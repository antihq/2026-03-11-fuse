<?php

use App\Models\Server;
use App\Models\Site;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'provisioned_at' => now(),
    ]);
});

test('site belongs to server', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    expect($site->server->id)->toBe($this->server->id);
});

test('server has many sites', function () {
    Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'site1.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo1.git',
        'repository_branch' => 'main',
    ]);

    Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'site2.com',
        'php_version' => '8.3',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo2.git',
        'repository_branch' => 'develop',
    ]);

    expect($this->server->fresh()->sites)->toHaveCount(2);
});

test('sites are deleted when server is deleted', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $this->server->delete();

    expect(Site::find($site->id))->toBeNull();
});

test('site defaults status to pending', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'pending.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $site->refresh();

    expect($site->status)->toBe('pending');
});
