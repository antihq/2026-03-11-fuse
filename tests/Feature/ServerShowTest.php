<?php

use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('guests cannot access servers show page', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    $this->get(route('servers.show', $server))->assertRedirect(route('login'));
});

test('user can view a provisioned server', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Production Server',
        'ip_address' => '192.168.1.100',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'authorized_keys' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5 test@example.com',
        'provisioned_at' => now(),
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('Production Server')
        ->assertSee('192.168.1.100')
        ->assertSee('2,048 MB')
        ->assertSee('deploy')
        ->assertSee('1 configured')
        ->assertSee('Provisioned');
});

test('non-provisioned server redirects to provision page', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Pending Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => null,
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertRedirect(route('servers.provision', $server));
});

test('user cannot view servers from other users', function () {
    $otherUser = User::factory()->create();
    $otherServer = Server::create([
        'user_id' => $otherUser->id,
        'name' => 'Other Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::servers.show', ['server' => $otherServer->id])
        ->assertRedirect(route('servers.index'));
});

test('show page returns 404 for non-existent server', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::servers.show', ['server' => 99999]);
})->throws(ModelNotFoundException::class);

test('user can delete server from show page', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'To Delete',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->call('delete')
        ->assertRedirect(route('servers.index'));

    expect(Server::find($server->id))->toBeNull();
});

test('user cannot delete servers from other users via show page', function () {
    $otherUser = User::factory()->create();
    $otherServer = Server::create([
        'user_id' => $otherUser->id,
        'name' => 'Other Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::servers.show', ['server' => $otherServer->id])
        ->assertRedirect(route('servers.index'));

    expect(Server::find($otherServer->id))->not->toBeNull();
});

test('test connection shows success message when connection succeeds', function () {
    $user = User::factory()->withSshKeys()->create();
    $server = Server::create([
        'user_id' => $user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Process::fake([
        '*' => Process::result(exitCode: 0, output: 'connected'),
    ]);

    Livewire::actingAs($user)
        ->test('pages::servers.show', ['server' => $server->id])
        ->call('testConnection')
        ->assertSet('connectionStatus', 'Connection successful!')
        ->assertSet('connectionSuccess', true);
});

test('test connection shows failure message when connection fails', function () {
    $user = User::factory()->withSshKeys()->create();
    $server = Server::create([
        'user_id' => $user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Process::fake([
        '*' => Process::result(exitCode: 255, output: 'Permission denied'),
    ]);

    Livewire::actingAs($user)
        ->test('pages::servers.show', ['server' => $server->id])
        ->call('testConnection')
        ->assertSet('connectionSuccess', false)
        ->assertSet('connectionStatus', fn ($status) => str_contains($status, 'Permission denied'));
});

test('test connection shows error when user has no ssh key', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.show', ['server' => $server->id])
        ->call('testConnection')
        ->assertSet('connectionStatus', 'No SSH private key configured for your account.')
        ->assertSet('connectionSuccess', false);
});

test('show page displays sites section', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('Sites');
});

test('show page shows no sites message when empty', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('No sites configured yet.');
});

test('show page lists sites with hostname php version and branch', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Site::create([
        'server_id' => $server->id,
        'hostname' => 'example.com',
        'php_version' => '8.3',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'develop',
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('example.com')
        ->assertSee('8.3')
        ->assertSee('develop');
});

test('show page has add site button linking to create page', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('Add Site')
        ->assertSee(route('servers.sites.create', $server));
});
