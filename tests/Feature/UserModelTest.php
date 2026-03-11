<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('ssh private key is encrypted at rest', function () {
    $user = User::factory()->withSshKeys()->create();

    $rawValue = DB::table('users')->where('id', $user->id)->value('ssh_private_key');

    expect($rawValue)->not->toBe($user->ssh_private_key)
        ->and($rawValue)->toStartWith('eyJ');
});

test('ssh private key is hidden from array serialization', function () {
    $user = User::factory()->withSshKeys()->create();

    $array = $user->toArray();

    expect($array)->not->toHaveKey('ssh_private_key');
});

test('ssh public key is visible in array serialization', function () {
    $user = User::factory()->withSshKeys()->create();

    $array = $user->toArray();

    expect($array)->toHaveKey('ssh_public_key')
        ->and($array['ssh_public_key'])->toBe($user->ssh_public_key);
});

test('ssh private key is hidden from json serialization', function () {
    $user = User::factory()->withSshKeys()->create();

    $json = $user->toJson();
    $decoded = json_decode($json, true);

    expect($decoded)->not->toHaveKey('ssh_private_key');
});

test('ssh public key is visible in json serialization', function () {
    $user = User::factory()->withSshKeys()->create();

    $json = $user->toJson();
    $decoded = json_decode($json, true);

    expect($decoded)->toHaveKey('ssh_public_key')
        ->and($decoded['ssh_public_key'])->toBe($user->ssh_public_key);
});
