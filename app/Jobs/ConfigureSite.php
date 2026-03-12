<?php

namespace App\Jobs;

use App\Models\Site;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class ConfigureSite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 120;

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

        $site->update(['status' => 'configuring']);

        $siteTask = Task::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'ssh_user' => $server->sites_user,
            'script' => view('scripts.site-caddyfile', [
                'hostname' => $site->hostname,
                'phpVersion' => $site->php_version,
                'sitesUser' => $server->sites_user,
            ])->render(),
            'timeout' => 60,
        ]);

        $siteTask->run();

        if (! $siteTask->successful()) {
            $site->update(['status' => 'failed']);

            return;
        }

        $importsTask = Task::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'ssh_user' => 'root',
            'script' => view('scripts.update-caddy-imports', [
                'hostname' => $site->hostname,
                'sitesUser' => $server->sites_user,
            ])->render(),
            'timeout' => 30,
        ]);

        $importsTask->run();

        if ($importsTask->successful()) {
            $site->update([
                'status' => 'active',
                'configured_at' => now(),
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
