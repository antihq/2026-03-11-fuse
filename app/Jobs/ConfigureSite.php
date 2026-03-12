<?php

namespace App\Jobs;

use App\Models\Site;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Blade;
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

        $script = $this->generateScript($site, $server);

        $task = Task::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'ssh_user' => $server->sites_user,
            'script' => $script,
            'timeout' => 60,
        ]);

        $task->run();

        if ($task->successful()) {
            $site->update([
                'status' => 'active',
                'configured_at' => now(),
            ]);
        } else {
            $site->update(['status' => 'failed']);
        }
    }

    protected function generateScript(Site $site, $server): string
    {
        $template = file_get_contents(resource_path('views/scripts/site-caddyfile.blade.php'));

        return Blade::render($template, [
            'hostname' => $site->hostname,
            'phpVersion' => $site->php_version,
            'sitesUser' => $server->sites_user,
        ]);
    }

    public function failed(?Throwable $e): void
    {
        Site::where('id', $this->siteId)->update(['status' => 'failed']);
    }
}
