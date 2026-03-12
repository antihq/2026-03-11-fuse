<?php

namespace App;

class SecureShellCommand
{
    public static function forScript(string $ip, string $keyPath, string $user, string $script): string
    {
        return implode(' ', [
            'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no',
            '-i '.$keyPath,
            '-p 22',
            $user.'@'.$ip,
            $script,
        ]);
    }

    public static function forUpload(string $ip, string $keyPath, string $user, string $from, string $to): string
    {
        return sprintf(
            'scp -i %s -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -P 22 %s %s@%s:%s',
            $keyPath,
            $from,
            $user,
            $ip,
            $to
        );
    }
}
