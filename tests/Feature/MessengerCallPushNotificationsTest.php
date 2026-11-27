<?php

namespace Tests\Feature;

use App\Jobs\SendChatCallPushNotification;
use App\Models\Chat;
use App\Models\User;
use App\Models\UserPushDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessengerCallPushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.firebase.project_id' => 'daodes-test',
            'services.firebase.client_email' => 'firebase@daodes-test.iam.gserviceaccount.com',
            'services.firebase.private_key' => 'test-private-key',
            'services.firebase.web.api_key' => 'test-api-key',
            'services.firebase.web.auth_domain' => 'daodes-test.firebaseapp.com',
            'services.firebase.web.project_id' => 'daodes-test',
            'services.firebase.web.messaging_sender_id' => '123456789',
            'services.firebase.web.app_id' => '1:123456789:web:test',
            'services.firebase.web.vapid_key' => 'test-vapid-key',
        ]);
    }

    public function test_user_can_register_check_and_disable_call_push_device(): void
    {
        $user = $this->createUser();
        $token = 'web-push-token-' . Str::uuid();

        $this->actingAs($user)
            ->postJson('/chat-call-push/devices', [
                'token' => $token,
                'device_name' => 'Desktop browser',
            ])
            ->assertCreated()
            ->assertJsonPath('enabled', true);

        $device = UserPushDevice::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($device->is_active);
        $this->assertSame(hash('sha256', $token), $device->token_hash);

        $this->actingAs($user)
            ->postJson('/chat-call-push/devices/status', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('enabled', true);

        $this->actingAs($user)
            ->deleteJson('/chat-call-push/devices', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('enabled', false);

        $this->assertDatabaseHas('user_push_devices', [
            'id' => $device->id,
            'is_active' => 0,
        ]);
    }

    public function test_user_cannot_disable_another_users_call_push_device(): void
    {
        $owner = $this->createUser();
        $otherUser = $this->createUser();
        $token = 'private-token-' . Str::uuid();

        $device = UserPushDevice::query()->create([
            'user_id' => $owner->id,
            'token_hash' => hash('sha256', $token),
            'push_token' => $token,
            'platform' => 'web',
            'is_active' => true,
        ]);

        $this->actingAs($otherUser)
            ->deleteJson('/chat-call-push/devices', ['token' => $token])
            ->assertOk();

        $this->assertDatabaseHas('user_push_devices', [
            'id' => $device->id,
            'user_id' => $owner->id,
            'is_active' => 1,
        ]);
    }

    public function test_starting_a_call_queues_push_only_for_the_callee_devices(): void
    {
        Queue::fake();

        $caller = $this->createUser();
        $callee = $this->createUser();
        $chat = Chat::create(['name' => 'Call push', 'type' => 'personal']);
        $chat->users()->attach($caller->id, ['role' => 'member']);
        $chat->users()->attach($callee->id, ['role' => 'member']);

        $device = UserPushDevice::query()->create([
            'user_id' => $callee->id,
            'token_hash' => hash('sha256', 'callee-token'),
            'push_token' => 'callee-token',
            'platform' => 'web',
            'is_active' => true,
        ]);

        UserPushDevice::query()->create([
            'user_id' => $caller->id,
            'token_hash' => hash('sha256', 'caller-token'),
            'push_token' => 'caller-token',
            'platform' => 'web',
            'is_active' => true,
        ]);

        $this->actingAs($caller)
            ->postJson('/chats/' . $chat->id . '/calls', [
                'call_type' => 'audio',
                'encrypted_offer' => json_encode(['nonce' => 'n', 'ciphertext' => 'c']),
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'success');

        Queue::assertPushed(SendChatCallPushNotification::class, function (SendChatCallPushNotification $job) use ($device) {
            return $job->deviceId === $device->id && $job->callId > 0;
        });

        Queue::assertPushed(SendChatCallPushNotification::class, 1);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->name = 'call_push_test_' . Str::uuid();
        $user->password = 'test-password';
        $user->keyword = 'synthetic-test-keyword';
        $user->save();

        return $user;
    }
}
