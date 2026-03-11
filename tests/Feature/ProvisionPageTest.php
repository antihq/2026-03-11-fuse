<?php

use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create(['user_id' => $this->user->id]);
});

test('guests cannot access provision page', function () {
    $server = Server::create([
        'team_id' => $this->team->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => 'test-token',
    ]);

    $this->get(route('servers.provision', $server))->assertRedirect(route('login'));
});

test('authenticated users can access provision page', function () {
    $server = Server::create([
        'team_id' => $this->team->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => 'test-token',
    ]);

    $this->actingAs($this->user)
        ->get(route('servers.provision', $server))
        ->assertStatus(200)
        ->assertSee('Provision Server')
        ->assertSee('test-token');
});

test('provision page displays provision url', function () {
    $server = Server::create([
        'team_id' => $this->team->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => 'my-provision-token',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->assertSet('provisionUrl', url('/provision/my-provision-token'));
});

test('regenerate token creates new token and updates url', function () {
    $server = Server::create([
        'team_id' => $this->team->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => 'original-token',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.provision', ['server' => $server])
        ->call('regenerateToken')
        ->assertSet('provisionUrl', fn ($url) => str_contains($url, '/provision/') && ! str_contains($url, 'original-token'));

    expect($server->fresh()->provision_token)->not->toBe('original-token');
});
