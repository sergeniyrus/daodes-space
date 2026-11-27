<?php

namespace Tests\Unit;

use App\Models\OrganizationOrderSetting;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class OrganizationOrderSettingTest extends TestCase
{
    public function test_orders_are_disabled_until_manager_enables_them(): void
    {
        $settings = new OrganizationOrderSetting([
            'currency' => 'EUR',
            'orders_enabled' => false,
            'delivery_enabled' => true,
            'cash_payment_enabled' => true,
            'pickup_enabled' => true,
            'pickup_address' => 'Test street 1',
            'working_hours' => array_fill_keys(range(1, 7), [
                'enabled' => true,
                'open' => '09:00',
                'close' => '22:00',
            ]),
        ]);

        $this->assertFalse($settings->canAcceptOrders());
    }

    public function test_orders_require_currency_and_fulfillment_method(): void
    {
        $workingHours = array_fill_keys(range(1, 7), [
            'enabled' => true,
            'open' => '09:00',
            'close' => '22:00',
        ]);

        $missingCurrency = new OrganizationOrderSetting([
            'orders_enabled' => true,
            'pickup_enabled' => true,
            'pickup_address' => 'Test street 1',
            'cash_payment_enabled' => true,
            'working_hours' => $workingHours,
        ]);

        $missingFulfillment = new OrganizationOrderSetting([
            'currency' => 'EUR',
            'orders_enabled' => true,
            'cash_payment_enabled' => true,
            'working_hours' => $workingHours,
        ]);

        $this->assertFalse($missingCurrency->canAcceptOrders());
        $this->assertFalse($missingFulfillment->canAcceptOrders());
    }

    public function test_orders_require_a_supported_payment_method(): void
    {
        $settings = new OrganizationOrderSetting([
            'currency' => 'EUR',
            'orders_enabled' => true,
            'pickup_enabled' => true,
            'pickup_address' => 'Test street 1',
            'online_payment_enabled' => true,
            'cash_payment_enabled' => false,
            'card_payment_enabled' => false,
            'working_hours' => array_fill_keys(range(1, 7), [
                'enabled' => true,
                'open' => '09:00',
                'close' => '22:00',
            ]),
        ]);

        $this->assertFalse($settings->canAcceptOrders());
    }

    public function test_orders_can_be_enabled_with_supported_settings(): void
    {
        $settings = new OrganizationOrderSetting([
            'currency' => 'EUR',
            'orders_enabled' => true,
            'delivery_enabled' => true,
            'pickup_enabled' => true,
            'pickup_address' => 'Test street 1',
            'cash_payment_enabled' => true,
            'working_hours' => array_fill_keys(range(1, 7), [
                'enabled' => true,
                'open' => '09:00',
                'close' => '22:00',
            ]),
        ]);

        $this->assertTrue($settings->canAcceptOrders());
    }
    public function test_overnight_hours_remain_open_after_midnight_even_when_the_next_day_is_disabled(): void
    {
        $workingHours = array_fill_keys(range(1, 7), [
            'enabled' => false,
            'open' => '09:00',
            'close' => '18:00',
        ]);
        $workingHours[4] = [
            'enabled' => true,
            'open' => '18:00',
            'close' => '02:00',
        ];

        $settings = new OrganizationOrderSetting([
            'working_hours' => $workingHours,
            'timezone' => 'Asia/Krasnoyarsk',
        ]);

        $this->assertTrue(
            $settings->isWithinWorkingHours(Carbon::create(2026, 11, 20, 1, 0, 0, 'Asia/Krasnoyarsk'))
        );
        $this->assertFalse(
            $settings->isWithinWorkingHours(Carbon::create(2026, 11, 20, 3, 0, 0, 'Asia/Krasnoyarsk'))
        );
    }

    public function test_enabled_working_day_cannot_have_identical_open_and_close_times(): void
    {
        $workingHours = array_fill_keys(range(1, 7), [
            'enabled' => true,
            'open' => '09:00',
            'close' => '09:00',
        ]);

        $settings = new OrganizationOrderSetting([
            'orders_enabled' => true,
            'currency' => 'EUR',
            'pickup_enabled' => true,
            'cash_payment_enabled' => true,
            'working_hours' => $workingHours,
        ]);

        $this->assertFalse($settings->hasConfiguredWorkingHours());
        $this->assertFalse($settings->canAcceptOrders());
    }

    public function test_pickup_requires_an_address(): void
    {
        $settings = new OrganizationOrderSetting([
            'currency' => 'EUR',
            'orders_enabled' => true,
            'pickup_enabled' => true,
            'cash_payment_enabled' => true,
            'working_hours' => array_fill_keys(range(1, 7), [
                'enabled' => true,
                'open' => '09:00',
                'close' => '22:00',
            ]),
        ]);

        $this->assertFalse($settings->canAcceptOrders());
    }

    public function test_dine_in_can_accept_guest_account_orders_without_receipt_payment_methods(): void
    {
        $settings = new OrganizationOrderSetting([
            'currency' => 'EUR',
            'orders_enabled' => true,
            'dine_in_enabled' => true,
            'cash_payment_enabled' => false,
            'card_payment_enabled' => false,
            'working_hours' => array_fill_keys(range(1, 7), [
                'enabled' => true,
                'open' => '09:00',
                'close' => '22:00',
            ]),
        ]);

        $this->assertTrue($settings->canAcceptOrders());
    }

}
