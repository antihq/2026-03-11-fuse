<?php

use App\Callbacks\MarkSiteDeployed;
use App\Models\Server;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;

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
        'status' => 'deploying',
    ]);
});

test('handle updates site to active when task succeeds', function () {
    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'ssh_user' => 'deploy',
        'script' => 'echo test',
        'status' => 'finished',
        'exit_code' => 0,
        'output' => 'Success',
        'timeout' => 60,
    ]);

    $callback = new MarkSiteDeployed($this->site->id);
    $callback->handle($task);

    expect($this->site->fresh())
        ->status->toBe('active')
        ->deployed_at->not->toBeNull();
});

test('handle updates site to failed when task fails', function () {
    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'ssh_user' => 'deploy',
        'script' => 'echo test',
        'status' => 'finished',
        'exit_code' => 1,
        'output' => 'Error',
        'timeout' => 60,
    ]);

    $callback = new MarkSiteDeployed($this->site->id);
    $callback->handle($task);

    expect($this->site->fresh())
        ->status->toBe('failed')
        ->deployed_at->toBeNull();
});

test('handle does nothing when site not found', function () {
    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'ssh_user' => 'deploy',
        'script' => 'echo test',
        'status' => 'finished',
        'exit_code' => 0,
        'output' => 'Success',
        'timeout' => 60,
    ]);

    $callback = new MarkSiteDeployed(9999);
    $callback->handle($task);

    expect($this->site->fresh())
        ->status->toBe('deploying')
        ->deployed_at->toBeNull();
});
