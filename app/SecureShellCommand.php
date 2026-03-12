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
}
