<?php

namespace App\Helpers;

use App\Models\Site;
use Illuminate\Support\Facades\Process;

class RemoteFile
{
    public static function read(Site $site, string $path): string
    {
        $user = $site->server->user;
        $server = $site->server;

        if (empty($user->ssh_private_key)) {
            return '';
        }

        $keyPath = self::writeKeyFile($user->ssh_private_key);

        try {
            $command = sprintf(
                'ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null %s@%s "cat %s 2>/dev/null || echo \'\'"',
                escapeshellarg($keyPath),
                escapeshellarg($server->sites_user),
                escapeshellarg($server->ip_address),
                escapeshellarg($path)
            );

            $result = Process::timeout(30)->run($command);

            return $result->output();
        } catch (\Exception $e) {
            return '';
        } finally {
            @unlink($keyPath);
        }
    }

    public static function write(Site $site, string $path, string $content): bool
    {
        $user = $site->server->user;
        $server = $site->server;

        if (empty($user->ssh_private_key)) {
            return false;
        }

        $keyPath = self::writeKeyFile($user->ssh_private_key);
        $tempPath = storage_path('app/temp/'.uniqid('.env'));

        try {
            if (! is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            file_put_contents($tempPath, $content);
            chmod($tempPath, 0644);

            $command = sprintf(
                'scp -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null %s %s@%s:%s',
                escapeshellarg($keyPath),
                escapeshellarg($tempPath),
                escapeshellarg($server->sites_user),
                escapeshellarg($server->ip_address),
                escapeshellarg($path)
            );

            $result = Process::timeout(30)->run($command);

            return $result->successful();
        } catch (\Exception $e) {
            return false;
        } finally {
            @unlink($keyPath);
            @unlink($tempPath);
        }
    }

    private static function writeKeyFile(string $key): string
    {
        $path = storage_path('app/keys/'.uniqid());

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }

        file_put_contents($path, rtrim($key).PHP_EOL);
        chmod($path, 0600);

        return $path;
    }
}
