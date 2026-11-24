<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessengerMeetingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_member_can_schedule_meeting_for_chat_participants(): void
    {
        $organizer = $this->createUser();
        $invitee = $this->createUser();
        $outsider = $this->createUser();
        $chat = Chat::create(['name' => 'Meeting test', 'type' => 'personal']);
        $chat->users()->attach($organizer->id, ['role' => 'member']);
        $chat->users()->attach($invitee->id, ['role' => 'member']);

        $this->actingAs($organizer)->postJson("/chats/{$chat->id}/meetings", [
            'encrypted_title' => base64_encode('encrypted title'),
            'title_nonce' => base64_encode(str_repeat('n', 24)),
            'starts_at' => now()->addHour()->toIso8601String(),
            'duration_minutes' => 30,
            'participant_ids' => [$invitee->id],
        ])->assertCreated()->assertJsonPath('status', 'success');

        $this->actingAs($invitee)->getJson("/chats/{$chat->id}/meetings")
            ->assertOk()->assertJsonCount(1);

        $this->actingAs($outsider)->getJson("/chats/{$chat->id}/meetings")
            ->assertForbidden();
    }

    private function createUser(): User
    {
        $user = new User();
        $user->name = 'meeting_test_' . Str::uuid();
        $user->password = 'test-password';
        $user->keyword = 'synthetic-test-keyword';
        $user->save();

        return $user;
    }
}
