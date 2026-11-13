<?php
namespace Tests\Feature;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class CustomerPushDeviceTest extends TestCase
{
    use RefreshDatabase;
    protected function configureFirebase(): void
    {
        config()->set('services.firebase.project_id', 'daodes-test');
        config()->set('services.firebase.client_email', 'firebase@example.test');
        config()->set('services.firebase.private_key', 'test-private-key');
        config()->set('services.firebase.web', [
            'api_key' => 'public-api-key',
            'auth_domain' => 'daodes-test.firebaseapp.com',
            'project_id' => 'daodes-test',
            'storage_bucket' => 'daodes-test.appspot.com',
            'messaging_sender_id' => '123456789',
            'app_id' => '1:123456789:web:test',
            'vapid_key' => 'public-vapid-key',
        ]);
    }
    public function test_verified_customer_can_register_a_web_push_device(): void
    {
        $this->configureFirebase();
        $customer = Customer::query()->create(['phone' => '+79990000001', 'phone_verified_at' => now()]);
        $token = 'test-fcm-registration-token';
        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.push.devices.store'), [
                'token' => $token, 'platform' => 'web', 'device_name' => 'Chrome Desktop',
            ])
            ->assertCreated()
            ->assertJson(['subscribed' => true]);
        $this->assertDatabaseHas('customer_devices', [
            'customer_id' => $customer->id,
            'token_hash' => hash('sha256', $token),
            'platform' => 'web',
            'is_active' => true,
        ]);

        $storedToken = DB::table('customer_devices')
            ->where('token_hash', hash('sha256', $token))
            ->value('push_token');

        $this->assertNotSame($token, $storedToken);
        $this->assertSame($token, $customer->devices()->firstOrFail()->push_token);
    }
    public function test_unverified_customer_cannot_register_a_push_device(): void
    {
        $this->configureFirebase();
        $customer = Customer::query()->create(['phone' => '+79990000002']);
        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.push.devices.store'), [
                'token' => 'unverified-device-token', 'platform' => 'web',
            ])
            ->assertForbidden();
        $this->assertDatabaseCount('customer_devices', 0);
    }
    public function test_customer_can_disable_only_their_own_device(): void
    {
        $this->configureFirebase();
        $owner = Customer::query()->create(['phone' => '+79990000003', 'phone_verified_at' => now()]);
        $anotherCustomer = Customer::query()->create(['phone' => '+79990000004', 'phone_verified_at' => now()]);
        $token = 'owned-device-token';
        $device = $owner->devices()->create([
            'platform' => 'web', 'push_token' => $token,
            'token_hash' => hash('sha256', $token), 'is_active' => true,
        ]);
        $this->actingAs($anotherCustomer, 'customer')
            ->deleteJson(route('customer.push.devices.destroy'), ['token' => $token])
            ->assertOk();
        $this->assertTrue($device->fresh()->is_active);
        $this->actingAs($owner, 'customer')
            ->deleteJson(route('customer.push.devices.destroy'), ['token' => $token])
            ->assertOk()
            ->assertJson(['subscribed' => false]);
        $this->assertFalse($device->fresh()->is_active);
    }
}
