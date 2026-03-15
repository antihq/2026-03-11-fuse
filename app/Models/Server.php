<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'ip_address', 'ram_mb', 'authorized_keys', 'ssh_setup_token', 'ssh_ready_at', 'provision_status', 'provision_task_id', 'sites_user', 'provisioned_at', 'mysql_root_password', 'deploy_user_password', 'server_public_key'];

    protected function casts(): array
    {
        return [
            'ram_mb' => 'integer',
            'ssh_ready_at' => 'datetime',
            'provisioned_at' => 'datetime',
            'mysql_root_password' => 'encrypted',
            'deploy_user_password' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'provision_task_id');
    }

    public function authorizedKeysCount(): int
    {
        if (empty($this->authorized_keys)) {
            return 0;
        }

        return collect(explode("\n", $this->authorized_keys))
            ->filter(fn ($line) => ! empty(trim($line)))
            ->count();
    }

    public function isProvisioning(): bool
    {
        return in_array($this->provision_status, ['ssh_setup', 'provisioning']);
    }

    public function isProvisioned(): bool
    {
        return $this->provision_status === 'provisioned';
    }

    public function isPending(): bool
    {
        return $this->provision_status === 'pending';
    }
}
