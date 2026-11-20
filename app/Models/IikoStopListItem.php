<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IikoStopListItem extends Model
{
    protected $table = 'iiko_stop_list_items';

    protected $fillable = [
        'organization_id',
        'iiko_organization_id',
        'terminal_group_id',
        'iiko_product_id',
        'balance',
        'is_available',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'balance' => 'decimal:3',
            'is_available' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
