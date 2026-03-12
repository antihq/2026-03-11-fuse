<?php

use App\SecureShellCommand;

it('builds ssh command for script', function () {
    $command = SecureShellCommand::forScript(
        '192.168.1.1',
        '/path/to/key',
        'root',
        'ls -la'
    );

    expect($command)->toContain('ssh')
        ->toContain('root@192.168.1.1')
        ->toContain('ls -la');
});

it('includes ssh options', function () {
    $command = SecureShellCommand::forScript(
        '192.168.1.1',
        '/path/to/key',
        'root',
        'ls -la'
    );

    expect($command)->toContain('StrictHostKeyChecking=no')
        ->toContain('UserKnownHostsFile=/dev/null');
});

it('uses port 22', function () {
    $command = SecureShellCommand::forScript(
        '192.168.1.1',
        '/path/to/key',
        'root',
        'ls -la'
    );

    expect($command)->toContain('-p 22');
});

it('includes key path', function () {
    $command = SecureShellCommand::forScript(
        '192.168.1.1',
        '/home/user/.ssh/id_rsa',
        'root',
        'ls'
    );

    expect($command)->toContain('-i /home/user/.ssh/id_rsa');
});

it('interpolates user and ip', function () {
    $command = SecureShellCommand::forScript(
        '10.0.0.50',
        '/path/to/key',
        'ubuntu',
        'ls'
    );

    expect($command)->toContain('ubuntu@10.0.0.50');
});
