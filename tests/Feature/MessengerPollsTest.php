<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessengerPollsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_open_poll_records_voter_and_returns_public_voter_name(): void
    {
        [$owner, $member, $chat, $message, $poll] = $this->createPoll(false, false);

        $this->actingAs($member)
            ->postJson("/messages/{$message->id}/poll-votes", ['option_ids' => [1]])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('counts.0.option_id', 1)
            ->assertJsonPath('counts.0.count', 1)
            ->assertJsonPath('voters.1.0', $member->name);

        $this->assertDatabaseHas('chat_poll_votes', [
            'poll_id' => $poll->id,
            'user_id' => $member->id,
            'voter_hash' => null,
            'option_id' => 1,
        ]);
    }

    public function test_anonymous_poll_never_stores_or_returns_voter_identity(): void
    {
        [$owner, $member, $chat, $message, $poll] = $this->createPoll(true, false);

        $this->actingAs($member)
            ->postJson("/messages/{$message->id}/poll-votes", ['option_ids' => [0]])
            ->assertOk()
            ->assertJsonPath('is_anonymous', true)
            ->assertJsonPath('voters', []);

        $this->assertDatabaseHas('chat_poll_votes', [
            'poll_id' => $poll->id,
            'user_id' => null,
            'option_id' => 0,
        ]);

        $vote = DB::table('chat_poll_votes')->where('poll_id', $poll->id)->first();
        $this->assertNotEmpty($vote->voter_hash);
        $this->assertNotSame((string) $member->id, $vote->voter_hash);
    }

    public function test_non_member_cannot_vote_and_closed_poll_rejects_votes(): void
    {
        [$owner, $member, $chat, $message] = $this->createPoll(false, false);
        $outsider = $this->createUser();

        $this->actingAs($outsider)
            ->postJson("/messages/{$message->id}/poll-votes", ['option_ids' => [0]])
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson("/messages/{$message->id}/poll-close")
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->actingAs($member)
            ->postJson("/messages/{$message->id}/poll-votes", ['option_ids' => [0]])
            ->assertStatus(409);
    }

    private function createPoll(bool $anonymous, bool $multiple): array
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $chat = Chat::create(['name' => 'Poll test', 'type' => 'group']);
        $chat->users()->attach($owner->id, ['role' => 'owner']);
        $chat->users()->attach($member->id, ['role' => 'member']);

        $message = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $owner->id,
            'ipfs_cid' => 'bafy-poll-test',
            'message_type' => 'poll',
        ]);

        $pollId = DB::table('chat_polls')->insertGetId([
            'message_id' => $message->id,
            'is_anonymous' => $anonymous,
            'allows_multiple' => $multiple,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$owner, $member, $chat, $message, (object) ['id' => $pollId]];
    }

    private function createUser(): User
    {
        $user = new User();
        $user->name = 'poll_test_' . Str::uuid();
        $user->password = 'test-password';
        $user->keyword = 'synthetic-test-keyword';
        $user->save();

        return $user;
    }
}
