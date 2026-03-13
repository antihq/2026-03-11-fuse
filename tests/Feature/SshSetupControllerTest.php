<?php

use App\Jobs\ProvisionServer;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->user = User::factory()->withSshKeys()->create();
});

test('show with valid token returns script with callback url', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'ssh_setup_token' => 'valid-token-123',
        'provision_status' => 'pending',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $response = $this->get(route('ssh-setup.show', 'valid-token-123'));

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertHeader('Content-Disposition', 'inline; filename="add-ssh-key.sh"');

    $content = $response->getContent();

    expect($content)
        ->toContain('SSH Key Setup Script')
        ->toContain($this->user->ssh_public_key)
        ->toContain('ssh-setup/valid-token-123/callback')
        ->toContain('curl -s "');

    expect($content)->not->toContain('&amp;');
});

test('show with invalid token returns 410', function () {
    $response = $this->get(route('ssh-setup.show', 'invalid-token'));

    $response->assertStatus(410);
});

test('show when user has no ssh key returns 400', function () {
    $user = User::factory()->create();

    $server = Server::create([
        'user_id' => $user->id,
        'name' => 'No Key Server',
        'ip_address' => '10.0.0.1',
        'ram_mb' => 1024,
        'ssh_setup_token' => 'token-123',
        'provision_status' => 'pending',
        'mysql_root_password' => 'test',
        'deploy_user_password' => 'test',
    ]);

    $response = $this->get(route('ssh-setup.show', 'token-123'));

    $response->assertStatus(400);
});

test('callback with valid signature updates server and dispatches job', function () {
    Queue::fake();

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'ssh_setup_token' => 'test-callback-token',
        'provision_status' => 'pending',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $signedUrl = URL::signedRoute(
        'ssh-setup.callback',
        ['token' => 'test-callback-token'],
        now()->addHours(24)
    );

    $response = $this->get($signedUrl);

    $response->assertStatus(200)
        ->assertContent('SSH setup confirmed');

    expect($server->fresh())
        ->ssh_setup_token->toBeNull()
        ->ssh_ready_at->not->toBeNull()
        ->provision_status->toBe('ssh_setup');

    Queue::assertPushed(ProvisionServer::class, fn ($job) => $job->serverId === $server->id);
});

test('callback with invalid signature returns 403', function () {
    Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'ssh_setup_token' => 'test-token',
        'provision_status' => 'pending',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $validUrl = URL::signedRoute('ssh-setup.callback', ['token' => 'test-token'], now()->addHours(24));

    $invalidUrl = str_replace('signature=', 'signature=invalid', $validUrl);

    $response = $this->get($invalidUrl);

    $response->assertStatus(403);
});

test('callback with already used token returns 410', function () {
    Queue::fake();

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'ssh_setup_token' => 'test-token',
        'provision_status' => 'pending',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $signedUrl = URL::signedRoute(
        'ssh-setup.callback',
        ['token' => 'test-token'],
        now()->addHours(24)
    );

    $this->get($signedUrl)->assertStatus(200);

    $secondResponse = $this->get($signedUrl);

    $secondResponse->assertStatus(410)
        ->assertContent('Token already used or expired');
});

test('callback does not dispatch job when token already used', function () {
    Queue::fake();

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'ssh_setup_token' => 'test-token',
        'provision_status' => 'pending',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $signedUrl = URL::signedRoute(
        'ssh-setup.callback',
        ['token' => 'test-token'],
        now()->addHours(24)
    );

    $this->get($signedUrl)->assertStatus(200);

    Queue::assertPushed(ProvisionServer::class, 1);

    $this->get($signedUrl)->assertStatus(410);

    Queue::assertPushed(ProvisionServer::class, 1);
});

test('show does not invalidate token on script request', function () {
    Process::fake();

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'ssh_setup_token' => 'persistent-token',
        'provision_status' => 'pending',
        'mysql_root_password' => 'test-mysql-password-123',
        'deploy_user_password' => 'test-deploy-password-123',
    ]);

    $this->get(route('ssh-setup.show', 'persistent-token'))->assertStatus(200);

    expect($server->fresh()->ssh_setup_token)->toBe('persistent-token');

    $signedUrl = URL::signedRoute(
        'ssh-setup.callback',
        ['token' => 'persistent-token'],
        now()->addHours(24)
    );

    $this->get($signedUrl)->assertStatus(200);

    expect($server->fresh()->ssh_setup_token)->toBeNull();
});
