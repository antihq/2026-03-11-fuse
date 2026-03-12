<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class UninstallSite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 60;

    public function __construct(
        public int $serverId,
        public string $hostname,
    ) {}

    public function handle(): void
    {
        $server = Server::with('user')->findOrFail($this->serverId);
        $user = $server->user;

        if (empty($user->ssh_private_key)) {
            return;
        }

        $task = Task::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'ssh_user' => 'root',
            'script' => view('scripts.uninstall-site', [
                'hostname' => $this->hostname,
                'sitesUser' => $server->sites_user,
            ])->render(),
            'timeout' => 30,
        ]);

        $task->run();
    }

    public function failed(?Throwable $e): void
    {
        // Site already deleted from database, nothing to update
    }
}
