<?php

use App\Callbacks\MarkSiteDeployed;
use App\Jobs\DeploySite;
use App\Jobs\InstallSiteQueue;
use App\Models\Server;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->withSshKeys()->create();
    $this->server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provisioned_at' => now(),
    ]);
    $this->site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'ready',
    ]);
});

test('handle creates task with callback options and sets site to deploying', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Background script started', exitCode: 0)),
    ]);

    $job = new DeploySite($this->site->id);
    $job->handle();

    expect($this->site->fresh())
        ->status->toBe('deploying')
        ->deployed_at->toBeNull();

    $task = Task::where('server_id', $this->server->id)->first();
    expect($task)
        ->ssh_user->toBe('deploy')
        ->status->toBe('running')
        ->options->toBeArray()
        ->and($task->options['then'])->toHaveCount(1)
        ->and($task->options['then'][0])->toBeInstanceOf(MarkSiteDeployed::class);
});

test('handle updates site to failed when user has no ssh key', function () {
    $user = User::factory()->create();
    $server = Server::create([
        'user_id' => $user->id,
        'name' => 'No Key Server',
        'ip_address' => '10.0.0.1',
        'ram_mb' => 1024,
        'sites_user' => 'deploy',
        'provisioned_at' => now(),
    ]);
    $site = Site::create([
        'server_id' => $server->id,
        'hostname' => 'nokey.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'ready',
    ]);

    $job = new DeploySite($site->id);
    $job->handle();

    expect($site->fresh())
        ->status->toBe('failed')
        ->deployed_at->toBeNull();

    expect(Task::where('server_id', $server->id)->count())->toBe(0);
});

test('failed method updates site status', function () {
    $job = new DeploySite($this->site->id);
    $job->failed(new Exception('Test failure'));

    expect($this->site->fresh()->status)->toBe('failed');
});

test('deploy script contains expected content', function () {
    $script = view('scripts.deploy-site', [
        'hostname' => 'example.com',
        'sitesUser' => 'deploy',
        'repositoryUrl' => 'git@github.com:user/repo.git',
        'repositoryBranch' => 'main',
        'phpVersion' => '8.4',
    ])->render();

    expect($script)
        ->toContain('SITES_USER="deploy"')
        ->toContain('HOSTNAME="example.com"')
        ->toContain('REPOSITORY_URL="git@github.com:user/repo.git"')
        ->toContain('REPOSITORY_BRANCH="main"')
        ->toContain('PHP_VERSION="8.4"')
        ->toContain('git clone')
        ->toContain('git fetch origin')
        ->toContain('Setting permissions');
});

test('deploy script creates repository directory', function () {
    $script = view('scripts.deploy-site', [
        'hostname' => 'example.com',
        'sitesUser' => 'deploy',
        'repositoryUrl' => 'git@github.com:user/repo.git',
        'repositoryBranch' => 'main',
        'phpVersion' => '8.4',
    ])->render();

    expect($script)->toContain('REPO_DIR="$SITE_DIR/repository"');
});

test('deploy script uses git fetch and reset for existing repository', function () {
    $script = view('scripts.deploy-site', [
        'hostname' => 'example.com',
        'sitesUser' => 'deploy',
        'repositoryUrl' => 'git@github.com:user/repo.git',
        'repositoryBranch' => 'main',
        'phpVersion' => '8.4',
    ])->render();

    expect($script)
        ->toContain('git fetch origin')
        ->toContain('git reset --hard origin/$REPOSITORY_BRANCH')
        ->not->toContain('git pull origin $REPOSITORY_BRANCH');
});

test('deploy script includes before hook when provided', function () {
    $script = view('scripts.deploy-site', [
        'hostname' => 'example.com',
        'sitesUser' => 'deploy',
        'repositoryUrl' => 'git@github.com:user/repo.git',
        'repositoryBranch' => 'main',
        'phpVersion' => '8.4',
        'hookBeforeUpdatingRepository' => 'echo "before hook"',
    ])->render();

    expect($script)
        ->toContain('Running hook before updating repository')
        ->toContain('echo "before hook"');
});

test('deploy script includes after hook when provided', function () {
    $script = view('scripts.deploy-site', [
        'hostname' => 'example.com',
        'sitesUser' => 'deploy',
        'repositoryUrl' => 'git@github.com:user/repo.git',
        'repositoryBranch' => 'main',
        'phpVersion' => '8.4',
        'hookAfterUpdatingRepository' => 'echo "after hook"',
    ])->render();

    expect($script)
        ->toContain('Running hook after updating repository')
        ->toContain('echo "after hook"');
});

test('deploy script does not include hooks when not provided', function () {
    $script = view('scripts.deploy-site', [
        'hostname' => 'example.com',
        'sitesUser' => 'deploy',
        'repositoryUrl' => 'git@github.com:user/repo.git',
        'repositoryBranch' => 'main',
        'phpVersion' => '8.4',
    ])->render();

    expect($script)
        ->not->toContain('Running hook before updating repository')
        ->not->toContain('Running hook after updating repository');
});

test('handle passes site hooks to deploy script', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'hooked.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'ready',
        'hook_before_updating_repository' => 'echo "custom before"',
        'hook_after_updating_repository' => 'echo "custom after"',
    ]);

    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Background script started', exitCode: 0)),
    ]);

    $job = new DeploySite($site->id);
    $job->handle();

    $task = Task::where('server_id', $this->server->id)
        ->where('ssh_user', 'deploy')
        ->first();

    expect($task->script)
        ->toContain('echo "custom before"')
        ->toContain('echo "custom after"')
        ->toContain('Running hook before updating repository')
        ->toContain('Running hook after updating repository');
});

test('deploy script includes database credentials when site has them', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'withdb.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'ready',
        'database_name' => 'withdb_com',
        'database_user' => 'withdb_com',
        'database_password' => 'db_password',
    ]);

    $script = view('scripts.deploy-site', [
        'hostname' => 'withdb.com',
        'sitesUser' => 'deploy',
        'repositoryUrl' => 'git@github.com:user/repo.git',
        'repositoryBranch' => 'main',
        'phpVersion' => '8.4',
        'databaseName' => 'withdb_com',
        'databaseUser' => 'withdb_com',
        'databasePassword' => 'db_password',
    ])->render();

    expect($script)
        ->toContain('export DB_DATABASE="withdb_com"')
        ->toContain('export DB_USERNAME="withdb_com"')
        ->toContain('export DB_PASSWORD="db_password"');
});

test('deploy script does not include database credentials when site does not have them', function () {
    $script = view('scripts.deploy-site', [
        'hostname' => 'example.com',
        'sitesUser' => 'deploy',
        'repositoryUrl' => 'git@github.com:user/repo.git',
        'repositoryBranch' => 'main',
        'phpVersion' => '8.4',
    ])->render();

    expect($script)
        ->not->toContain('export DB_DATABASE=')
        ->not->toContain('export DB_USERNAME=')
        ->not->toContain('export DB_PASSWORD=');
});

test('handle dispatches InstallSiteQueue when queue_enabled is true', function () {
    Queue::fake();
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Background script started', exitCode: 0)),
    ]);

    $this->site->update(['queue_enabled' => true, 'queue_processes' => 3]);

    $job = new DeploySite($this->site->id);
    $job->handle();

    Queue::assertPushed(InstallSiteQueue::class, function ($job) {
        return $job->siteId === $this->site->id;
    });
});

test('handle does not dispatch InstallSiteQueue when queue_enabled is false', function () {
    Queue::fake();
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Background script started', exitCode: 0)),
    ]);

    $this->site->update(['queue_enabled' => false]);

    $job = new DeploySite($this->site->id);
    $job->handle();

    Queue::assertNotPushed(InstallSiteQueue::class);
});
