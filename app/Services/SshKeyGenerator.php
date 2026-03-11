<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class SshKeyGenerator
{
    public function generate(string $comment, string $passphrase = ''): array
    {
        $filename = Str::random(40);
        $keyPath = storage_path('app/'.$filename);

        $command = sprintf(
            'ssh-keygen -C %s -f %s -t ed25519 -N %s',
            escapeshellarg($comment),
            escapeshellarg($keyPath),
            escapeshellarg($passphrase)
        );

        $result = Process::path(storage_path('app'))->run($command);

        if (! $result->successful()) {
            throw new RuntimeException('Failed to generate SSH key: '.$result->errorOutput());
        }

        $publicKey = trim(file_get_contents($keyPath.'.pub'));
        $privateKey = trim(file_get_contents($keyPath));

        @unlink($keyPath.'.pub');
        @unlink($keyPath);

        return [
            'public' => $publicKey,
            'private' => $privateKey,
        ];
    }
}
