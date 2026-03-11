<?php

use App\Models\Server;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->withSshKeys()->create();
});

test('valid token returns provisioning script', function () {
    Server::create([
        'user_id' => $this->user->id,
        'name' => 'Production Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => 'valid-token-123',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
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
        ->toContain('MEMORY_MB=2048')
        ->toContain('MYSQL_ROOT_PASSWORD="test-mysql-password-123"')
        ->toContain('DEPLOY_USER_PASSWORD="test-deploy-password-123"');
});

test('invalid token returns 410 gone', function () {
    $response = $this->get(route('provision.show', 'invalid-token'));

    $response->assertStatus(410);
});

test('token is nullified after first use', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => 'one-time-token',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $this->get(route('provision.show', 'one-time-token'))->assertStatus(200);

    expect($server->fresh()->provision_token)->toBeNull();

    $this->get(route('provision.show', 'one-time-token'))->assertStatus(410);
});

test('script includes user root ssh key', function () {
    $user = User::factory()->withSshKeys()->create();

    Server::create([
        'user_id' => $user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provision_token' => 'token-with-user-key',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $response = $this->get(route('provision.show', 'token-with-user-key'));

    expect($response->getContent())->toContain($user->ssh_public_key);
});
