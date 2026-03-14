<?php

namespace App\Jobs;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Process;

class RetrieveServerPublicKey implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public int $serverId
    ) {}

    public function handle(): void
    {
        $server = Server::with('user')->findOrFail($this->serverId);
        $user = $server->user;

        if (empty($user->ssh_private_key)) {
            return;
        }

        $keyPath = $this->writeKeyFile($user->ssh_private_key);

        try {
            $command = sprintf(
                'ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=10 %s@%s "cat /home/%s/.ssh/id_rsa.pub 2>/dev/null || echo \'\'"',
                escapeshellarg($keyPath),
                escapeshellarg($server->sites_user),
                escapeshellarg($server->ip_address),
                escapeshellarg($server->sites_user)
            );

            $result = Process::timeout(15)->run($command);
            $publicKey = trim($result->output());

            $server->update([
                'server_public_key' => $publicKey ?: null,
            ]);
        } finally {
            @unlink($keyPath);
        }
    }

    protected function writeKeyFile(string $key): string
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
