<?php

use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

test('user registration creates SSH keys on user', function () {
    $privateKeyContent = <<<'EOT'
        -----BEGIN OPENSSH PRIVATE KEY-----
        test-private-key-content
        -----END OPENSSH PRIVATE KEY-----
        EOT;

    $publicKeyContent = 'ssh-ed25519 test-public-key-content user@localhost';

    Process::fake(['ssh-keygen *' => Process::result(exitCode: 0)]);

    file_put_contents(storage_path('app/test-key'), $privateKeyContent);
    file_put_contents(storage_path('app/test-key.pub'), $publicKeyContent);

    Str::createRandomStringsUsing(fn () => 'test-key');

    $this->post(route('register.store'), [
        'email' => 'john@example.com',
    ])->assertRedirect(route('dashboard'));

    $user = User::where('email', 'john@example.com')->first();
    expect($user->ssh_public_key)->toBe($publicKeyContent)
        ->and($user->ssh_private_key)->toBe($privateKeyContent);

    Str::createRandomStringsNormally();
});

test('user registration creates user with SSH keys', function () {
    $this->post(route('register.store'), [
        'email' => 'john@example.com',
    ])->assertRedirect(route('dashboard'));

    $user = User::where('email', 'john@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->ssh_public_key)->not->toBeNull()
        ->and($user->ssh_private_key)->not->toBeNull();
});

test('registration fails if ssh key generation fails', function () {
    Process::fake([
        'ssh-keygen *' => Process::result(exitCode: 1, errorOutput: 'Key generation failed'),
    ]);

    $this->post(route('register.store'), [
        'email' => 'john@example.com',
    ])->assertStatus(500);

    expect(User::where('email', 'john@example.com')->exists())->toBeFalse();
});

test('user has many servers relationship', function () {
    $user = User::factory()->create();

    Server::create([
        'user_id' => $user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
    ]);

    expect($user->servers)->toHaveCount(1)
        ->and($user->servers->first()->name)->toBe('Test Server');
});

test('deleting user cascades to servers', function () {
    $user = User::factory()->create();

    $server = Server::create([
        'user_id' => $user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
    ]);

    $serverId = $server->id;

    $user->delete();

    expect(Server::find($serverId))->toBeNull();
});

test('user registration with remember field authenticates user', function () {
    $this->post(route('register.store'), [
        'email' => 'remember@example.com',
    ])->assertRedirect(route('dashboard'));

    $user = User::where('email', 'remember@example.com')->first();

    expect($user)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});
