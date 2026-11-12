<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $table = 'organizations';

    protected $fillable = [
        'name',
        'status',
    ];

    /**
     * Пользователи DAODES, связанные с организацией.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'organization_users',
            'organization_id',
            'user_id'
        )
            ->withPivot([
                'role',
                'status',
            ])
            ->withTimestamps();
    }

    /**
     * Связи организации с пользователями.
     */
    public function organizationUsers(): HasMany
    {
        return $this->hasMany(
            OrganizationUser::class
        );
    }

    /**
     * Членства сотрудников организации.
     */
    public function employeeMemberships(): HasMany
    {
        return $this->hasMany(
            OrganizationEmployeeMembership::class,
            'organization_id'
        );
    }

    /**
     * Сотрудники отдельной системы авторизации.
     *
     * Не смешиваются с пользователями DAODES из organization_users.
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganizationEmployee::class,
            'organization_employee_memberships',
            'organization_id',
            'employee_id'
        )
            ->withPivot([
                'role',
                'status',
            ])
            ->withTimestamps();
    }

    /**
     * Разделы меню организации.
     */
    public function menuSections(): HasMany
    {
        return $this->hasMany(
            MenuSection::class,
            'organization_id'
        );
    }

    /**
     * Настройки онлайн-заказов организации.
     */
    public function orderSettings(): \\Illuminate\\Database\\Eloquent\\Relations\\HasOne
    {
        return $this->hasOne(OrganizationOrderSetting::class);
    }

    /**
     * Заказы клиентов организации.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Клиенты, связанные с организацией через customer_organizations.
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(
            Customer::class,
            'customer_organizations',
            'organization_id',
            'customer_id'
        )
            ->withPivot('source')
            ->withTimestamps();
    }

    /**
     * Категории релизов организации.
     */
    public function releaseCategories(): HasMany
    {
        return $this->hasMany(
            ReleaseCategory::class,
            'organization_id'
        );
    }

    /**
     * Проверяет, активна ли организация.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}