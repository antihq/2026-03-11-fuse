<?php

use App\Services\SshExecutor;
use Illuminate\Support\Facades\Process;

it('returns successful response when connection succeeds', function () {
    Process::fake([
        '*' => Process::result(exitCode: 0, output: 'connected'),
    ]);

    $response = SshExecutor::testConnection(
        '192.168.1.1',
        'root',
        'fake-private-key'
    );

    expect($response)
        ->exitCode->toBe(0)
        ->output->toContain('connected')
        ->timedOut->toBeFalse();
});

it('returns failure response when connection fails', function () {
    Process::fake([
        '*' => Process::result(exitCode: 255, output: 'Permission denied'),
    ]);

    $response = SshExecutor::testConnection(
        '192.168.1.1',
        'root',
        'fake-private-key'
    );

    expect($response)
        ->exitCode->toBe(255)
        ->output->toContain('Permission denied')
        ->timedOut->toBeFalse();
});

it('creates key file in storage app keys directory', function () {
    Process::fake([
        '*' => Process::result(exitCode: 0, output: 'connected'),
    ]);

    SshExecutor::testConnection(
        '192.168.1.1',
        'root',
        'fake-private-key'
    );

    $keysDir = storage_path('app/keys');
    expect(is_dir($keysDir))->toBeTrue('Keys directory should exist');
});
