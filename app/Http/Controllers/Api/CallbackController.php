<?php

namespace App\Http\Controllers\Api;

use App\Jobs\FinishTask;
use App\Models\Task;
use Illuminate\Http\Request;

class CallbackController
{
    public function __invoke(Request $request, Task $task)
    {
        abort_unless($task->isRunning(), 404);

        $exitCode = (int) $request->input('exit_code', 1);

        FinishTask::dispatch($task, $exitCode);

        return response()->noContent();
    }
}
