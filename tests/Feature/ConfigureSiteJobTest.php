<?php

use App\Jobs\ConfigureSite;
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
    ]);
});

test('handle updates site to configuring then active on task success', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Site configured successfully', exitCode: 0)),
    ]);

    $job = new ConfigureSite($this->site->id);
    $job->handle();

    expect($this->site->fresh())
        ->status->toBe('active')
        ->configured_at->not->toBeNull();

    $task = Task::where('server_id', $this->server->id)->first();
    expect($task)
        ->ssh_user->toBe('deploy')
        ->exit_code->toBe(0);
});

test('handle updates site to failed on task failure', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Permission denied', exitCode: 1)),
    ]);

    $job = new ConfigureSite($this->site->id);
    $job->handle();

    expect($this->site->fresh())
        ->status->toBe('failed')
        ->configured_at->toBeNull();
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
    ]);

    $job = new ConfigureSite($site->id);
    $job->handle();

    expect($site->fresh())
        ->status->toBe('failed')
        ->configured_at->toBeNull();

    expect(Task::where('server_id', $server->id)->count())->toBe(0);
});

test('generate script produces correct caddyfile', function () {
    $job = new ConfigureSite($this->site->id);

    $method = new ReflectionMethod($job, 'generateScript');
    $method->setAccessible(true);

    $script = $method->invoke($job, $this->site, $this->server);

    expect($script)
        ->toContain('SITES_USER="deploy"')
        ->toContain('HOSTNAME="example.com"')
        ->toContain('PHP_VERSION="8.4"')
        ->toContain('SITE_DIR="/home/$SITES_USER/$HOSTNAME"')
        ->toContain('root * /home/deploy/example.com/public')
        ->toContain('php8.4-fpm.sock')
        ->toContain('sudo service caddy reload');
});

test('failed method updates site status', function () {
    $job = new ConfigureSite($this->site->id);
    $job->failed(new Exception('Test failure'));

    expect($this->site->fresh()->status)->toBe('failed');
});
