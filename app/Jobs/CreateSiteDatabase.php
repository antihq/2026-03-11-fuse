<?php

namespace App\Jobs;

use App\Models\Site;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Throwable;

class CreateSiteDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

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
            $site->update(['status' => 'failed']);

            return;
        }

        $databaseName = $this->generateDatabaseName($site->hostname);
        $databaseUser = $databaseName;
        $databasePassword = Str::password(32, true, true, false, false);

        $site->update([
            'database_name' => $databaseName,
            'database_user' => $databaseUser,
            'database_password' => $databasePassword,
        ]);

        $task = Task::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'ssh_user' => 'root',
            'script' => view('scripts.create-site-database', [
                'databaseName' => $databaseName,
                'databaseUser' => $databaseUser,
                'databasePassword' => $databasePassword,
                'mysqlRootPassword' => $server->mysql_root_password,
            ])->render(),
            'timeout' => 60,
        ]);

        $task->run();

        if ($task->successful()) {
            $site->update([
                'database_created_at' => now(),
                'status' => 'ready',
            ]);
        } else {
            $site->update(['status' => 'failed']);
        }
    }

    public function failed(?Throwable $e): void
    {
        Site::where('id', $this->siteId)->update(['status' => 'failed']);
    }

    private function generateDatabaseName(string $hostname): string
    {
        return str_replace(['.', '-'], '_', $hostname);
    }
}
