<?php

namespace App\Traits;

use App\SecureShellCommand;
use App\ShellResponse;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

trait InteractsWithSsh
{
    public function run(): self
    {
        $this->markAsRunning();

        $this->ensureWorkingDirectoryExists();

        try {
            $this->upload();
        } catch (ProcessTimedOutException $e) {
            return $this->markAsTimedOut();
        }

        $response = $this->executeScript();

        return $this->updateForResponse($response);
    }

    protected function path(): string
    {
        return $this->ssh_user === 'root'
            ? '/root/.remote-tasks'
            : "/home/{$this->ssh_user}/.remote-tasks";
    }

    protected function scriptFile(): string
    {
        return $this->path().'/'.$this->id.'.sh';
    }

    protected function outputFile(): string
    {
        return $this->path().'/'.$this->id.'.out';
    }

    protected function ensureWorkingDirectoryExists(): void
    {
        $this->runInline('mkdir -p '.$this->path(), 10);
    }

    protected function upload(): bool
    {
        $localScript = $this->writeLocalScript();
        $keyPath = $this->writeKeyFile();

        try {
            $command = SecureShellCommand::forUpload(
                $this->server->ip_address,
                $keyPath,
                $this->ssh_user,
                $localScript,
                $this->scriptFile()
            );

            $result = Process::timeout(15)->run($command);

            return $result->successful();
        } finally {
            @unlink($localScript);
            @unlink($keyPath);
        }
    }

    protected function executeScript(): ShellResponse
    {
        $keyPath = $this->writeKeyFile();

        try {
            $script = sprintf(
                'set -o pipefail; bash %s 2>&1 | tee %s',
                $this->scriptFile(),
                $this->outputFile()
            );

            $token = Str::random(20);

            $command = SecureShellCommand::forScript(
                $this->server->ip_address,
                $keyPath,
                $this->ssh_user,
                "'bash -s ' << '{$token}'\n{$script}\n{$token}"
            );

            $result = Process::timeout($this->timeout)->run($command);

            return new ShellResponse(
                exitCode: $result->exitCode(),
                output: $result->output(),
                timedOut: false
            );
        } catch (ProcessTimedOutException $e) {
            return new ShellResponse(
                exitCode: 124,
                output: $e->result->output(),
                timedOut: true
            );
        } finally {
            @unlink($keyPath);
        }
    }

    protected function writeLocalScript(): string
    {
        $hash = md5(uniqid().$this->script);
        $path = storage_path('app/scripts/'.$hash);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $this->script);

        return $path;
    }

    protected function runInline(string $script, int $timeout = 60): ShellResponse
    {
        $keyPath = $this->writeKeyFile();

        try {
            $token = Str::random(20);

            $command = SecureShellCommand::forScript(
                $this->server->ip_address,
                $keyPath,
                $this->ssh_user,
                "'bash -s ' << '{$token}'\n{$script}\n{$token}"
            );

            $result = Process::timeout($timeout)->run($command);

            return new ShellResponse(
                exitCode: $result->exitCode(),
                output: $result->output(),
                timedOut: false
            );
        } catch (ProcessTimedOutException $e) {
            return new ShellResponse(
                exitCode: 124,
                output: '',
                timedOut: true
            );
        } finally {
            @unlink($keyPath);
        }
    }

    protected function updateForResponse(ShellResponse $response): self
    {
        return tap($this)->update([
            'status' => $response->timedOut ? 'timeout' : 'finished',
            'exit_code' => $response->exitCode,
            'output' => $response->output,
            'finished_at' => now(),
        ]);
    }

    abstract protected function writeKeyFile(): string;
}
