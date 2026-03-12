<?php

namespace App\Models;

use App\Traits\InteractsWithSsh;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Process;

class Task extends Model
{
    use HasFactory, InteractsWithSsh;

    protected $fillable = [
        'user_id',
        'server_id',
        'ssh_user',
        'script',
        'status',
        'exit_code',
        'output',
        'timeout',
        'options',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected function options(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? unserialize($value) : [],
            set: fn (array $value) => serialize($value),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function markAsRunning(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function markAsTimedOut(): self
    {
        return tap($this)->update([
            'status' => 'timeout',
            'finished_at' => now(),
        ]);
    }

    public function successful(): bool
    {
        return $this->exit_code === 0;
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function finish(int $exitCode = 0): void
    {
        $this->markAsFinished($exitCode);

        $this->update([
            'output' => $this->retrieveOutput(),
        ]);

        foreach ($this->options['then'] ?? [] as $callback) {
            is_object($callback)
                ? $callback->handle($this)
                : app($callback)->handle($this);
        }
    }

    public function markAsFinished(int $exitCode = 0): self
    {
        return tap($this)->update([
            'status' => 'finished',
            'exit_code' => $exitCode,
            'finished_at' => now(),
        ]);
    }

    public function retrieveOutput(): string
    {
        $keyPath = $this->writeKeyFile();

        try {
            $command = sprintf(
                'ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null %s@%s "cat %s 2>/dev/null || echo \'\'"',
                escapeshellarg($keyPath),
                escapeshellarg($this->ssh_user),
                escapeshellarg($this->server->ip_address),
                escapeshellarg($this->outputFile())
            );

            $result = Process::timeout(10)->run($command);

            return $result->output();
        } catch (\Exception $e) {
            return '';
        } finally {
            @unlink($keyPath);
        }
    }

    protected function writeKeyFile(): string
    {
        $path = storage_path('app/keys/'.uniqid());

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }

        file_put_contents($path, rtrim($this->user->ssh_private_key).PHP_EOL);
        chmod($path, 0600);

        return $path;
    }
}
