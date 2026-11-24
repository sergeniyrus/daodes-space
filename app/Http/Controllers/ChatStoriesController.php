<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\IPFSService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatStoriesController extends Controller
{
    /**
     * Список активных историй, доступных текущему пользователю.
     * CID и содержимое историй клиенту не раскрываются.
     */
    public function index()
    {
        $userId = Auth::id();

        $stories = DB::table('chat_stories as stories')
            ->join('chat_story_keys as story_keys', 'story_keys.story_id', '=', 'stories.id')
            ->join('users as authors', 'authors.id', '=', 'stories.user_id')
            ->leftJoin('user_profiles as profiles', 'profiles.user_id', '=', 'authors.id')
            ->leftJoin('chat_story_views as views', function ($join) use ($userId) {
                $join->on('views.story_id', '=', 'stories.id')
                    ->where('views.user_id', '=', $userId);
            })
            ->where('story_keys.user_id', $userId)
            ->where('stories.expires_at', '>', now())
            ->whereNotExists(function ($query) use ($userId) {
                $query->select(DB::raw(1))
                    ->from('chat_contact_blocks as blocks')
                    ->where(function ($pair) use ($userId) {
                        $pair->whereColumn('blocks.user_id', 'stories.user_id')
                            ->where('blocks.blocked_user_id', $userId);
                    })
                    ->orWhere(function ($pair) use ($userId) {
                        $pair->where('blocks.user_id', $userId)
                            ->whereColumn('blocks.blocked_user_id', 'stories.user_id');
                    });
            })
            ->orderByDesc('stories.created_at')
            ->limit(100)
            ->get([
                'stories.id',
                'stories.user_id',
                'authors.name as author_name',
                'profiles.avatar_url',
                'stories.media_type',
                'stories.mime_type',
                'stories.file_nonce',
                'stories.created_at',
                'stories.expires_at',
                'views.id as viewed_id',
            ])
            ->map(fn($story) => [
                'id' => (int) $story->id,
                'user_id' => (int) $story->user_id,
                'author_name' => $story->author_name,
                'avatar_url' => $story->avatar_url,
                'media_type' => $story->media_type,
                'mime_type' => $story->mime_type,
                'file_nonce' => $story->file_nonce,
                'created_at' => $story->created_at,
                'expires_at' => $story->expires_at,
                'viewed' => $story->viewed_id !== null,
                'is_own' => (int) $story->user_id === (int) $userId,
            ])
            ->values();

        return response()->json($stories);
    }

    /**
     * Контакты — участники личных чатов плюс сам пользователь.
     * Возвращаем только публичные ключи для клиентского шифрования story key.
     */
    public function contacts()
    {
        $userId = Auth::id();

        $contactIds = $this->storyContactIds($userId);

        $contacts = User::with('profile')
            ->whereIn('id', $contactIds)
            ->get(['id', 'name'])
            ->map(fn($user) => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'avatar_url' => $user->profile?->avatar_url,
                'public_key' => $user->profile?->public_key,
                'is_self' => (int) $user->id === (int) $userId,
            ])
            ->values();

        return response()->json($contacts);
    }

    /**
     * Принимает только зашифрованный файл и ключевые конверты.
     * Каждый получатель получает отдельный envelope, созданный в браузере.
     */
    public function store(Request $request, IPFSService $ipfs)
    {
        $validated = $request->validate([
            'file' => 'required|file|max:51200',
            'media_type' => 'required|string|in:image,video',
            'mime_type' => 'required|string|max:150',
            'file_nonce' => 'required|string|max:255',
            'encrypted' => 'required|accepted',
            'expires_hours' => 'required|integer|in:24,48',
            'keys' => 'required|array|min:1|max:100',
            'keys.*.user_id' => 'required|integer|distinct|exists:users,id',
            'keys.*.encrypted_key' => 'required|string|max:255',
            'keys.*.nonce' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        $senderPublicKey = $user->profile?->public_key;
        if (!$senderPublicKey) {
            return response()->json(['status' => 'error', 'message' => 'Public encryption key is not configured'], 422);
        }

        $allowedIds = $this->storyContactIds((int) $user->id)->all();

        $keys = collect($validated['keys'])->keyBy(fn($item) => (int) $item['user_id']);
        if (!$keys->has((int) $user->id)) {
            return response()->json(['status' => 'error', 'message' => 'A key envelope for the story owner is required'], 422);
        }

        if ($keys->keys()->diff($allowedIds)->isNotEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'Stories can only be shared with personal chat contacts'], 422);
        }

        $cid = null;

        try {
            $file = $request->file('file');
            $cid = $ipfs->uploadFile($file);

            $story = DB::transaction(function () use ($validated, $keys, $senderPublicKey, $user, $cid) {
                $storyId = DB::table('chat_stories')->insertGetId([
                    'user_id' => $user->id,
                    'ipfs_cid' => $cid,
                    'media_type' => $validated['media_type'],
                    'mime_type' => $validated['mime_type'],
                    'file_nonce' => $validated['file_nonce'],
                    'expires_at' => now()->addHours((int) $validated['expires_hours']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($keys as $recipientId => $envelope) {
                    DB::table('chat_story_keys')->insert([
                        'story_id' => $storyId,
                        'user_id' => (int) $recipientId,
                        'encrypted_key' => $envelope['encrypted_key'],
                        'nonce' => $envelope['nonce'],
                        'sender_public_key' => $senderPublicKey,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return DB::table('chat_stories')->where('id', $storyId)->first();
            });

            return response()->json([
                'status' => 'success',
                'story_id' => $story->id,
            ], 201);
        } catch (\Throwable $exception) {
            if ($cid) {
                try {
                    $ipfs->unpinFile($cid);
                } catch (\Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            report($exception);
            return response()->json(['status' => 'error', 'message' => 'Story upload failed'], 503);
        }
    }

    public function key(int $story)
    {
        $storyRecord = DB::table('chat_stories')
            ->where('id', $story)
            ->where('expires_at', '>', now())
            ->first();
        abort_unless($storyRecord, 404);

        $envelope = DB::table('chat_story_keys')
            ->where('story_id', $story)
            ->where('user_id', Auth::id())
            ->first();
        abort_unless($envelope, 404);
        abort_if($this->isBlockedBetween((int) $storyRecord->user_id, (int) Auth::id()), 404);

        return response()->json([
            'encrypted_key' => $envelope->encrypted_key,
            'nonce' => $envelope->nonce,
            'sender_public_key' => $envelope->sender_public_key,
            'file_nonce' => $storyRecord->file_nonce,
            'mime_type' => $storyRecord->mime_type,
            'media_type' => $storyRecord->media_type,
        ]);
    }

    public function media(int $story, IPFSService $ipfs)
    {
        $storyRecord = DB::table('chat_stories')
            ->where('id', $story)
            ->where('expires_at', '>', now())
            ->first();
        abort_unless($storyRecord, 404);

        abort_unless(
            DB::table('chat_story_keys')->where('story_id', $story)->where('user_id', Auth::id())->exists(),
            404
        );
        abort_if($this->isBlockedBetween((int) $storyRecord->user_id, (int) Auth::id()), 404);

        try {
            return response($ipfs->downloadFile($storyRecord->ipfs_cid), 200, [
                'Content-Type' => 'application/octet-stream',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['status' => 'error', 'message' => 'Story unavailable'], 503);
        }
    }

    public function view(int $story)
    {
        $storyRecord = DB::table('chat_stories')
            ->where('id', $story)
            ->where('expires_at', '>', now())
            ->first();
        abort_unless($storyRecord, 404);

        abort_unless(
            DB::table('chat_story_keys')->where('story_id', $story)->where('user_id', Auth::id())->exists(),
            404
        );
        abort_if($this->isBlockedBetween((int) $storyRecord->user_id, (int) Auth::id()), 404);

        DB::table('chat_story_views')->insertOrIgnore([
            'story_id' => $story,
            'user_id' => Auth::id(),
            'viewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'success']);
    }

    public function views(int $story)
    {
        $storyRecord = DB::table('chat_stories')
            ->where('id', $story)
            ->where('user_id', Auth::id())
            ->first();
        abort_unless($storyRecord, 404);

        $views = DB::table('chat_story_views as views')
            ->join('users', 'users.id', '=', 'views.user_id')
            ->leftJoin('user_profiles as profiles', 'profiles.user_id', '=', 'users.id')
            ->where('views.story_id', $story)
            ->orderByDesc('views.viewed_at')
            ->get([
                'users.id',
                'users.name',
                'profiles.avatar_url',
                'views.viewed_at',
            ]);

        return response()->json($views);
    }

    /**
     * Stories доступны друзьям с подтверждённой заявкой и прежним собеседникам
     * личных чатов. Блокировка в любую сторону исключает пользователя.
     */
    private function storyContactIds(int $userId)
    {
        $personalIds = DB::table('chat_user as own')
            ->join('chats', 'chats.id', '=', 'own.chat_id')
            ->join('chat_user as other', 'other.chat_id', '=', 'chats.id')
            ->where('chats.type', 'personal')
            ->where('own.user_id', $userId)
            ->where('other.user_id', '!=', $userId)
            ->pluck('other.user_id')
            ->map(fn ($id) => (int) $id);

        $friendIds = DB::table('chat_contact_requests')
            ->where('status', 'accepted')
            ->where(function ($query) use ($userId) {
                $query->where('sender_id', $userId)
                    ->orWhere('recipient_id', $userId);
            })
            ->get(['sender_id', 'recipient_id'])
            ->map(fn ($row) => (int) ($row->sender_id === $userId ? $row->recipient_id : $row->sender_id));

        $blockedIds = DB::table('chat_contact_blocks')
            ->where('user_id', $userId)
            ->pluck('blocked_user_id')
            ->merge(
                DB::table('chat_contact_blocks')
                    ->where('blocked_user_id', $userId)
                    ->pluck('user_id')
            )
            ->map(fn ($id) => (int) $id);

        return $personalIds
            ->merge($friendIds)
            ->diff($blockedIds)
            ->push($userId)
            ->unique()
            ->values();
    }

    private function isBlockedBetween(int $firstUserId, int $secondUserId): bool
    {
        return DB::table('chat_contact_blocks')
            ->where(function ($query) use ($firstUserId, $secondUserId) {
                $query->where('user_id', $firstUserId)->where('blocked_user_id', $secondUserId);
            })
            ->orWhere(function ($query) use ($firstUserId, $secondUserId) {
                $query->where('user_id', $secondUserId)->where('blocked_user_id', $firstUserId);
            })
            ->exists();
    }

    public function destroy(int $story, IPFSService $ipfs)
    {
        $storyRecord = DB::table('chat_stories')
            ->where('id', $story)
            ->where('user_id', Auth::id())
            ->first();

        abort_unless($storyRecord, 404);

        try {
            $ipfs->unpinFile($storyRecord->ipfs_cid);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['status' => 'error', 'message' => 'Could not remove story from IPFS'], 503);
        }

        DB::table('chat_stories')->where('id', $story)->delete();

        return response()->json(['status' => 'success']);
    }
}
