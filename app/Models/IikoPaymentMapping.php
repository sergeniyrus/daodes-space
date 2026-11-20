<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IikoPaymentMapping extends Model
{
    protected $table = 'iiko_payment_mappings';

    protected $fillable = [
        'organization_id',
        'daodes_payment_method',
        'iiko_payment_type_id',
        'iiko_payment_type_name',
        'payment_type_kind',
        'is_enabled',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'is_enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
