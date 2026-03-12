#!/bin/bash
set -e

# Write user script to temp file
cat > {{ $tempScriptPath }} << '{{ $token }}'
{!! $task->script !!}

{{ $token }}

# Execute the user's script
set +e
@if ($task->timeout > 0)
timeout {{ $task->timeout }}s bash {{ $tempScriptPath }}
@else
bash {{ $tempScriptPath }}
@endif
EXIT_CODE=$?
set -e

# Callback to home server with exit code
curl -X POST "{!! $callbackUrl !!}" -d "exit_code=${EXIT_CODE}" > /dev/null 2>&1 &
