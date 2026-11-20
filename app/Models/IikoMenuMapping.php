<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IikoMenuMapping extends Model
{
    protected $table = 'iiko_menu_mappings';

    protected $fillable = [
        'organization_id',
        'menu_item_id',
        'iiko_product_id',
        'iiko_product_name',
        'iiko_group_id',
        'external_menu_id',
        'price_category_id',
        'last_iiko_price',
        'last_checked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'menu_item_id' => 'integer',
            'last_iiko_price' => 'decimal:2',
            'last_checked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
