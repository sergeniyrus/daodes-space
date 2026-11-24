<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessengerContactsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_user_can_send_and_accept_contact_request(): void
    {
        $alice = $this->createUser();
        $bob = $this->createUser();

        $this->actingAs($alice)
            ->post("/contacts/{$bob->id}/requests")
            ->assertRedirect();

        $request = DB::table('chat_contact_requests')
            ->where('sender_id', $alice->id)
            ->where('recipient_id', $bob->id)
            ->first();

        $this->assertNotNull($request);
        $this->assertSame('pending', $request->status);

        $this->actingAs($bob)
            ->post("/contacts/requests/{$request->id}/accept")
            ->assertRedirect();

        $this->assertDatabaseHas('chat_contact_requests', [
            'id' => $request->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($alice)
            ->get('/contacts')
            ->assertOk()
            ->assertSee($bob->name);
    }

    public function test_reciprocal_request_automatically_confirms_the_contact(): void
    {
        $alice = $this->createUser();
        $bob = $this->createUser();

        $this->actingAs($alice)->post("/contacts/{$bob->id}/requests")->assertRedirect();
        $this->actingAs($bob)->post("/contacts/{$alice->id}/requests")->assertRedirect();

        $this->assertDatabaseHas('chat_contact_requests', [
            'sender_id' => $alice->id,
            'recipient_id' => $bob->id,
            'status' => 'accepted',
        ]);

        $this->assertSame(
            1,
            DB::table('chat_contact_requests')
                ->where('status', 'accepted')
                ->where(function ($query) use ($alice, $bob) {
                    $query->where('sender_id', $alice->id)->where('recipient_id', $bob->id);
                })
                ->count()
        );
    }

    public function test_blocking_removes_contact_and_prevents_existing_story_access(): void
    {
        $alice = $this->createUser();
        $bob = $this->createUser();

        $requestId = DB::table('chat_contact_requests')->insertGetId([
            'sender_id' => $alice->id,
            'recipient_id' => $bob->id,
            'status' => 'accepted',
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $storyId = DB::table('chat_stories')->insertGetId([
            'user_id' => $alice->id,
            'ipfs_cid' => 'bafy-contact-block-test',
            'media_type' => 'image',
            'mime_type' => 'image/jpeg',
            'file_nonce' => base64_encode(str_repeat('n', 24)),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('chat_story_keys')->insert([
            'story_id' => $storyId,
            'user_id' => $alice->id,
            'encrypted_key' => base64_encode(str_repeat('a', 48)),
            'nonce' => base64_encode(str_repeat('n', 24)),
            'sender_public_key' => base64_encode(str_repeat('p', 32)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('chat_story_keys')->insert([
            'story_id' => $storyId,
            'user_id' => $bob->id,
            'encrypted_key' => base64_encode(str_repeat('b', 48)),
            'nonce' => base64_encode(str_repeat('m', 24)),
            'sender_public_key' => base64_encode(str_repeat('p', 32)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($alice)
            ->post("/contacts/{$bob->id}/block")
            ->assertRedirect();

        $this->assertDatabaseMissing('chat_contact_requests', ['id' => $requestId]);
        $this->assertDatabaseHas('chat_contact_blocks', [
            'user_id' => $alice->id,
            'blocked_user_id' => $bob->id,
        ]);

        $this->actingAs($bob)
            ->getJson('/stories')
            ->assertOk()
            ->assertJsonCount(0);

        $this->actingAs($bob)
            ->getJson("/stories/{$storyId}/key")
            ->assertNotFound();
    }

    private function createUser(): User
    {
        $user = new User();
        $user->name = 'contacts_test_' . Str::uuid();
        $user->password = 'test-password';
        $user->keyword = 'synthetic-test-keyword';
        $user->save();

        return $user;
    }
}
