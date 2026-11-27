<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\User;
use App\Services\IPFSService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChatChannelStoriesController extends Controller
{
    private function membership(Chat $chat): object
    {
        abort_unless($chat->type === 'channel', 404);

        $membership = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', Auth::id())
            ->first();

        abort_unless($membership, 403);
        return $membership;
    }

    private function canPublish(Chat $chat, object $membership): bool
    {
        $role = $membership->role ?? 'member';

        return $role === 'owner'
            || ($role === 'admin' && (bool) $chat->allow_admin_stories)
            || ($role === 'moderator' && (bool) $chat->allow_moderator_stories);
    }

    private function canChangePhoto(Chat $chat, object $membership): bool
    {
        $role = $membership->role ?? 'member';

        return $role === 'owner'
            || ($role === 'admin' && (bool) $chat->allow_admin_photo)
            || ($role === 'moderator' && (bool) $chat->allow_moderator_photo);
    }

    public function index(Chat $chat)
    {
        $membership = $this->membership($chat);
        $userId = (int) Auth::id();

        $hasReactionsTable = Schema::hasTable('chat_channel_story_reactions');

        $stories = DB::table('chat_channel_stories as stories')
            ->join('chat_channel_story_keys as story_keys', 'story_keys.story_id', '=', 'stories.id')
            ->join('users as publishers', 'publishers.id', '=', 'stories.published_by')
            ->leftJoin('user_profiles as profiles', 'profiles.user_id', '=', 'publishers.id')
            ->leftJoin('chat_channel_story_views as views', function ($join) use ($userId) {
                $join->on('views.story_id', '=', 'stories.id')
                    ->where('views.user_id', '=', $userId);
            })
            ->where('stories.chat_id', $chat->id)
            ->where('story_keys.user_id', $userId)
            ->when($hasReactionsTable, function ($query) use ($userId) {
                $query->leftJoin('chat_channel_story_reactions as reactions', function ($join) use ($userId) {
                    $join->on('reactions.story_id', '=', 'stories.id')
                        ->where('reactions.user_id', '=', $userId);
                });
            })
            ->where('stories.expires_at', '>', now())
            ->orderByDesc('stories.created_at')
            ->limit(100)
            ->get([
                'stories.id',
                'stories.published_by',
                'publishers.name as publisher_name',
                'profiles.avatar_url',
                'stories.media_type',
                'stories.mime_type',
                'stories.file_nonce',
                'stories.created_at',
                'stories.expires_at',
                'views.id as viewed_id',
                DB::raw('(SELECT direct_chats.id
                FROM chats AS direct_chats
                INNER JOIN chat_user AS publisher_members ON publisher_members.chat_id = direct_chats.id
                INNER JOIN chat_user AS viewer_members ON viewer_members.chat_id = direct_chats.id
                WHERE direct_chats.type = "personal"
                  AND publisher_members.user_id = stories.published_by
                  AND viewer_members.user_id = ' . (int) $userId . '
                LIMIT 1) AS direct_chat_id'),
                $hasReactionsTable ? 'reactions.reaction as my_reaction' : DB::raw('NULL as my_reaction'),
            ])
            ->map(fn ($story) => [
                'id' => (int) $story->id,
                'user_id' => (int) $story->published_by,
                'direct_chat_id' => $story->direct_chat_id ? (int) $story->direct_chat_id : null,
                'author_name' => $chat->name,
                'publisher_name' => $story->publisher_name,
                'avatar_url' => $chat->photo_cid ? '/ipfs/' . $chat->photo_cid : ($story->avatar_url ?: '/img/main/default-avatar.png'),
                'media_type' => $story->media_type,
                'mime_type' => $story->mime_type,
                'file_nonce' => $story->file_nonce,
                'created_at' => $story->created_at,
                'expires_at' => $story->expires_at,
                'viewed' => $story->viewed_id !== null,
                'my_reaction' => $story->my_reaction,
                'is_own' => (int) $story->published_by === $userId,
                'is_channel' => true,
                'can_manage' => ($membership->role ?? 'member') === 'owner'
                    || (int) $story->published_by === $userId,
            ])
            ->values();

        return response()->json($stories);
    }

    public function contacts(Chat $chat)
    {
        $membership = $this->membership($chat);
        abort_unless($this->canPublish($chat, $membership), 403);

        $contacts = User::with('profile')
            ->whereIn('id', DB::table('chat_user')->where('chat_id', $chat->id)->pluck('user_id'))
            ->get(['id', 'name'])
            ->map(fn ($user) => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'avatar_url' => $user->profile?->avatar_url,
                'public_key' => $user->profile?->public_key,
                'is_self' => (int) $user->id === (int) Auth::id(),
            ])
            ->values();

        return response()->json($contacts);
    }

    public function store(Request $request, Chat $chat, IPFSService $ipfs)
    {
        $membership = $this->membership($chat);
        abort_unless($this->canPublish($chat, $membership), 403);

        $validated = $request->validate([
            'file' => 'required|file|max:51200',
            'media_type' => 'required|string|in:image,video',
            'mime_type' => 'required|string|max:150',
            'file_nonce' => 'required|string|max:255',
            'encrypted' => 'required|accepted',
            'expires_hours' => 'required|integer|in:24,48',
            'keys' => 'required|array|min:1|max:500',
            'keys.*.user_id' => 'required|integer|distinct|exists:users,id',
            'keys.*.encrypted_key' => 'required|string|max:255',
            'keys.*.nonce' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        $senderPublicKey = $user->profile?->public_key;
        if (!$senderPublicKey) {
            return response()->json(['status' => 'error', 'message' => 'Public encryption key is not configured'], 422);
        }

        $memberIds = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();

        $keys = collect($validated['keys'])->keyBy(fn ($item) => (int) $item['user_id']);
        if (!$keys->has((int) $user->id) || $keys->keys()->map(fn ($id) => (int) $id)->sort()->values()->all() !== $memberIds->all()) {
            return response()->json(['status' => 'error', 'message' => 'An encrypted key for every current channel member is required'], 422);
        }

        $cid = null;

        try {
            $cid = $ipfs->uploadFile($request->file('file'));

            $storyId = DB::transaction(function () use ($validated, $keys, $senderPublicKey, $user, $cid, $chat) {
                $storyId = DB::table('chat_channel_stories')->insertGetId([
                    'chat_id' => $chat->id,
                    'published_by' => $user->id,
                    'ipfs_cid' => $cid,
                    'media_type' => $validated['media_type'],
                    'mime_type' => $validated['mime_type'],
                    'file_nonce' => $validated['file_nonce'],
                    'expires_at' => now()->addHours((int) $validated['expires_hours']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($keys as $recipientId => $envelope) {
                    DB::table('chat_channel_story_keys')->insert([
                        'story_id' => $storyId,
                        'user_id' => (int) $recipientId,
                        'encrypted_key' => $envelope['encrypted_key'],
                        'nonce' => $envelope['nonce'],
                        'sender_public_key' => $senderPublicKey,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return $storyId;
            });

            return response()->json(['status' => 'success', 'story_id' => $storyId], 201);
        } catch (\Throwable $exception) {
            if ($cid) {
                try {
                    $ipfs->unpinFile($cid);
                } catch (\Throwable $cleanupException) {
                    report($cleanupException);
                }
            }
            report($exception);
            return response()->json(['status' => 'error', 'message' => 'Channel story upload failed'], 503);
        }
    }

    private function accessibleStory(Chat $chat, int $storyId): object
    {
        $this->membership($chat);
        $story = DB::table('chat_channel_stories')
            ->where('id', $storyId)
            ->where('chat_id', $chat->id)
            ->where('expires_at', '>', now())
            ->first();
        abort_unless($story, 404);

        abort_unless(DB::table('chat_channel_story_keys')
            ->where('story_id', $storyId)
            ->where('user_id', Auth::id())
            ->exists(), 404);

        return $story;
    }

    public function key(Chat $chat, int $story)
    {
        $record = $this->accessibleStory($chat, $story);
        $envelope = DB::table('chat_channel_story_keys')
            ->where('story_id', $record->id)
            ->where('user_id', Auth::id())
            ->first();

        return response()->json([
            'encrypted_key' => $envelope->encrypted_key,
            'nonce' => $envelope->nonce,
            'sender_public_key' => $envelope->sender_public_key,
            'file_nonce' => $record->file_nonce,
            'mime_type' => $record->mime_type,
            'media_type' => $record->media_type,
        ]);
    }

    public function media(Chat $chat, int $story, IPFSService $ipfs)
    {
        $record = $this->accessibleStory($chat, $story);
        try {
            return response($ipfs->downloadFile($record->ipfs_cid), 200, [
                'Content-Type' => 'application/octet-stream',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['status' => 'error', 'message' => 'Story unavailable'], 503);
        }
    }

    public function view(Chat $chat, int $story)
    {
        $record = $this->accessibleStory($chat, $story);
        DB::table('chat_channel_story_views')->insertOrIgnore([
            'story_id' => $record->id,
            'user_id' => Auth::id(),
            'viewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['status' => 'success']);
    }

    public function toggleReaction(Request $request, Chat $chat, int $story)
    {
        if (!Schema::hasTable('chat_channel_story_reactions')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Для реакций на истории канала требуется применить миграцию DAODES.',
            ], 503);
        }

        $record = $this->accessibleStory($chat, $story);
        $validated = $request->validate([
            'reaction' => 'required|string|in:❤️,🔥,😂,😮,👏,😢',
        ]);

        $existing = DB::table('chat_channel_story_reactions')
            ->where('story_id', $record->id)
            ->where('user_id', Auth::id())
            ->first();

        if ($existing && $existing->reaction === $validated['reaction']) {
            DB::table('chat_channel_story_reactions')->where('id', $existing->id)->delete();
            $active = false;
        } else {
            DB::table('chat_channel_story_reactions')->updateOrInsert(
                ['story_id' => $record->id, 'user_id' => Auth::id()],
                ['reaction' => $validated['reaction'], 'created_at' => now(), 'updated_at' => now()]
            );
            $active = true;
        }

        return response()->json(['status' => 'success', 'active' => $active, 'reaction' => $active ? $validated['reaction'] : null]);
    }

    public function views(Chat $chat, int $story)
    {
        $record = DB::table('chat_channel_stories')
            ->where('id', $story)
            ->where('chat_id', $chat->id)
            ->first();
        abort_unless($record, 404);

        $membership = $this->membership($chat);
        abort_unless(($membership->role ?? 'member') === 'owner' || (int) $record->published_by === (int) Auth::id(), 403);

        return response()->json(DB::table('chat_channel_story_views as views')
            ->join('users', 'users.id', '=', 'views.user_id')
            ->where('views.story_id', $story)
            ->orderByDesc('views.viewed_at')
            ->get(['users.id', 'users.name', 'views.viewed_at']));
    }

    public function destroy(Chat $chat, int $story, IPFSService $ipfs)
    {
        $record = DB::table('chat_channel_stories')
            ->where('id', $story)
            ->where('chat_id', $chat->id)
            ->first();
        abort_unless($record, 404);

        $membership = $this->membership($chat);
        abort_unless(($membership->role ?? 'member') === 'owner' || (int) $record->published_by === (int) Auth::id(), 403);

        try {
            $ipfs->unpinFile($record->ipfs_cid);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['status' => 'error', 'message' => 'Could not remove story from IPFS'], 503);
        }

        DB::table('chat_channel_stories')->where('id', $story)->delete();
        return response()->json(['status' => 'success']);
    }
}
