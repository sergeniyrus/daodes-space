<?php

namespace Tests\\Feature;

use App\\Models\\Chat;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Str;
use Tests\\TestCase;

class MessengerChatMuteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->withoutMiddleware(\\Illuminate\\Routing\\Middleware\\ThrottleRequests::class);
    }

    public function test_member_can_mute_and_unmute_chat_for_themselves(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();

        $chat = Chat::create([
            'name' => 'Mute test',
            'type' => 'group',
        ]);
        $chat->users()->attach($owner->id, ['role' => 'owner']);
        $chat->users()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->postJson("/chats/{$chat->id}/mute", ['duration' => 1440])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['muted_until']);

        $mutedUntil = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', $member->id)
            ->value('muted_until');

        $this->assertNotNull($mutedUntil);
        $this->assertNull(
            DB::table('chat_user')
                ->where('chat_id', $chat->id)
                ->where('user_id', $owner->id)
                ->value('muted_until')
        );

        $this->actingAs($member)
            ->postJson("/chats/{$chat->id}/mute", ['duration' => null])
            ->assertOk()
            ->assertJsonPath('muted_until', null);

        $this->assertNull(
            DB::table('chat_user')
                ->where('chat_id', $chat->id)
                ->where('user_id', $member->id)
                ->value('muted_until')
        );
    }

    public function test_non_member_cannot_mute_chat_and_invalid_duration_is_rejected(): void
    {
        $owner = $this->createUser();
        $outsider = $this->createUser();

        $chat = Chat::create([
            'name' => 'Mute permissions test',
            'type' => 'group',
        ]);
        $chat->users()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($outsider)
            ->postJson("/chats/{$chat->id}/mute", ['duration' => 60])
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson("/chats/{$chat->id}/mute", ['duration' => 61])
            ->assertUnprocessable();
    }

    private function createUser(): User
    {
        $user = new User();
        $user->name = 'chat_mute_test_' . Str::uuid();
        $user->password = 'test-password';
        $user->keyword = 'synthetic-test-keyword';
        $user->save();

        return $user;
    }
}
