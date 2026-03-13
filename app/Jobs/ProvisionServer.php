<?php

namespace App\Jobs;

use App\Callbacks\MarkServerProvisioned;
use App\Models\Server;
use App\Models\Task;
use App\Services\ProvisioningScriptGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionServer implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 60;

    public function __construct(
        public int $serverId
    ) {}

    public function handle(): void
    {
        $server = Server::with('user')->findOrFail($this->serverId);

        if (empty($server->user->ssh_private_key)) {
            $server->update(['provision_status' => 'failed']);

            return;
        }

        $server->update(['provision_status' => 'provisioning']);

        $rootSshKey = $server->user->ssh_public_key ?? '';

        $generator = new ProvisioningScriptGenerator($server, $rootSshKey);
        $script = $generator->generate();

        $task = Task::create([
            'user_id' => $server->user_id,
            'server_id' => $server->id,
            'ssh_user' => 'root',
            'script' => $script,
            'timeout' => 1800,
            'options' => [
                'then' => [new MarkServerProvisioned($server->id)],
            ],
        ]);

        $server->update(['provision_task_id' => $task->id]);

        $task->runInBackground();
    }
}
