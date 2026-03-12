<?php

namespace App\Models;

use App\Traits\InteractsWithSsh;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
