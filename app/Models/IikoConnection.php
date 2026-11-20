<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IikoConnection extends Model
{
    protected $table = 'iiko_connections';

    protected $fillable = [
        'organization_id',
        'api_key',
        'iiko_organization_id',
        'terminal_group_id',
        'external_menu_id',
        'connection_status',
        'is_enabled',
        'menu_sync_enabled',
        'stop_list_sync_enabled',
        'order_sync_enabled',
        'status_sync_enabled',
        'webhook_enabled',
        'webhook_secret',
        'last_connected_at',
        'last_menu_sync_at',
        'last_stop_list_sync_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'api_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'is_enabled' => 'boolean',
            'menu_sync_enabled' => 'boolean',
            'stop_list_sync_enabled' => 'boolean',
            'order_sync_enabled' => 'boolean',
            'status_sync_enabled' => 'boolean',
            'webhook_enabled' => 'boolean',
            'last_connected_at' => 'datetime',
            'last_menu_sync_at' => 'datetime',
            'last_stop_list_sync_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function tokenCacheKey(): string
    {
        return 'iiko:access-token:' . $this->getKey() . ':' . hash('sha256', (string) $this->api_key);
    }
}
