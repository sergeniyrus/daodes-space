<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationOrderSetting extends Model
{
    protected $table = 'organization_order_settings';

    protected $fillable = [
        'organization_id',
        'currency',
        'orders_enabled',
        'delivery_enabled',
        'pickup_enabled',
        'dine_in_enabled',
        'online_payment_enabled',
        'cash_payment_enabled',
        'card_payment_enabled',
        'pickup_min_minutes',
        'working_hours',
        'timezone',
        'preorders_enabled',
        'order_lead_minutes',
        'time_slot_minutes',
        'delivery_city',
        'pickup_address',
        'delivery_origin_latitude',
        'delivery_origin_longitude',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'delivery_origin_latitude' => 'decimal:7',
            'delivery_origin_longitude' => 'decimal:7',
            'orders_enabled' => 'boolean',
            'delivery_enabled' => 'boolean',
            'pickup_enabled' => 'boolean',
            'dine_in_enabled' => 'boolean',
            'online_payment_enabled' => 'boolean',
            'cash_payment_enabled' => 'boolean',
            'card_payment_enabled' => 'boolean',
            'pickup_min_minutes' => 'integer',
            'working_hours' => 'array',
            'preorders_enabled' => 'boolean',
            'order_lead_minutes' => 'integer',
            'time_slot_minutes' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function deliveryZones(): HasMany
    {
        return $this->hasMany(OrganizationDeliveryZone::class, 'organization_id', 'organization_id')
            ->orderBy('sort_order');
    }

    public function hasConfiguredDelivery(): bool
    {
        return $this->delivery_enabled
            && $this->delivery_origin_latitude !== null
            && $this->delivery_origin_longitude !== null
            && $this->deliveryZones()->exists();
    }

    public function timezoneName(): string
    {
        $timezone = (string) ($this->timezone ?: 'Asia/Krasnoyarsk');

        return in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : 'Asia/Krasnoyarsk';
    }

    public function isWithinWorkingHours(Carbon $requestedAt): bool
    {
        // До заполнения графика сохраняем прежнее поведение организации.
        if (!is_array($this->working_hours) || $this->working_hours === []) {
            return true;
        }

        $localTime = $requestedAt->copy()->setTimezone($this->timezoneName());
        $day = (string) $localTime->dayOfWeekIso;
        $today = $this->working_hours[$day] ?? null;

        if (!is_array($today) || empty($today['enabled'])) {
            return false;
        }

        $open = (string) ($today['open'] ?? '');
        $close = (string) ($today['close'] ?? '');
        if (!preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $open)
            || !preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $close)) {
            return false;
        }

        $time = $localTime->format('H:i');

        if ($close > $open) {
            return $time >= $open && $time < $close;
        }

        if ($close < $open) {
            if ($time >= $open) {
                return true;
            }

            $previousDay = (string) ($localTime->copy()->subDay()->dayOfWeekIso);
            $previous = $this->working_hours[$previousDay] ?? null;

            return is_array($previous)
                && !empty($previous['enabled'])
                && (string) ($previous['close'] ?? '') < (string) ($previous['open'] ?? '')
                && $time < (string) $previous['close'];
        }

        return false;
    }

    public function minimumOrderLeadMinutes(): int
    {
        return max(30, (int) ($this->order_lead_minutes ?? 30));
    }

    public function canAcceptOrders(): bool
    {
        return $this->orders_enabled
            && filled($this->currency)
            && (
                $this->pickup_enabled
                || $this->dine_in_enabled
                || ($this->delivery_enabled && $this->hasConfiguredDelivery())
            )
            && (
                $this->dine_in_enabled
                || $this->cash_payment_enabled
                || $this->card_payment_enabled
            );
    }
}
