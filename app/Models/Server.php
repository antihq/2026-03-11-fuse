<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Server extends Model
{
    protected $fillable = ['team_id', 'name', 'ip_address', 'ram_mb', 'authorized_keys', 'provision_token', 'sites_user', 'provisioned_at'];

    protected function casts(): array
    {
        return [
            'ram_mb' => 'integer',
            'provisioned_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
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
