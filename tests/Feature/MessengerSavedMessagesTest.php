<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessengerSavedMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_user_can_create_and_reopen_private_saved_messages_chat(): void
    {
        $user = $this->createUser();
        $user->profile()->create([
            'public_key' => base64_encode(str_repeat('p', 32)),
        ]);

        $payload = [
            'encrypted_key' => base64_encode(str_repeat('e', 48)),
            'nonce' => base64_encode(str_repeat('n', 24)),
        ];

        $first = $this->actingAs($user)
            ->postJson('/chats/saved', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $chatId = (int) DB::table('chats')
            ->where('type', 'saved')
            ->where('initiator_id', $user->id)
            ->value('id');

        $this->assertGreaterThan(0, $chatId);
        $this->assertDatabaseHas('chat_user', [
            'chat_id' => $chatId,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('chat_member_keys', [
            'chat_id' => $chatId,
            'user_id' => $user->id,
            'encrypted_key' => $payload['encrypted_key'],
        ]);

        $this->actingAs($user)
            ->postJson('/chats/saved', [
                'encrypted_key' => base64_encode(str_repeat('x', 48)),
                'nonce' => $payload['nonce'],
            ])
            ->assertOk()
            ->assertJsonPath('redirect_url', $first->json('redirect_url'));

        $this->assertSame(
            1,
            DB::table('chats')->where('type', 'saved')->where('initiator_id', $user->id)->count()
        );
        $this->assertSame(
            1,
            DB::table('chat_member_keys')->where('chat_id', $chatId)->where('user_id', $user->id)->count()
        );
    }

    public function test_saved_messages_requires_an_authenticated_user_with_a_public_key(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson('/chats/saved', [
                'encrypted_key' => base64_encode(str_repeat('e', 48)),
                'nonce' => base64_encode(str_repeat('n', 24)),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error');
    }

    private function createUser(): User
    {
        $user = new User();
        $user->name = 'saved_messages_test_' . Str::uuid();
        $user->password = 'test-password';
        $user->keyword = 'synthetic-test-keyword';
        $user->save();

        return $user;
    }
}
