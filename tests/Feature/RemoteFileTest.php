<?php

use App\Helpers\RemoteFile;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->user = User::factory()->withSshKeys()->create();
    $this->server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provisioned_at' => now(),
    ]);
    $this->site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);
});

test('read returns empty string when user has no ssh key', function () {
    $userWithoutKey = User::factory()->create();
    $serverWithoutKey = Server::create([
        'user_id' => $userWithoutKey->id,
        'name' => 'Server Without Key',
        'ip_address' => '10.0.0.1',
        'ram_mb' => 1024,
        'sites_user' => 'deploy',
        'provisioned_at' => now(),
    ]);
    $siteWithoutKey = Site::create([
        'server_id' => $serverWithoutKey->id,
        'hostname' => 'nokey.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $content = RemoteFile::read($siteWithoutKey, '/home/deploy/nokey.com/.env');

    expect($content)->toBe('');
});

test('read returns file content via ssh', function () {
    Process::fake([
        'ssh *' => Process::result(output: 'APP_ENV=production
APP_KEY=base64:test-key
DB_HOST=localhost'),
    ]);

    $content = RemoteFile::read($this->site, '/home/deploy/example.com/.env');

    expect($content)
        ->toContain('APP_ENV=production')
        ->toContain('APP_KEY=base64:test-key')
        ->toContain('DB_HOST=localhost');
});

test('read returns empty string on ssh failure', function () {
    Process::fake([
        'ssh *' => Process::result(
            exitCode: 1,
            output: 'Connection refused
',
        ),
    ]);

    $content = RemoteFile::read($this->site, '/home/deploy/example.com/.env');

    expect($content)->toContain('Connection refused');
});

test('write returns false when user has no ssh key', function () {
    $userWithoutKey = User::factory()->create();
    $serverWithoutKey = Server::create([
        'user_id' => $userWithoutKey->id,
        'name' => 'Server Without Key',
        'ip_address' => '10.0.0.1',
        'ram_mb' => 1024,
        'sites_user' => 'deploy',
        'provisioned_at' => now(),
    ]);
    $siteWithoutKey = Site::create([
        'server_id' => $serverWithoutKey->id,
        'hostname' => 'nokey.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $result = RemoteFile::write($siteWithoutKey, '/home/deploy/nokey.com/.env', 'APP_ENV=production');

    expect($result)->toBeFalse();
});

test('write returns true on successful upload', function () {
    Process::fake([
        'scp *' => Process::result(exitCode: 0),
    ]);

    $result = RemoteFile::write($this->site, '/home/deploy/example.com/.env', 'APP_ENV=production');

    expect($result)->toBeTrue();
});

test('write returns false on upload failure', function () {
    Process::fake([
        'scp *' => Process::result(
            exitCode: 1,
            errorOutput: 'Permission denied'
        ),
    ]);

    $result = RemoteFile::write($this->site, '/home/deploy/example.com/.env', 'APP_ENV=production');

    expect($result)->toBeFalse();
});
