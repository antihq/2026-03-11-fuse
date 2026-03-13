<?php

use App\Callbacks\MarkServerProvisioned;
use App\Models\Server;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;

beforeEach(function () {
    $this->user = User::factory()->withSshKeys()->create();
});

test('handle updates server to provisioned when task succeeds', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'provision_status' => 'provisioning',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $server->id,
        'ssh_user' => 'root',
        'script' => 'echo test',
        'status' => 'finished',
        'exit_code' => 0,
        'output' => 'Success',
        'timeout' => 60,
    ]);

    $server->update(['provision_task_id' => $task->id]);

    $callback = new MarkServerProvisioned($server->id);
    $callback->handle($task);

    expect($server->fresh())
        ->provision_status->toBe('provisioned')
        ->provisioned_at->not->toBeNull()
        ->provision_task_id->toBeNull();
});

test('handle updates server to failed when task fails', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'provision_status' => 'provisioning',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $server->id,
        'ssh_user' => 'root',
        'script' => 'echo test',
        'status' => 'finished',
        'exit_code' => 1,
        'output' => 'Error occurred',
        'timeout' => 60,
    ]);

    $server->update(['provision_task_id' => $task->id]);

    $callback = new MarkServerProvisioned($server->id);
    $callback->handle($task);

    expect($server->fresh())
        ->provision_status->toBe('failed')
        ->provisioned_at->toBeNull()
        ->provision_task_id->toBeNull();
});

test('handle clears provision task id', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'provision_status' => 'provisioning',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $server->id,
        'ssh_user' => 'root',
        'script' => 'echo test',
        'status' => 'finished',
        'exit_code' => 0,
        'output' => 'Success',
        'timeout' => 60,
    ]);

    $server->update(['provision_task_id' => $task->id]);

    $callback = new MarkServerProvisioned($server->id);
    $callback->handle($task);

    expect($server->fresh()->provision_task_id)->toBeNull();
});

test('handle sets provisioned at on success', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'provision_status' => 'provisioning',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $server->id,
        'ssh_user' => 'root',
        'script' => 'echo test',
        'status' => 'finished',
        'exit_code' => 0,
        'output' => 'Success',
        'timeout' => 60,
    ]);

    $server->update(['provision_task_id' => $task->id]);

    $callback = new MarkServerProvisioned($server->id);
    $callback->handle($task);

    expect($server->fresh()->provisioned_at)
        ->not->toBeNull()
        ->toBeInstanceOf(CarbonInterface::class);
});

test('handle does not set provisioned at on failure', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'provision_status' => 'provisioning',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $server->id,
        'ssh_user' => 'root',
        'script' => 'echo test',
        'status' => 'finished',
        'exit_code' => 1,
        'output' => 'Error occurred',
        'timeout' => 60,
    ]);

    $server->update(['provision_task_id' => $task->id]);

    $callback = new MarkServerProvisioned($server->id);
    $callback->handle($task);

    expect($server->fresh()->provisioned_at)->toBeNull();
});

test('handle uses correct callback instance', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'provision_status' => 'provisioning',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $server->id,
        'ssh_user' => 'root',
        'script' => 'echo test',
        'status' => 'finished',
        'exit_code' => 0,
        'output' => 'Success',
        'timeout' => 60,
    ]);

    $server->update(['provision_task_id' => $task->id]);

    $callback = new MarkServerProvisioned($server->id);
    $callback->handle($task);

    expect($server->fresh()->provision_status)->toBe('provisioned');
});
