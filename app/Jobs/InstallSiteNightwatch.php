<?php

namespace App\Jobs;

use App\Models\Site;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class InstallSiteNightwatch implements ShouldQueue
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

        $repoPath = "/home/{$server->sites_user}/{$site->hostname}/repository";
        $logPath = $site->nightwatchLogPath();

        $task = Task::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'ssh_user' => 'root',
            'script' => view('scripts.site-nightwatch-supervisor', [
                'site' => $site,
                'sitesUser' => $server->sites_user,
                'repoPath' => $repoPath,
                'logPath' => $logPath,
            ])->render(),
            'timeout' => 60,
        ]);

        $task->run();

        if ($task->successful()) {
            $reloadTask = Task::create([
                'user_id' => $user->id,
                'server_id' => $server->id,
                'ssh_user' => 'root',
                'script' => implode("\n", [
                    'mkdir -p '.$logPath,
                    'chown '.$server->sites_user.':'.$server->sites_user.' '.$logPath,
                    'chmod 775 '.$logPath,
                    'touch '.$logPath.'/nightwatch.log '.$logPath.'/nightwatch-error.log',
                    'chown '.$server->sites_user.':'.$server->sites_user.' '.$logPath.'/nightwatch.log '.$logPath.'/nightwatch-error.log',
                    'supervisorctl reread',
                    'supervisorctl update',
                    'supervisorctl start site-'.$site->id.'-nightwatch:*',
                ]),
                'timeout' => 60,
            ]);

            $reloadTask->run();
        }
    }

    public function failed(?Throwable $e): void
    {
        Site::where('id', $this->siteId)->update([
            'nightwatch_enabled' => false,
        ]);
    }
}
