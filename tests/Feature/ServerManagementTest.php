<?php

use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('guests cannot access servers index page', function () {
    $this->get(route('servers.index'))->assertRedirect(route('login'));
});

test('guests cannot access servers create page', function () {
    $this->get(route('servers.create'))->assertRedirect(route('login'));
});

test('guests cannot access servers edit page', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
    ]);

    $this->get(route('servers.edit', $server))->assertRedirect(route('login'));
});

test('users with servers can access the index page', function () {
    $this->actingAs($this->user);

    Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
    ]);

    Livewire::test('pages::servers.index')
        ->assertOk();
});

test('users with no servers are redirected to create page', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::servers.index')
        ->assertRedirect(route('servers.create'));
});

test('authenticated users can access servers create page', function () {
    $this->actingAs($this->user)
        ->get(route('servers.create'))
        ->assertStatus(200);
});

test('user can create a server', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::servers.create')
        ->set('name', 'Production Web 1')
        ->set('ip_address', '192.168.1.100')
        ->set('ram_mb', '2048')
        ->set('authorized_keys', 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI test@example.com')
        ->call('save')
        ->assertRedirect(route('servers.provision', 1));

    $server = Server::where('user_id', $this->user->id)->first();

    expect($server)
        ->name->toBe('Production Web 1')
        ->ip_address->toBe('192.168.1.100')
        ->ram_mb->toBe(2048)
        ->authorized_keys->toBe('ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI test@example.com')
        ->ssh_setup_token->not->toBeNull()
        ->provision_status->toBe('pending')
        ->mysql_root_password->not->toBeNull()
        ->deploy_user_password->not->toBeNull();
});

test('user can create a server without ssh keys', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::servers.create')
        ->set('name', 'Database Server')
        ->set('ip_address', '10.0.0.50')
        ->set('ram_mb', '4096')
        ->call('save')
        ->assertRedirect(route('servers.provision', 1));

    expect(Server::where('user_id', $this->user->id)->count())->toBe(1);
});

test('server creation validates required fields', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::servers.create')
        ->set('name', '')
        ->set('ip_address', '')
        ->set('ram_mb', '')
        ->call('save')
        ->assertHasErrors(['name', 'ip_address', 'ram_mb']);
});

test('server creation validates ip address format', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::servers.create')
        ->set('name', 'Test Server')
        ->set('ip_address', 'invalid-ip')
        ->set('ram_mb', '2048')
        ->call('save')
        ->assertHasErrors('ip_address');
});

test('server creation validates ssh key format', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::servers.create')
        ->set('name', 'Test Server')
        ->set('ip_address', '192.168.1.1')
        ->set('ram_mb', '2048')
        ->set('authorized_keys', "invalid-key\nssh-ed25519 valid-key")
        ->call('save')
        ->assertHasErrors('authorized_keys');
});

test('valid ssh key formats are accepted', function () {
    $this->actingAs($this->user);
    $keys = "ssh-rsa AAAAB3NzaC1yc2E user@example.com\nssh-ed25519 AAAAC3NzaC1lZDI1NTE5 user2@example.com";

    Livewire::test('pages::servers.create')
        ->set('name', 'Test Server')
        ->set('ip_address', '192.168.1.1')
        ->set('ram_mb', '2048')
        ->set('authorized_keys', $keys)
        ->call('save')
        ->assertHasNoErrors();

    $server = Server::where('user_id', $this->user->id)->first();
    expect($server->authorizedKeysCount())->toBe(2);
});

test('user can edit a server', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Original Name',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
    ]);

    Livewire::test('pages::servers.edit', ['server' => $server->id])
        ->assertSet('name', 'Original Name')
        ->assertSet('ip_address', '192.168.1.1')
        ->set('name', 'Updated Name')
        ->set('ram_mb', '2048')
        ->call('save')
        ->assertRedirect(route('servers.index'));

    expect($server->fresh())
        ->name->toBe('Updated Name')
        ->ram_mb->toBe(2048);
});

test('edit page validates required fields', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
    ]);

    Livewire::test('pages::servers.edit', ['server' => $server->id])
        ->set('name', '')
        ->set('ip_address', '')
        ->set('ram_mb', '')
        ->call('save')
        ->assertHasErrors(['name', 'ip_address', 'ram_mb']);
});

test('edit page validates ip address format', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
    ]);

    Livewire::test('pages::servers.edit', ['server' => $server->id])
        ->set('ip_address', 'invalid-ip')
        ->call('save')
        ->assertHasErrors('ip_address');
});

test('edit page validates ssh key format', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
    ]);

    Livewire::test('pages::servers.edit', ['server' => $server->id])
        ->set('authorized_keys', "invalid-key\nssh-ed25519 valid-key")
        ->call('save')
        ->assertHasErrors('authorized_keys');
});

test('user can update server ssh keys', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
    ]);

    $newKeys = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI updated@example.com';

    Livewire::test('pages::servers.edit', ['server' => $server->id])
        ->set('authorized_keys', $newKeys)
        ->call('save')
        ->assertRedirect(route('servers.index'));

    expect($server->fresh()->authorized_keys)->toBe($newKeys);
});

test('ecdsa ssh key formats are accepted', function () {
    $this->actingAs($this->user);

    $keys = "ecdsa-sha2-nistp256 AAAAE2VjZHNhLXNoYTItbmlzdHA user@ecdsa256\necdsa-sha2-nistp384 AAAAE2VjZHNhLXNoYTItbmlzdHA user@ecdsa384\necdsa-sha2-nistp521 AAAAE2VjZHNhLXNoYTItbmlzdHA user@ecdsa521";

    Livewire::test('pages::servers.create')
        ->set('name', 'Test Server')
        ->set('ip_address', '192.168.1.1')
        ->set('ram_mb', '2048')
        ->set('authorized_keys', $keys)
        ->call('save')
        ->assertHasNoErrors();

    $server = Server::where('user_id', $this->user->id)->first();
    expect($server->authorizedKeysCount())->toBe(3);
});

test('edit page returns 404 for non-existent server', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::servers.edit', ['server' => 99999]);
})->throws(ModelNotFoundException::class);

test('user can delete a server from index page', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'To Delete',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
    ]);

    Livewire::test('pages::servers.index')
        ->call('deleteServer', $server->id);

    expect(Server::find($server->id))->toBeNull();
});

test('user cannot edit servers from other users', function () {
    $otherUser = User::factory()->create();
    $otherServer = Server::create([
        'user_id' => $otherUser->id,
        'name' => 'Other Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::servers.edit', ['server' => $otherServer->id])
        ->assertRedirect(route('servers.index'));
});

test('user cannot delete servers from other users', function () {
    $otherUser = User::factory()->create();
    $otherServer = Server::create([
        'user_id' => $otherUser->id,
        'name' => 'Other Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
    ]);

    $this->actingAs($this->user);

    Server::create([
        'user_id' => $this->user->id,
        'name' => 'My Server',
        'ip_address' => '192.168.1.2',
        'ram_mb' => 1024,
    ]);

    Livewire::test('pages::servers.index')
        ->call('deleteServer', $otherServer->id);

    expect(Server::find($otherServer->id))->not->toBeNull();
});

test('index shows view button and link for provisioned servers', function () {
    $this->actingAs($this->user);

    Server::create([
        'user_id' => $this->user->id,
        'name' => 'Provisioned Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Livewire::test('pages::servers.index')
        ->assertSee('View')
        ->assertSee(route('servers.show', 1));
});

test('index hides view button for non-provisioned servers', function () {
    $this->actingAs($this->user);

    Server::create([
        'user_id' => $this->user->id,
        'name' => 'Pending Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => null,
    ]);

    $component = Livewire::test('pages::servers.index');

    $html = $component->html();
    expect($html)->not->toContain('servers.show');
});

test('sites user defaults to deploy', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::servers.create')
        ->assertSet('sites_user', 'deploy');
});

test('sites user must start with lowercase letter', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::servers.create')
        ->set('name', 'Test Server')
        ->set('ip_address', '192.168.1.1')
        ->set('ram_mb', '2048')
        ->set('sites_user', '1invalid')
        ->call('save')
        ->assertHasErrors('sites_user');
});

test('sites user can contain lowercase letters numbers underscores and hyphens', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::servers.create')
        ->set('name', 'Test Server')
        ->set('ip_address', '192.168.1.1')
        ->set('ram_mb', '2048')
        ->set('sites_user', 'deploy_user-1')
        ->call('save')
        ->assertHasNoErrors();

    $server = Server::where('user_id', $this->user->id)->first();
    expect($server->sites_user)->toBe('deploy_user-1');
});

test('sites user cannot contain uppercase letters', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::servers.create')
        ->set('name', 'Test Server')
        ->set('ip_address', '192.168.1.1')
        ->set('ram_mb', '2048')
        ->set('sites_user', 'DeployUser')
        ->call('save')
        ->assertHasErrors('sites_user');
});

test('sites user can be updated via edit page', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'sites_user' => 'deploy',
    ]);

    Livewire::test('pages::servers.edit', ['server' => $server->id])
        ->set('sites_user', 'webmaster')
        ->call('save')
        ->assertRedirect(route('servers.index'));

    expect($server->fresh()->sites_user)->toBe('webmaster');
});
