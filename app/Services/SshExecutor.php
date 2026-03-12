<?php

namespace App\Services;

use App\SecureShellCommand;
use App\ShellResponse;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;

class SshExecutor
{
    public static function testConnection(string $ip, string $user, string $privateKey, int $timeout = 10): ShellResponse
    {
        $keyPath = self::writeKeyFile($privateKey);

        try {
            $command = SecureShellCommand::forScript(
                $ip,
                $keyPath,
                $user,
                "'echo connected'"
            );

            $result = Process::timeout($timeout)->run($command);

            return new ShellResponse(
                exitCode: $result->exitCode(),
                output: $result->output(),
                timedOut: false
            );
        } catch (ProcessTimedOutException $e) {
            return new ShellResponse(
                exitCode: 124,
                output: $e->result->output(),
                timedOut: true
            );
        } finally {
            @unlink($keyPath);
        }
    }

    protected static function writeKeyFile(string $privateKey): string
    {
        $path = storage_path('app/keys/'.uniqid());

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }

        file_put_contents($path, rtrim($privateKey).PHP_EOL);
        chmod($path, 0600);

        return $path;
    }
}
