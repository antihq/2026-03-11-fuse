<?php

use App\Services\SshKeyGenerator;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

beforeEach(function () {
    Str::createRandomStringsUsing(fn () => 'test-key-filename');
});

afterEach(function () {
    Str::createRandomStringsNormally();
});

test('generates ssh key without passphrase', function () {
    $privateKeyContent = <<<'EOT'
        -----BEGIN OPENSSH PRIVATE KEY-----
        test-private-key-content
        -----END OPENSSH PRIVATE KEY-----
        EOT;

    $publicKeyContent = 'ssh-ed25519 test-public-key-content test@example.com';

    $keyPath = storage_path('app/test-key-filename');
    file_put_contents($keyPath, $privateKeyContent);
    file_put_contents($keyPath.'.pub', $publicKeyContent);

    Process::fake(['ssh-keygen *' => Process::result(exitCode: 0)]);

    $generator = new SshKeyGenerator;
    $result = $generator->generate('test@example.com');

    Process::assertRan(function (PendingProcess $process, ProcessResult $processResult) {
        return str_contains($process->command, "-C 'test@example.com'")
            && str_contains($process->command, '-t ed25519')
            && str_contains($process->command, "-N ''");
    });

    expect($result)
        ->toBeArray()
        ->toHaveKeys(['public', 'private'])
        ->and($result['public'])->toBe($publicKeyContent)
        ->and($result['private'])->toBe($privateKeyContent);
});

test('generates ssh key with passphrase', function () {
    $privateKeyContent = <<<'EOT'
        -----BEGIN OPENSSH PRIVATE KEY-----
        test-private-key-content-with-passphrase
        -----END OPENSSH PRIVATE KEY-----
        EOT;

    $publicKeyContent = 'ssh-ed25519 test-public-key-content-with-passphrase test@example.com';

    $keyPath = storage_path('app/test-key-filename');
    file_put_contents($keyPath, $privateKeyContent);
    file_put_contents($keyPath.'.pub', $publicKeyContent);

    Process::fake(['ssh-keygen *' => Process::result(exitCode: 0)]);

    $generator = new SshKeyGenerator;
    $result = $generator->generate('test@example.com', 'secret123');

    Process::assertRan(function (PendingProcess $process, ProcessResult $processResult) {
        return str_contains($process->command, "-N 'secret123'");
    });

    expect($result)
        ->toBeArray()
        ->toHaveKeys(['public', 'private'])
        ->and($result['public'])->toBe($publicKeyContent)
        ->and($result['private'])->toBe($privateKeyContent);
});

test('throws exception on process failure', function () {
    Process::fake([
        'ssh-keygen *' => Process::result(
            exitCode: 1,
            errorOutput: 'Permission denied'
        ),
    ]);

    $generator = new SshKeyGenerator;

    expect(fn () => $generator->generate('test@example.com'))
        ->toThrow(RuntimeException::class, 'Failed to generate SSH key: Permission denied');
});

test('cleans up temporary key files', function () {
    $privateKeyContent = <<<'EOT'
        -----BEGIN OPENSSH PRIVATE KEY-----
        test-private-key-content
        -----END OPENSSH PRIVATE KEY-----
        EOT;

    $publicKeyContent = 'ssh-ed25519 test-public-key-content test@example.com';

    $keyPath = storage_path('app/test-key-filename');
    file_put_contents($keyPath, $privateKeyContent);
    file_put_contents($keyPath.'.pub', $publicKeyContent);

    Process::fake(['ssh-keygen *' => Process::result(exitCode: 0)]);

    $generator = new SshKeyGenerator;
    $generator->generate('test@example.com');

    expect(file_exists($keyPath))->toBeFalse()
        ->and(file_exists($keyPath.'.pub'))->toBeFalse();
});
