<?php

namespace App\Callbacks;

use App\Models\Server;
use App\Models\Task;

class MarkServerProvisioned
{
    public function __construct(
        public int $serverId
    ) {}

    public function handle(Task $task): void
    {
        $server = Server::findOrFail($this->serverId);

        if ($task->successful()) {
            $server->update([
                'provision_status' => 'provisioned',
                'provisioned_at' => now(),
                'provision_task_id' => null,
            ]);
        } else {
            $server->update([
                'provision_status' => 'failed',
                'provision_task_id' => null,
            ]);
        }
    }
}
