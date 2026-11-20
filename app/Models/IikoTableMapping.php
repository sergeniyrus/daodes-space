<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IikoTableMapping extends Model
{
    protected $table = 'iiko_table_mappings';

    protected $fillable = [
        'organization_id',
        'iiko_organization_id',
        'terminal_group_id',
        'daodes_table_number',
        'iiko_table_id',
        'iiko_table_name',
        'restaurant_section_id',
        'restaurant_section_name',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
