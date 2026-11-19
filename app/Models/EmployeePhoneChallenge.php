<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePhoneChallenge extends Model
{
    protected $table = 'employee_phone_challenges';

    protected $fillable = [
        'employee_id',
        'organization_id',
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
            'employee_id' => 'integer',
            'organization_id' => 'integer',
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(OrganizationEmployee::class, 'employee_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
