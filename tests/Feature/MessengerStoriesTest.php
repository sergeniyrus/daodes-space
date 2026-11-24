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

class MessengerStoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_story_is_shared_only_with_direct_contacts_that_have_key_envelopes(): void
    {
        [$owner, $contact, $outsider, $storyId] = $this->createStoryWithContact();

        $this->actingAs($contact)
            ->getJson('/stories')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $storyId)
            ->assertJsonPath('0.author_name', $owner->name);

        $this->actingAs($outsider)
            ->getJson('/stories')
            ->assertOk()
            ->assertJsonCount(0);

        $this->actingAs($contact)
            ->getJson("/stories/{$storyId}/key")
            ->assertOk()
            ->assertJsonStructure(['encrypted_key', 'nonce', 'sender_public_key', 'file_nonce', 'mime_type']);
    }

    public function test_story_views_are_recorded_once_and_only_for_recipients(): void
    {
        [$owner, $contact, $outsider, $storyId] = $this->createStoryWithContact();

        $this->actingAs($contact)
            ->postJson("/stories/{$storyId}/view")
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->actingAs($contact)
            ->postJson("/stories/{$storyId}/view")
            ->assertOk();

        $this->assertSame(
            1,
            DB::table('chat_story_views')
                ->where('story_id', $storyId)
                ->where('user_id', $contact->id)
                ->count()
        );

        $this->actingAs($outsider)
            ->postJson("/stories/{$storyId}/view")
            ->assertNotFound();
    }

    public function test_story_upload_requires_an_envelope_for_every_allowed_recipient_only(): void
    {
        $owner = $this->createUser();
        $contact = $this->createUser();
        $outsider = $this->createUser();

        $owner->profile()->create(['public_key' => base64_encode(str_repeat('a', 32))]);
        $contact->profile()->create(['public_key' => base64_encode(str_repeat('b', 32))]);
        $outsider->profile()->create(['public_key' => base64_encode(str_repeat('c', 32))]);

        $chat = Chat::create(['name' => 'Story contact', 'type' => 'personal']);
        $chat->users()->attach($owner->id, ['role' => 'member']);
        $chat->users()->attach($contact->id, ['role' => 'member']);

        $this->mock(IPFSService::class, function ($mock) {
            $mock->shouldReceive('uploadFile')->once()->andReturn('bafy-encrypted-story-test');
        });

        $response = $this->actingAs($owner)->post('/stories', [
            'file' => UploadedFile::fake()->create('story.enc', 20, 'application/octet-stream'),
            'media_type' => 'image',
            'mime_type' => 'image/jpeg',
            'file_nonce' => base64_encode(str_repeat('n', 24)),
            'encrypted' => '1',
            'expires_hours' => 24,
            'keys' => [
                [
                    'user_id' => $owner->id,
                    'encrypted_key' => base64_encode(str_repeat('x', 48)),
                    'nonce' => base64_encode(str_repeat('n', 24)),
                ],
                [
                    'user_id' => $contact->id,
                    'encrypted_key' => base64_encode(str_repeat('y', 48)),
                    'nonce' => base64_encode(str_repeat('m', 24)),
                ],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('chat_stories', [
            'user_id' => $owner->id,
            'ipfs_cid' => 'bafy-encrypted-story-test',
        ]);

        $this->assertDatabaseHas('chat_story_keys', [
            'user_id' => $contact->id,
            'sender_public_key' => $owner->profile->public_key,
        ]);

        $this->assertSame(2, DB::table('chat_story_keys')->count());
    }

    private function createStoryWithContact(): array
    {
        $owner = $this->createUser();
        $contact = $this->createUser();
        $outsider = $this->createUser();

        $chat = Chat::create(['name' => 'Story contact', 'type' => 'personal']);
        $chat->users()->attach($owner->id, ['role' => 'member']);
        $chat->users()->attach($contact->id, ['role' => 'member']);

        $storyId = DB::table('chat_stories')->insertGetId([
            'user_id' => $owner->id,
            'ipfs_cid' => 'bafy-private-story',
            'media_type' => 'image',
            'mime_type' => 'image/jpeg',
            'file_nonce' => base64_encode(str_repeat('n', 24)),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$owner, $contact] as $recipient) {
            DB::table('chat_story_keys')->insert([
                'story_id' => $storyId,
                'user_id' => $recipient->id,
                'encrypted_key' => base64_encode(str_repeat('e', 48)),
                'nonce' => base64_encode(str_repeat('n', 24)),
                'sender_public_key' => base64_encode(str_repeat('p', 32)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [$owner, $contact, $outsider, $storyId];
    }

    private function createUser(): User
    {
        $user = new User();
        $user->name = 'story_test_' . Str::uuid();
        $user->password = 'test-password';
        $user->keyword = 'synthetic-test-keyword';
        $user->save();

        return $user;
    }
}
