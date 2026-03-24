<?php

namespace App\Jobs;

use App\Models\Site;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class UninstallSiteNightwatch implements ShouldQueue
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
            return;
        }

        $task = Task::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'ssh_user' => 'root',
            'script' => implode("\n", [
                'supervisorctl stop site-'.$site->id.'-nightwatch:*',
                'rm -f '.$site->nightwatchSupervisorConfigPath(),
                'supervisorctl reread',
                'supervisorctl update',
            ]),
            'timeout' => 60,
        ]);

        $task->run();

        $site->update(['nightwatch_enabled' => false]);
    }

    public function failed(?Throwable $e): void {}
}
