<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessengerGroupCallsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'livekit.url' => 'wss://rtc.example.test',
            'livekit.api_key' => 'DAODES_TEST_KEY',
            'livekit.api_secret' => 'daodes-test-secret-that-is-not-production',
        ]);
    }

    public function test_accepted_group_meeting_participant_receives_a_short_lived_room_token(): void
    {
        $user = $this->createUser();
        $chat = Chat::create(['name' => 'Group call test', 'type' => 'group']);
        $chat->users()->attach($user->id, ['role' => 'member']);
        $meetingId = $this->createMeeting($chat, $user, 'accepted');

        $response = $this->actingAs($user)
            ->postJson("/meetings/{$meetingId}/livekit-token")
            ->assertOk()
            ->assertJsonPath('server_url', 'wss://rtc.example.test')
            ->assertJsonPath('room', 'daodes-meeting-' . $meetingId)
            ->assertJsonPath('identity', 'user-' . $user->id)
            ->assertJsonPath('e2ee_required', true);

        $parts = explode('.', $response->json('token'));
        $this->assertCount(3, $parts);

        $claims = json_decode(
            base64_decode(strtr($parts[1], '-_', '+/')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame('DAODES_TEST_KEY', $claims['iss']);
        $this->assertSame('user-' . $user->id, $claims['sub']);
        $this->assertSame('daodes-meeting-' . $meetingId, $claims['video']['room']);
        $this->assertTrue($claims['video']['roomJoin']);
        $this->assertTrue($claims['video']['canPublish']);
        $this->assertTrue($claims['video']['canSubscribe']);
        $this->assertGreaterThan(now()->timestamp, $claims['exp']);
        $this->assertLessThanOrEqual(now()->addDay()->timestamp, $claims['exp']);
    }

    public function test_pending_invitation_cannot_get_group_call_token(): void
    {
        $user = $this->createUser();
        $chat = Chat::create(['name' => 'Pending group call', 'type' => 'group']);
        $chat->users()->attach($user->id, ['role' => 'member']);
        $meetingId = $this->createMeeting($chat, $user, 'pending');

        $this->actingAs($user)
            ->postJson("/meetings/{$meetingId}/livekit-token")
            ->assertForbidden();
    }

    public function test_personal_chat_meeting_cannot_use_group_call_token(): void
    {
        $user = $this->createUser();
        $chat = Chat::create(['name' => 'Personal call', 'type' => 'personal']);
        $chat->users()->attach($user->id, ['role' => 'member']);
        $meetingId = $this->createMeeting($chat, $user, 'accepted');

        $this->actingAs($user)
            ->postJson("/meetings/{$meetingId}/livekit-token")
            ->assertStatus(422);
    }

    public function test_group_call_token_is_unavailable_when_livekit_is_not_configured(): void
    {
        config([
            'livekit.url' => '',
            'livekit.api_key' => '',
            'livekit.api_secret' => '',
        ]);

        $user = $this->createUser();
        $chat = Chat::create(['name' => 'No LiveKit', 'type' => 'group']);
        $chat->users()->attach($user->id, ['role' => 'member']);
        $meetingId = $this->createMeeting($chat, $user, 'accepted');

        $this->actingAs($user)
            ->postJson("/meetings/{$meetingId}/livekit-token")
            ->assertStatus(503);
    }

    private function createMeeting(Chat $chat, User $user, string $response): int
    {
        $now = now();
        $meetingId = DB::table('chat_meetings')->insertGetId([
            'chat_id' => $chat->id,
            'organizer_id' => $user->id,
            'encrypted_title' => base64_encode('encrypted title'),
            'title_nonce' => base64_encode(str_repeat('n', 24)),
            'starts_at' => now()->subMinute(),
            'duration_minutes' => 30,
            'status' => 'scheduled',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('chat_meeting_participants')->insert([
            'meeting_id' => $meetingId,
            'user_id' => $user->id,
            'response' => $response,
            'responded_at' => $response === 'accepted' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $meetingId;
    }

    private function createUser(): User
    {
        $user = new User();
        $user->name = 'group_call_test_' . Str::uuid();
        $user->password = 'test-password';
        $user->keyword = 'synthetic-test-keyword';
        $user->save();

        return $user;
    }
}
