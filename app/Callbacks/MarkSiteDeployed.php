<?php

namespace App\Callbacks;

use App\Models\Site;
use App\Models\Task;

class MarkSiteDeployed
{
    public function __construct(
        public int $siteId
    ) {}

    public function handle(Task $task): void
    {
        $site = Site::find($this->siteId);

        if (! $site) {
            return;
        }

        $task->successful()
            ? $site->update(['status' => 'active', 'deployed_at' => now()])
            : $site->update(['status' => 'failed']);
    }
}
