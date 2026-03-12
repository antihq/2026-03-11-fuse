<?php

namespace App\Jobs;

use App\Models\Site;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class DeploySite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 300;

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

        if (empty($site->repository_url)) {
            $site->update(['status' => 'failed']);

            return;
        }

        $site->update(['status' => 'deploying']);

        $task = Task::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'ssh_user' => $server->sites_user,
            'script' => view('scripts.deploy-site', [
                'hostname' => $site->hostname,
                'sitesUser' => $server->sites_user,
                'repositoryUrl' => $site->repository_url,
                'repositoryBranch' => $site->repository_branch,
                'phpVersion' => $site->php_version,
            ])->render(),
            'timeout' => 240,
        ]);

        $task->run();

        if ($task->successful()) {
            $site->update([
                'status' => 'active',
                'deployed_at' => now(),
            ]);
        } else {
            $site->update(['status' => 'failed']);
        }
    }

    public function failed(?Throwable $e): void
    {
        Site::where('id', $this->siteId)->update(['status' => 'failed']);
    }
}
