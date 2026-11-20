<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IikoSyncLog extends Model
{
    protected $table = 'iiko_sync_logs';

    protected $fillable = [
        'organization_id',
        'iiko_connection_id',
        'sync_type',
        'status',
        'message',
        'correlation_id',
        'context',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'iiko_connection_id' => 'integer',
            'context' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IikoConnection::class, 'iiko_connection_id');
    }
}
