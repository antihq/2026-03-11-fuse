<?php

use App\Callbacks\MarkServerProvisioned;
use App\Jobs\ProvisionServer;
use App\Models\Server;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->withSshKeys()->create();
});

test('handle creates task with mark server provisioned callback', function () {
    Process::fake();

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => 'test-token',
        'ssh_ready_at' => now(),
        'provision_status' => 'ssh_setup',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $job = new ProvisionServer($server->id);
    $job->handle();

    $task = Task::where('server_id', $server->id)->first();

    expect($task)
        ->not->toBeNull()
        ->user_id->toBe($this->user->id)
        ->server_id->toBe($server->id)
        ->ssh_user->toBe('root')
        ->timeout->toBe(1800)
        ->status->toBe('running')
        ->and($task->options['then'])->toHaveCount(1)
        ->and($task->options['then'][0])->toBeInstanceOf(MarkServerProvisioned::class);
});

test('handle updates provision status to provisioning', function () {
    Process::fake();

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'ssh_setup_token' => 'test-token',
        'ssh_ready_at' => now(),
        'provision_status' => 'ssh_setup',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $job = new ProvisionServer($server->id);
    $job->handle();

    expect($server->fresh()->provision_status)->toBe('provisioning');
});

test('handle updates provision status to failed when user has no ssh key', function () {
    $user = User::factory()->create();

    $server = Server::create([
        'user_id' => $user->id,
        'name' => 'No Key Server',
        'ip_address' => '10.0.0.1',
        'ram_mb' => 1024,
        'ssh_setup_token' => 'test-token',
        'ssh_ready_at' => now(),
        'provision_status' => 'ssh_setup',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $job = new ProvisionServer($server->id);
    $job->handle();

    expect($server->fresh()->provision_status)->toBe('failed');

    expect(Task::where('server_id', $server->id)->count())->toBe(0);
});

test('handle links task to server via provision task id', function () {
    Process::fake();

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'ssh_setup_token' => 'test-token',
        'ssh_ready_at' => now(),
        'provision_status' => 'ssh_setup',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $job = new ProvisionServer($server->id);
    $job->handle();

    expect($server->fresh()->provision_task_id)->not->toBeNull();

    $task = Task::where('server_id', $server->id)->first();
    expect($task->id)->toBe($server->fresh()->provision_task_id);
});

test('handle generates script with user public key', function () {
    Process::fake();

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'ssh_setup_token' => 'test-token',
        'ssh_ready_at' => now(),
        'provision_status' => 'ssh_setup',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $job = new ProvisionServer($server->id);
    $job->handle();

    $task = Task::where('server_id', $server->id)->first();

    expect($task->script)->toContain($this->user->ssh_public_key);
});

test('handle generates script with correct server details', function () {
    Process::fake();

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Production Web 1',
        'ip_address' => '192.168.1.100',
        'ram_mb' => 4096,
        'sites_user' => 'www-data',
        'ssh_setup_token' => 'test-token',
        'ssh_ready_at' => now(),
        'provision_status' => 'ssh_setup',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $job = new ProvisionServer($server->id);
    $job->handle();

    $task = Task::where('server_id', $server->id)->first();

    expect($task->script)
        ->toContain('SITES_USER="www-data"')
        ->toContain('MEMORY_MB=4096');
});

test('handle uses empty string for root ssh key when user key is missing', function () {
    $userWithoutKey = User::factory()->create();

    $server = Server::create([
        'user_id' => $userWithoutKey->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => 'test-token',
        'ssh_ready_at' => now(),
        'provision_status' => 'ssh_setup',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $job = new ProvisionServer($server->id);
    $job->handle();

    expect($server->fresh()->provision_status)->toBe('failed');
});
