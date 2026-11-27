<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCheckoutAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_customer_login_before_checkout(): void
    {
        $response = $this->get(route('customer.checkout'));

        $response->assertRedirect(route('customer.login'));
    }

    public function test_unverified_customer_cannot_open_checkout(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Test customer',
            'phone' => '+436601234567',
            'phone_verified_at' => null,
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.checkout'));

        $response->assertForbidden();
    }

    public function test_guest_cannot_submit_an_order(): void
    {
        $response = $this->post(route('customer.checkout.store'), []);

        $response->assertRedirect(route('customer.login'));
        $this->assertDatabaseCount('orders', 0);
    }
}
