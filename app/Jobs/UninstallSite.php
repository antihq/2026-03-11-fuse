<?php

namespace App\Jobs;

use App\Models\Site;
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
        public int $siteId
    ) {}

    public function handle(): void
    {
        $site = Site::with('server.user')->findOrFail($this->siteId);
        $server = $site->server;
        $user = $server->user;

        if (empty($user->ssh_private_key)) {
            return;
        }

        $task = Task::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'ssh_user' => 'root',
            'script' => view('scripts.uninstall-site', [
                'hostname' => $site->hostname,
                'sitesUser' => $server->sites_user,
                'databaseName' => $site->database_name,
                'databaseUser' => $site->database_user,
                'mysqlRootPassword' => $server->mysql_root_password,
            ])->render(),
            'timeout' => 30,
        ]);

        $task->run();

        if ($task->successful()) {
            $site->delete();
        }
    }

    public function failed(?Throwable $e): void
    {
        // Site not deleted on failure, can be retried
    }
}
