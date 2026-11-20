<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Services\IPFSService;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_section_id',
        'name',
        'weight',
        'unit',
        'price',
        'description',
        'sort_order',
        'is_active',
        'image_path',
        'image_cid',
        'delivery_enabled',
        'pickup_enabled',
        'dine_in_enabled',
    ];

    protected $casts = [
        'menu_section_id' => 'integer',
        'weight' => 'decimal:3',
        'price' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'delivery_enabled' => 'boolean',
        'pickup_enabled' => 'boolean',
        'dine_in_enabled' => 'boolean',
    ];

    /**
     * URL фотографии: сначала IPFS CID, затем legacy-путь для старых записей.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (filled($this->image_cid)) {
            return app(IPFSService::class)->gatewayUrl($this->image_cid);
        }

        return $this->image_path ? asset($this->image_path) : null;
    }

    /**
     * Раздел меню.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(
            MenuSection::class,
            'menu_section_id'
        );
    }

    /**
     * Организация через раздел меню.
     */
    public function organization(): BelongsTo
    {
        return $this->hasOneThrough(
            Organization::class,
            MenuSection::class,
            'id',
            'id',
            'menu_section_id',
            'organization_id'
        );
    }

    /**
     * Сопоставление блюда с iiko в рамках организации.
     */
    public function iikoMapping(): HasOne
    {
        return $this->hasOne(IikoMenuMapping::class, 'menu_item_id');
    }
}
