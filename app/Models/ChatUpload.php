<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatUpload extends Model
{
    protected $fillable = [
        'chat_id',
        'user_id',
        'ipfs_cid',
        'media_type',
        'byte_size',
        'expires_at',
    ];

    protected $casts = [
        'byte_size' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
