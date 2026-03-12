<?php

use App\Callbacks\MarkSiteDeployed;
use App\Jobs\DeploySite;
use App\Models\Server;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Process;

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
        ->toContain('REPOSITORY_URL="git@github.com:user/repo.git')
        ->toContain('REPOSITORY_BRANCH="main"')
        ->toContain('PHP_VERSION="8.4"')
        ->toContain('git clone')
        ->toContain('Installing Composer dependencies')
        ->toContain('npm run build')
        ->toContain('artisan config:cache')
        ->toContain('maintenance.html');
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

test('deploy script removes maintenance page on completion', function () {
    $script = view('scripts.deploy-site', [
        'hostname' => 'example.com',
        'sitesUser' => 'deploy',
        'repositoryUrl' => 'git@github.com:user/repo.git',
        'repositoryBranch' => 'main',
        'phpVersion' => '8.4',
    ])->render();

    expect($script)
        ->toContain('rm -f "$PUBLIC_DIR/maintenance.html"');
});
