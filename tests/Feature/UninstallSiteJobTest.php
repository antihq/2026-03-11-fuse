<?php

use App\Jobs\UninstallSite;
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
        'status' => 'ready',
        'database_name' => 'example_com',
        'database_user' => 'example_com',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);
});

test('handle creates task and runs uninstall script', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Site uninstalled successfully', exitCode: 0)),
    ]);

    $job = new UninstallSite($this->site->id);
    $job->handle();

    $task = Task::where('server_id', $this->server->id)->first();

    expect($task)
        ->user_id->toBe($this->user->id)
        ->ssh_user->toBe('root')
        ->exit_code->toBe(0);
});

test('handle deletes site after successful uninstall', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Site uninstalled successfully', exitCode: 0)),
    ]);

    $job = new UninstallSite($this->site->id);
    $job->handle();

    expect(Site::find($this->site->id))->toBeNull();
});

test('handle does not delete site on failure', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Error', exitCode: 1)),
    ]);

    $job = new UninstallSite($this->site->id);
    $job->handle();

    expect(Site::find($this->site->id))->not->toBeNull();
});

test('handle does nothing when user has no ssh key', function () {
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
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'status' => 'ready',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $job = new UninstallSite($site->id);
    $job->handle();

    expect(Task::where('server_id', $server->id)->count())->toBe(0);
});

test('uninstall script contains expected content', function () {
    $script = view('scripts.uninstall-site', [
        'hostname' => 'example.com',
        'sitesUser' => 'deploy',
        'databaseName' => 'example_com',
        'databaseUser' => 'example_com',
        'mysqlRootPassword' => 'rootpass',
    ])->render();

    expect($script)
        ->toContain('SITES_USER="deploy"')
        ->toContain('HOSTNAME="example.com"')
        ->toContain('SITE_DIR="/home/$SITES_USER/$HOSTNAME"')
        ->toContain('sed -i')
        ->toContain('/etc/caddy/Sites.caddy')
        ->toContain('rm -rf "$SITE_DIR"')
        ->toContain('service caddy reload')
        ->toContain('DATABASE_NAME="example_com"')
        ->toContain('DATABASE_USER="example_com"')
        ->toContain('DROP DATABASE IF EXISTS \`$DATABASE_NAME\`')
        ->toContain('DROP USER IF EXISTS \`$DATABASE_USER\`');
});

test('uninstall script removes caddy import line', function () {
    $script = view('scripts.uninstall-site', [
        'hostname' => 'example.com',
        'sitesUser' => 'deploy',
        'databaseName' => 'example_com',
        'databaseUser' => 'example_com',
        'mysqlRootPassword' => 'rootpass',
    ])->render();

    expect($script)
        ->toContain('import /home/$SITES_USER/$HOSTNAME/Caddyfile')
        ->toContain('sed -i')
        ->toContain('|d"');
});

test('uninstall script deletes site directory', function () {
    $script = view('scripts.uninstall-site', [
        'hostname' => 'mysite.test',
        'sitesUser' => 'deploy',
        'databaseName' => 'mysite_test',
        'databaseUser' => 'mysite_test',
        'mysqlRootPassword' => 'rootpass',
    ])->render();

    expect($script)
        ->toContain('SITE_DIR="/home/$SITES_USER/$HOSTNAME"')
        ->toContain('rm -rf "$SITE_DIR"');
});

test('failed method handles exception gracefully', function () {
    $job = new UninstallSite($this->site->id);

    $job->failed(new Exception('Test failure'));

    expect(true)->toBeTrue();
});
