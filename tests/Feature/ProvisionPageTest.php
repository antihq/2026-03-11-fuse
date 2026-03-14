<?php

use App\Models\Server;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('guests cannot access provision page', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => 'test-token',
        'provision_status' => 'pending',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $this->get(route('servers.provision', $server))->assertRedirect(route('login'));
});

test('authenticated users can access provision page', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => 'test-token',
        'provision_status' => 'pending',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $this->actingAs($this->user)
        ->get(route('servers.provision', $server))
        ->assertStatus(200)
        ->assertSee('Provision Server')
        ->assertSee('Set up SSH Access');
});

test('provision page displays ssh setup url', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => 'my-ssh-token',
        'provision_status' => 'pending',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->assertSet('sshSetupUrl', url('/ssh-setup/my-ssh-token'));
});

test('retry provision regenerates ssh setup token for failed server', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => null,
        'provision_status' => 'failed',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->call('retryProvision')
        ->assertSet('sshSetupUrl', fn ($url) => str_contains($url, '/ssh-setup/'));

    expect($server->fresh()->ssh_setup_token)->not->toBeNull()
        ->and($server->fresh()->provision_status)->toBe('pending');
});

test('provisioned server shows success message', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => null,
        'provision_status' => 'provisioned',
        'provisioned_at' => now(),
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->assertSee('Server Provisioned')
        ->assertDontSee('SSH Setup Command')
        ->assertDontSee('Set up SSH Access');
});

test('provisioning server shows progress indicator', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => null,
        'ssh_ready_at' => now(),
        'provision_status' => 'ssh_setup',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->assertSee('Setting up SSH Access')
        ->assertSee('Waiting for SSH key to be added');
});

test('provision page handles server without ssh setup token', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => null,
        'provision_status' => 'failed',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->assertSet('sshSetupUrl', '');
});

test('pending server shows ssh setup command', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => 'test-token',
        'provision_status' => 'pending',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->assertSee('SSH Setup Command')
        ->assertSee('Run the Command')
        ->assertDontSee('Server Provisioned');
});

test('fetchOutput sets live output when task is running', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => null,
        'ssh_ready_at' => now(),
        'provision_status' => 'provisioning',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $server->id,
        'ssh_user' => 'root',
        'script' => 'echo "test"',
        'status' => 'running',
        'timeout' => 60,
    ]);

    $server->update(['provision_task_id' => $task->id]);

    $this->user->update(['ssh_private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\ntest-key\n-----END OPENSSH PRIVATE KEY-----"]);

    Process::fake([
        '*' => Process::result(output: 'Remote output line 1\nRemote output line 2', exitCode: 0),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->call('fetchOutput')
        ->assertSet('liveOutput', fn ($val) => str_contains($val, 'Remote output line 1') && str_contains($val, 'Remote output line 2'))
        ->assertDispatched('output-fetched');
});

test('fetchOutput does nothing when task is not running', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => null,
        'ssh_ready_at' => now(),
        'provision_status' => 'provisioning',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $server->id,
        'ssh_user' => 'root',
        'script' => 'echo "test"',
        'status' => 'finished',
        'output' => 'Task finished output',
        'timeout' => 60,
    ]);

    $server->update(['provision_task_id' => $task->id]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->call('fetchOutput')
        ->assertSet('liveOutput', null);
});

test('fetchOutput does nothing when task is null', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => null,
        'ssh_ready_at' => now(),
        'provision_status' => 'provisioning',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->call('fetchOutput')
        ->assertSet('liveOutput', null);
});

test('shows live output when available', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => null,
        'ssh_ready_at' => now(),
        'provision_status' => 'provisioning',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $server->id,
        'ssh_user' => 'root',
        'script' => 'echo "test"',
        'status' => 'running',
        'timeout' => 60,
    ]);

    $server->update(['provision_task_id' => $task->id]);

    $this->user->update(['ssh_private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\ntest-key\n-----END OPENSSH PRIVATE KEY-----"]);

    Process::fake([
        '*' => Process::result(output: 'Live output from server', exitCode: 0),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->call('fetchOutput')
        ->assertSee('Live output from server');
});

test('falls back to task output when live output is null', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => null,
        'ssh_ready_at' => now(),
        'provision_status' => 'provisioning',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $server->id,
        'ssh_user' => 'root',
        'script' => 'echo "test"',
        'status' => 'finished',
        'output' => 'Stored task output',
        'timeout' => 60,
    ]);

    $server->update(['provision_task_id' => $task->id]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->assertSee('Stored task output')
        ->assertDontSee('Click "View Live Output" to see progress');
});

test('shows placeholder when no output available', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => null,
        'ssh_ready_at' => now(),
        'provision_status' => 'provisioning',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $server->id,
        'ssh_user' => 'root',
        'script' => 'echo "test"',
        'status' => 'running',
        'output' => null,
        'timeout' => 60,
    ]);

    $server->update(['provision_task_id' => $task->id]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->assertSee('Click "View Live Output" to see progress');
});

test('provisioning with task shows View Live Output button', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => null,
        'ssh_ready_at' => now(),
        'provision_status' => 'provisioning',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $server->id,
        'ssh_user' => 'root',
        'script' => 'echo "test"',
        'status' => 'running',
        'timeout' => 60,
    ]);

    $server->update(['provision_task_id' => $task->id]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->assertSee('Refresh')
        ->assertSee('View Live Output')
        ->assertDontSee('Run the Command');
});
