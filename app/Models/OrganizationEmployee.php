<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class OrganizationEmployee extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'organization_employees';

    protected $fillable = [
        'name',
        'phone',
        'password',
        'phone_verified_at',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'phone_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Все членства сотрудника в организациях.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(
            OrganizationEmployeeMembership::class,
            'employee_id'
        );
    }

    /**
     * Только активные членства сотрудника.
     */
    public function activeMemberships(): HasMany
    {
        return $this->memberships()
            ->where('status', 'active')
            ->whereHas('organization', function ($query) {
                $query->where('status', 'active');
            });
    }

    /**
     * Организации, в которых состоит сотрудник.
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(
            Organization::class,
            'organization_employee_memberships',
            'employee_id',
            'organization_id'
        )
            ->withPivot([
                'role',
                'status',
            ])
            ->withTimestamps();
    }

    /**
     * Проверяет активное членство в конкретной организации.
     */
    public function hasActiveMembershipInOrganization(int $organizationId): bool
    {
        return $this->activeMemberships()
            ->where('organization_id', $organizationId)
            ->exists();
    }
}