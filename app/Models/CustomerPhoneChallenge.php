<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPhoneChallenge extends Model
{
    protected $table = 'customer_phone_challenges';

    protected $fillable = [
        'customer_id',
        'code_hash',
        'attempts',
        'expires_at',
        'sent_at',
        'consumed_at',
        'revoked_at',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
