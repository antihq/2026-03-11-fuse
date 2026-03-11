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
        'provision_token' => 'test-token',
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
        'provision_token' => 'test-token',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $this->actingAs($this->user)
        ->get(route('servers.provision', $server))
        ->assertStatus(200)
        ->assertSee('Provision Server')
        ->assertSee('test-token');
});

test('provision page displays provision url', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => 'my-provision-token',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->assertSet('provisionUrl', url('/provision/my-provision-token'));
});

test('regenerate token creates new token and updates url', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => 'original-token',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->call('regenerateToken')
        ->assertSet('provisionUrl', fn ($url) => str_contains($url, '/provision/') && ! str_contains($url, 'original-token'));

    expect($server->fresh()->provision_token)->not->toBe('original-token');
});

test('mark as provisioned sets provisioned_at and clears token', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => 'test-token',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->call('markAsProvisioned')
        ->assertSet('provisionUrl', '');

    $server->refresh();

    expect($server->provisioned_at)->not->toBeNull()
        ->and($server->provision_token)->toBeNull();
});

test('regenerate token does nothing when server is already provisioned', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => null,
        'provisioned_at' => now(),
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->call('regenerateToken')
        ->assertSet('provisionUrl', '');

    expect($server->fresh()->provision_token)->toBeNull();
});

test('provisioned server shows success message instead of command', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => null,
        'provisioned_at' => now(),
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->assertSee('Server Provisioned')
        ->assertDontSee('Provisioning Command')
        ->assertDontSee('Mark as Provisioned');
});

test('unprovisioned server shows provisioning command and mark button', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => 'test-token',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->assertSee('Provisioning Command')
        ->assertSee('Mark as Provisioned')
        ->assertDontSee('Server Provisioned');
});

test('provision page handles server without provision token', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => null,
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->assertSet('provisionUrl', '');
});
