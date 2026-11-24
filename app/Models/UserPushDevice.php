<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPushDevice extends Model
{
    protected $table = 'user_push_devices';

    protected $fillable = [
        'user_id',
        'token_hash',
        'push_token',
        'platform',
        'device_name',
        'is_active',
        'last_seen_at',
    ];

    protected $hidden = [
        'token_hash',
        'push_token',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
