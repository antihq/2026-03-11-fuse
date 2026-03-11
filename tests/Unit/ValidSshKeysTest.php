<?php

use App\Rules\ValidSshKeys;

test('passes for empty value', function () {
    $rule = new ValidSshKeys;

    $fail = fn ($message) => throw new Exception($message);

    $rule->validate('authorized_keys', '', $fail);
    $rule->validate('authorized_keys', null, $fail);

    expect(true)->toBeTrue();
});

test('passes for valid ssh-rsa key', function () {
    $rule = new ValidSshKeys;

    $fail = fn ($message) => throw new Exception($message);

    $rule->validate('authorized_keys', 'ssh-rsa AAAAB3NzaC1yc2E user@example.com', $fail);

    expect(true)->toBeTrue();
});

test('passes for valid ssh-ed25519 key', function () {
    $rule = new ValidSshKeys;

    $fail = fn ($message) => throw new Exception($message);

    $rule->validate('authorized_keys', 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5 user@example.com', $fail);

    expect(true)->toBeTrue();
});

test('passes for valid ecdsa keys', function () {
    $rule = new ValidSshKeys;

    $fail = fn ($message) => throw new Exception($message);

    $rule->validate('authorized_keys', 'ecdsa-sha2-nistp256 AAAAE2VjZHNh user@ecdsa256', $fail);
    $rule->validate('authorized_keys', 'ecdsa-sha2-nistp384 AAAAE2VjZHNh user@ecdsa384', $fail);
    $rule->validate('authorized_keys', 'ecdsa-sha2-nistp521 AAAAE2VjZHNh user@ecdsa521', $fail);

    expect(true)->toBeTrue();
});

test('passes for multiple valid keys', function () {
    $rule = new ValidSshKeys;

    $fail = fn ($message) => throw new Exception($message);

    $keys = "ssh-rsa AAAAB3NzaC1yc2E user@example.com\nssh-ed25519 AAAAC3NzaC1lZDI1NTE5 user2@example.com";

    $rule->validate('authorized_keys', $keys, $fail);

    expect(true)->toBeTrue();
});

test('fails for invalid key format', function () {
    $rule = new ValidSshKeys;

    $failed = false;
    $fail = function ($message) use (&$failed) {
        $failed = true;
    };

    $rule->validate('authorized_keys', 'invalid-key-format', $fail);

    expect($failed)->toBeTrue();
});

test('fails when one key in multiple keys is invalid', function () {
    $rule = new ValidSshKeys;

    $failed = false;
    $fail = function ($message) use (&$failed) {
        $failed = true;
    };

    $keys = "ssh-rsa AAAAB3NzaC1yc2E user@example.com\ninvalid-key";

    $rule->validate('authorized_keys', $keys, $fail);

    expect($failed)->toBeTrue();
});

test('ignores empty lines', function () {
    $rule = new ValidSshKeys;

    $fail = fn ($message) => throw new Exception($message);

    $keys = "ssh-rsa AAAAB3NzaC1yc2E user@example.com\n\n\nssh-ed25519 AAAAC3NzaC1lZDI1NTE5 user2@example.com";

    $rule->validate('authorized_keys', $keys, $fail);

    expect(true)->toBeTrue();
});
