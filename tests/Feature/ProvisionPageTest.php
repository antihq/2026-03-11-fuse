<?php

use App\Models\Server;
use App\Models\User;
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
        ->assertSet('sshSetupUrl', fn ($url) => str_contains($url, '/ssh-setup/'))
        ->assertSet('poll', false);

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
        ->assertSee('Run Command & Monitor Progress', escape: false)
        ->assertDontSee('Server Provisioned');
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

test('start polling enables polling for provisioning servers', function () {
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
        ->call('startPolling')
        ->assertSet('poll', true);
});

test('stop polling disables polling', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'ssh_setup_token' => null,
        'provision_status' => 'provisioning',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->call('stopPolling')
        ->assertSet('poll', false);
});
