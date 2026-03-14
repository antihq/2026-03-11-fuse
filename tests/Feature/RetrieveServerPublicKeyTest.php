<?php

use App\Jobs\RetrieveServerPublicKey;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->user = User::factory()->withSshKeys()->create();
    $this->server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
    ]);
});

test('handle retrieves and stores public key on success', function () {
    Process::fake([
        '*' => Process::result('ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI test@server'),
    ]);

    $job = new RetrieveServerPublicKey($this->server->id);
    $job->handle();

    expect($this->server->fresh()->server_public_key)
        ->toBe('ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI test@server');
});

test('handle sets server_public_key to null on empty response', function () {
    Process::fake([
        '*' => Process::result(''),
    ]);

    $this->server->update(['server_public_key' => 'old-key']);
    $job = new RetrieveServerPublicKey($this->server->id);
    $job->handle();

    expect($this->server->fresh()->server_public_key)->toBeNull();
});

test('handle returns early when user has no ssh key', function () {
    Process::fake();

    $user = User::factory()->create();
    $server = Server::create([
        'user_id' => $user->id,
        'name' => 'No Key Server',
        'ip_address' => '192.168.1.2',
        'ram_mb' => 2048,
    ]);

    $job = new RetrieveServerPublicKey($server->id);
    $job->handle();

    Process::assertNothingRan();
});

test('handle throws exception when server not found', function () {
    $job = new RetrieveServerPublicKey(99999);
    $job->handle();
})->throws(ModelNotFoundException::class);

test('handle cleans up temporary key file', function () {
    Process::fake();

    $job = new RetrieveServerPublicKey($this->server->id);
    $job->handle();

    $keyFiles = glob(storage_path('app/keys/*'));
    expect($keyFiles)->toBeEmpty();
});
