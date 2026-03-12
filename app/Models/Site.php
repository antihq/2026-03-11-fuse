<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Site extends Model
{
    protected $fillable = [
        'server_id',
        'hostname',
        'php_version',
        'size',
        'repository_url',
        'repository_branch',
    ];

    protected function casts(): array
    {
        return [
            'server_id' => 'integer',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
