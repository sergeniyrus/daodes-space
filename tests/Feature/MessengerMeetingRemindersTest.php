<?php

namespace Tests\Feature;

use App\Jobs\SendChatMeetingReminder;
use App\Models\Chat;
use App\Models\User;
use App\Models\UserPushDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessengerMeetingRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.firebase.project_id' => 'daodes-test',
            'services.firebase.client_email' => 'firebase@daodes-test.iam.gserviceaccount.com',
            'services.firebase.private_key' => 'test-private-key',
        ]);
    }

    public function test_accepted_participant_receives_one_queued_reminder_within_fifteen_minutes(): void
    {
        Queue::fake();

        $user = $this->createUser();
        $chat = Chat::create(['name' => 'Reminder test', 'type' => 'personal']);
        $chat->users()->attach($user->id, ['role' => 'member']);

        $meetingId = $this->createMeeting($chat, $user, 'accepted');

        UserPushDevice::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'meeting-reminder-token'),
            'push_token' => 'meeting-reminder-token',
            'platform' => 'web',
            'is_active' => true,
        ]);

        $this->artisan('chat:send-meeting-reminders')->assertExitCode(0);

        Queue::assertPushed(SendChatMeetingReminder::class, 1);
        $participant = \Illuminate\Support\Facades\DB::table('chat_meeting_participants')
            ->where('meeting_id', $meetingId)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($participant->reminder_sent_at);

        // Повторный запуск планировщика не создаёт повторное напоминание.
        $this->artisan('chat:send-meeting-reminders')->assertExitCode(0);
        Queue::assertPushed(SendChatMeetingReminder::class, 1);
    }

    public function test_pending_invitation_does_not_receive_meeting_reminder(): void
    {
        Queue::fake();

        $user = $this->createUser();
        $chat = Chat::create(['name' => 'Pending reminder test', 'type' => 'personal']);
        $chat->users()->attach($user->id, ['role' => 'member']);

        $this->createMeeting($chat, $user, 'pending');

        UserPushDevice::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'pending-meeting-token'),
            'push_token' => 'pending-meeting-token',
            'platform' => 'web',
            'is_active' => true,
        ]);

        $this->artisan('chat:send-meeting-reminders')->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    private function createMeeting(Chat $chat, User $user, string $response): int
    {
        $now = now();
        $meetingId = \Illuminate\Support\Facades\DB::table('chat_meetings')->insertGetId([
            'chat_id' => $chat->id,
            'organizer_id' => $user->id,
            'encrypted_title' => base64_encode('encrypted title'),
            'title_nonce' => base64_encode(str_repeat('n', 24)),
            'starts_at' => now()->addMinutes(10),
            'duration_minutes' => 30,
            'status' => 'scheduled',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        \Illuminate\Support\Facades\DB::table('chat_meeting_participants')->insert([
            'meeting_id' => $meetingId,
            'user_id' => $user->id,
            'response' => $response,
            'responded_at' => $response === 'accepted' ? $now : null,
            'reminder_sent_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $meetingId;
    }

    private function createUser(): User
    {
        $user = new User();
        $user->name = 'meeting_reminder_test_' . Str::uuid();
        $user->password = 'test-password';
        $user->keyword = 'synthetic-test-keyword';
        $user->save();

        return $user;
    }
}
