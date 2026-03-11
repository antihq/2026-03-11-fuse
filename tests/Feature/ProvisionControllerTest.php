<?php

use App\Models\Server;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create(['user_id' => $this->user->id]);
});

test('valid token returns provisioning script', function () {
    $server = Server::create([
        'team_id' => $this->team->id,
        'name' => 'Production Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => 'valid-token-123',
    ]);

    $response = $this->get(route('provision.show', 'valid-token-123'));

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertHeader('Content-Disposition', 'inline; filename="provision.sh"');

    $content = $response->getContent();

    expect($content)
        ->toContain('Server Provisioning Script')
        ->toContain('Production Server')
        ->toContain('SITES_USER="deploy"')
        ->toContain('MEMORY_MB=2048');
});

test('invalid token returns 410 gone', function () {
    $response = $this->get(route('provision.show', 'invalid-token'));

    $response->assertStatus(410);
});

test('token is nullified after first use', function () {
    $server = Server::create([
        'team_id' => $this->team->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => 'one-time-token',
    ]);

    $this->get(route('provision.show', 'one-time-token'))->assertStatus(200);

    expect($server->fresh()->provision_token)->toBeNull();

    $this->get(route('provision.show', 'one-time-token'))->assertStatus(410);
});

test('script includes team root ssh key', function () {
    $team = Team::factory()->create([
        'user_id' => $this->user->id,
        'ssh_public_key' => 'ssh-ed25519 AAAATEAM team@key',
    ]);

    Server::create([
        'team_id' => $team->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => 'token-with-team-key',
    ]);

    $response = $this->get(route('provision.show', 'token-with-team-key'));

    expect($response->getContent())->toContain('ssh-ed25519 AAAATEAM team@key');
});
