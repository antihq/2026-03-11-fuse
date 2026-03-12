<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    protected $fillable = ['user_id', 'name', 'ip_address', 'ram_mb', 'authorized_keys', 'provision_token', 'sites_user', 'provisioned_at', 'mysql_root_password', 'deploy_user_password'];

    protected $hidden = ['mysql_root_password', 'deploy_user_password'];

    protected function casts(): array
    {
        return [
            'ram_mb' => 'integer',
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

    public function authorizedKeysCount(): int
    {
        if (empty($this->authorized_keys)) {
            return 0;
        }

        return collect(explode("\n", $this->authorized_keys))
            ->filter(fn ($line) => ! empty(trim($line)))
            ->count();
    }
}
