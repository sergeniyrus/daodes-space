<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class MessageAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'ipfs_cid',
        'media_type',
        'byte_size',
        'position',
    ];

    protected $casts = [
        'byte_size' => 'integer',
        'position' => 'integer',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
