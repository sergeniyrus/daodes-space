<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'sort_order',
        'is_active',
        'delivery_enabled',
        'pickup_enabled',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'delivery_enabled' => 'boolean',
        'pickup_enabled' => 'boolean',
    ];

    /**
     * Организация, которой принадлежит раздел меню.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class
        );
    }

    /**
     * Блюда раздела.
     */
    public function items(): HasMany
    {
        return $this->hasMany(
            MenuItem::class
        )->orderBy(
            'sort_order'
        );
    }

    /**
     * Только активные блюда.
     */
    public function activeItems(): HasMany
    {
        return $this->hasMany(
            MenuItem::class
        )
            ->where('is_active', true)
            ->where(function ($query) {
                if ($this->delivery_enabled) {
                    $query->where('delivery_enabled', true);
                }

                if ($this->pickup_enabled) {
                    if ($this->delivery_enabled) {
                        $query->orWhere('pickup_enabled', true);
                    } else {
                        $query->where('pickup_enabled', true);
                    }
                }

                if (!$this->delivery_enabled && !$this->pickup_enabled) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->orderBy(
                'sort_order'
            );
    }
}
