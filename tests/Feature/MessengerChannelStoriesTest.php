<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\User;
use App\Services\IPFSService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessengerChannelStoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_only_channel_owner_can_change_role_permissions(): void
    {
        $owner = $this->createUser();
        $admin = $this->createUser();
        $chat = Chat::create(['name' => 'Private channel', 'type' => 'channel', 'initiator_id' => $owner->id]);
        $chat->users()->attach($owner->id, ['role' => 'owner']);
        $chat->users()->attach($admin->id, ['role' => 'admin']);

        $payload = [
            'allow_admin_photo' => true,
            'allow_moderator_photo' => false,
            'allow_admin_stories' => true,
            'allow_moderator_stories' => false,
        ];

        $this->actingAs($admin)
            ->putJson("/chats/{$chat->id}/channel-settings", $payload)
            ->assertForbidden();

        $this->actingAs($owner)
            ->putJson("/chats/{$chat->id}/channel-settings", $payload)
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('chats', [
            'id' => $chat->id,
            'allow_admin_photo' => 1,
            'allow_moderator_photo' => 0,
            'allow_admin_stories' => 1,
            'allow_moderator_stories' => 0,
        ]);
    }

    public function test_moderator_can_publish_encrypted_channel_story_only_after_owner_grants_permission(): void
    {
        $owner = $this->createUser();
        $moderator = $this->createUser();
        $viewer = $this->createUser();

        foreach ([$owner, $moderator, $viewer] as $index => $user) {
            $user->profile()->create([
                'public_key' => base64_encode(str_repeat(chr(65 + $index), 32)),
            ]);
        }

        $chat = Chat::create(['name' => 'DAODES channel', 'type' => 'channel', 'initiator_id' => $owner->id]);
        $chat->users()->attach($owner->id, ['role' => 'owner']);
        $chat->users()->attach($moderator->id, ['role' => 'moderator']);
        $chat->users()->attach($viewer->id, ['role' => 'member']);

        $this->actingAs($moderator)
            ->getJson("/chats/{$chat->id}/stories/contacts")
            ->assertForbidden();

        $this->actingAs($owner)
            ->putJson("/chats/{$chat->id}/channel-settings", [
                'allow_admin_photo' => false,
                'allow_moderator_photo' => false,
                'allow_admin_stories' => false,
                'allow_moderator_stories' => true,
            ])
            ->assertOk();

        $keys = [];
        foreach ([$owner, $moderator, $viewer] as $recipient) {
            $keys[] = [
                'user_id' => $recipient->id,
                'encrypted_key' => base64_encode(str_repeat('k', 48)),
                'nonce' => base64_encode(str_repeat('n', 24)),
            ];
        }

        $this->mock(IPFSService::class, function ($mock) {
            $mock->shouldReceive('uploadFile')
                ->once()
                ->andReturn('bafy-encrypted-channel-story');
        });

        $response = $this->actingAs($moderator)->post("/chats/{$chat->id}/stories", [
            'file' => UploadedFile::fake()->create('channel-story.enc', 20, 'application/octet-stream'),
            'media_type' => 'image',
            'mime_type' => 'image/jpeg',
            'file_nonce' => base64_encode(str_repeat('f', 24)),
            'encrypted' => '1',
            'expires_hours' => 24,
            'keys' => $keys,
        ]);

        $response->assertCreated()->assertJsonPath('status', 'success');

        $storyId = $response->json('story_id');
        $this->assertDatabaseHas('chat_channel_stories', [
            'id' => $storyId,
            'chat_id' => $chat->id,
            'published_by' => $moderator->id,
            'ipfs_cid' => 'bafy-encrypted-channel-story',
        ]);

        $this->assertDatabaseHas('chat_channel_story_keys', [
            'story_id' => $storyId,
            'user_id' => $viewer->id,
            'sender_public_key' => $moderator->profile->public_key,
        ]);

        $this->actingAs($viewer)
            ->getJson("/chats/{$chat->id}/stories/{$storyId}/key")
            ->assertOk()
            ->assertJsonStructure(['encrypted_key', 'nonce', 'sender_public_key', 'file_nonce', 'mime_type']);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->name = 'channel_test_' . Str::uuid();
        $user->password = 'test-password';
        $user->keyword = 'synthetic-channel-test-keyword';
        $user->save();

        return $user;
    }
}
