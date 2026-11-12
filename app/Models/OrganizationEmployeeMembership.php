<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationEmployeeMembership extends Model
{
    use HasFactory;

    protected $table = 'organization_employee_memberships';

    protected $fillable = [
        'employee_id',
        'organization_id',
        'role',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'organization_id' => 'integer',
        ];
    }

    /**
     * Учётная запись сотрудника.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(
            OrganizationEmployee::class,
            'employee_id'
        );
    }

    /**
     * Организация, в которой действует членство.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->organization?->isActive() === true;
    }

    public function hasRole(array|string $roles): bool
    {
        return in_array(
            $this->role,
            (array) $roles,
            true
        );
    }
}