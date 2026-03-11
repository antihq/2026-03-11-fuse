<?php

use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('mysql root password is encrypted at rest', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'mysql_root_password' => 'plain-text-password',
        'deploy_user_password' => 'another-password',
    ]);

    $rawValue = DB::table('servers')->where('id', $server->id)->value('mysql_root_password');

    expect($rawValue)->not->toBe('plain-text-password')
        ->and($rawValue)->toStartWith('eyJ');
});

test('deploy user password is encrypted at rest', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'mysql_root_password' => 'mysql-password',
        'deploy_user_password' => 'plain-text-password',
    ]);

    $rawValue = DB::table('servers')->where('id', $server->id)->value('deploy_user_password');

    expect($rawValue)->not->toBe('plain-text-password')
        ->and($rawValue)->toStartWith('eyJ');
});

test('passwords are hidden from array serialization', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'mysql_root_password' => 'secret-mysql',
        'deploy_user_password' => 'secret-deploy',
    ]);

    $array = $server->toArray();

    expect($array)->not->toHaveKey('mysql_root_password')
        ->and($array)->not->toHaveKey('deploy_user_password');
});

test('passwords are hidden from json serialization', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'mysql_root_password' => 'secret-mysql',
        'deploy_user_password' => 'secret-deploy',
    ]);

    $json = $server->toJson();
    $decoded = json_decode($json, true);

    expect($decoded)->not->toHaveKey('mysql_root_password')
        ->and($decoded)->not->toHaveKey('deploy_user_password');
});

test('passwords can be decrypted and accessed on model', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'mysql_root_password' => 'my-mysql-password',
        'deploy_user_password' => 'my-deploy-password',
    ]);

    expect($server->mysql_root_password)->toBe('my-mysql-password')
        ->and($server->deploy_user_password)->toBe('my-deploy-password');
});
