<?php // app/Http/Controllers/ChatController.php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\User;
use App\Models\ChatMemberKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $groupChats = Chat::with('users.profile')->whereIn('type', ['group', 'channel'])
            ->whereHas('participants', fn($q) => $q->where('user_id', $user->id))
            ->paginate(10);

        $privateChats = Chat::with('users.profile')->whereIn('type', ['personal', 'saved'])
            ->whereHas('participants', fn($q) => $q->where('user_id', $user->id))
            ->paginate(10);

        $uniqueChats = $this->getUnreadMessagesCount($groupChats->merge($privateChats));

        return view('chats.index', compact('groupChats', 'privateChats', 'uniqueChats'));
    }
    /**
     * Создаёт или открывает личное пространство «Избранное».
     * Ключ чата шифруется браузером публичным ключом самого пользователя.
     */
    public function createSavedMessages(Request $request)
    {
        $validated = $request->validate([
            'encrypted_key' => 'required|string|max:255',
            'nonce' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        $publicKey = $user->profile?->public_key;

        if (!$publicKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Public encryption key is not configured',
            ], 422);
        }

        $chat = DB::transaction(function () use ($user, $publicKey, $validated) {
            DB::table('users')
                ->where('id', $user->id)
                ->lockForUpdate()
                ->first();

            $existing = Chat::where('type', 'saved')
                ->where('initiator_id', $user->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $chat = Chat::create([
                'name' => __('chats.saved_messages'),
                'type' => 'saved',
                'initiator_id' => $user->id,
            ]);

            $chat->users()->attach($user->id, [
                'role' => 'owner',
                'joined_at' => now(),
            ]);

            ChatMemberKey::create([
                'chat_id' => $chat->id,
                'user_id' => $user->id,
                'encrypted_key' => $validated['encrypted_key'],
                'nonce' => $validated['nonce'],
                'sender_public_key' => $publicKey,
            ]);

            return $chat;
        });

        return response()->json([
            'status' => 'success',
            'redirect_url' => route('chats.show', $chat->id),
        ]);
    }


    private function getUnreadMessagesCount($chats)
    {
        $userId = Auth::id();
        $counts = [];

        foreach ($chats as $chat) {
            if ($chat->users->contains($userId)) {
                $unread = $chat->messages()
                    ->whereHas('notifications', fn($q) => $q->where('user_id', $userId)->where('is_read', false))
                    ->count();
                $counts[$chat->id] = $unread;
            }
        }

        return $counts;
    }

    public function create()
    {
        $currentUser = Auth::user();

        $users = User::with('profile')
            ->where('id', '!=', $currentUser->id)
            ->whereNotIn('id', [1, 2])
            ->get()
            ->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->profile?->avatar_url ?? '/img/main/default-avatar.png',
            ]);

        return view('chats.create', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'chat_type' => 'required|string|in:group,personal,channel',
            'name'      => 'required_if:chat_type,group|required_if:chat_type,channel|nullable|string|max:255',
            'users'     => 'nullable|string',
            'encrypted_keys' => 'required|array',
            'encrypted_keys.*.encrypted_key' => 'required|string',
            'encrypted_keys.*.nonce' => 'required|string',
        ]);

        $userIds = array_map('intval', json_decode($validated['users'] ?? '[]', true) ?: []);
        $initiatorId = Auth::id();
        if (!in_array($initiatorId, $userIds)) {
            $userIds[] = $initiatorId;
        }

        $chat = Chat::create([
            'name' => $request->chat_type === 'personal' ? null : $request->name,
            'type' => $request->chat_type,
            'initiator_id' => $initiatorId,
        ]);

        $chat->users()->attach(array_unique($userIds), [
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $chat->users()->updateExistingPivot($initiatorId, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        foreach ($validated['encrypted_keys'] as $uid => $data) {
            ChatMemberKey::create([
                'chat_id' => $chat->id,
                'user_id' => $uid,
                'encrypted_key' => $data['encrypted_key'],
                'nonce' => $data['nonce'],
            ]);
        }

        return redirect()->route('chats.show', $chat->id);
    }

    public function show($chatId)
    {
        $userId = Auth::id();

        $chat = Chat::with('users.profile')->findOrFail($chatId);

        $sidebarChats = Chat::with('users.profile')
            ->withMax('messages', 'created_at')
            ->whereHas('participants', fn($query) => $query->where('users.id', $userId))
            ->orderByDesc('messages_max_created_at')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        $sidebarUnread = $this->getUnreadMessagesCount($sidebarChats);

        // Не загружаем сообщения и их IPFS payload до проверки членства.
        abort_unless(
            $chat->users()->where('users.id', $userId)->exists(),
            403,
            'You are not a member of this chat'
        );

        $otherUser = $chat->type === 'personal'
            ? $chat->users->first(fn($user) => $user->id !== $userId)
            : null;

        $topics = $chat->topics()
            ->orderBy('created_at')
            ->get();

        $currentMembership = $chat->users->firstWhere('id', $userId)?->pivot;
        $currentRole = $currentMembership?->role ?? 'member';
        $mutedUntil = $currentMembership?->muted_until;

        // Первоначально отображаем только последние 50 сообщений.
        // Более старые сообщения клиент сможет подгружать отдельными страницами.
        $messages = $chat->messages()
            ->with(['sender', 'attachments', 'reactions'])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        $chat->setRelation('messages', $messages);

        $firstUnreadMessage = \App\Models\Notification::where('user_id', $userId)
            ->whereHas('message', fn($query) => $query->where('chat_id', $chatId))
            ->where('is_read', false)
            ->with('message')
            ->orderBy('created_at', 'asc')
            ->first();

        $firstUnreadMessageId = $firstUnreadMessage?->message?->id;

        // Удаляем только собственные уведомления после проверки доступа.
        \App\Models\Notification::whereHas(
            'message',
            fn($query) => $query->where('chat_id', $chatId)
        )
            ->where('user_id', $userId)
            ->delete();

        return view('chats.show', compact(
            'chat',
            'otherUser',
            'firstUnreadMessageId',
            'topics',
            'currentRole',
            'mutedUntil',
            'sidebarChats',
            'sidebarUnread'
        ));
    }

    public function notifications()
    {
        $user = Auth::user();
        $notifications = $user->notifications()
            ->where('is_read', false)
            ->with('message.chat')
            ->get();

        $grouped = $notifications->groupBy(fn($n) => $n->message?->chat_id);
        $uniqueChats = collect();

        foreach ($grouped as $chatId => $group) {
            if ($chatId && $chat = $group->first()?->message?->chat) {
                $unreadCount = $chat->messages()
                    ->whereHas('notifications', fn($q) => $q->where('user_id', $user->id)->where('is_read', false))
                    ->count();
                $uniqueChats->push(['chat' => $chat, 'unread_count' => $unreadCount]);
            }
        }

        return view('chats.notifications', compact('uniqueChats'));
    }

    public function markAsRead($notificationId)
    {
        $notification = Auth::user()->notifications()->findOrFail($notificationId);
        $notification->delete();
        return redirect()->back();
    }

    public function createWithUser($userId)
    {
        $otherUser = User::findOrFail($userId);

        $chat = Chat::whereHas('users', fn($q) => $q->where('user_id', Auth::id()))
            ->whereHas('users', fn($q) => $q->where('user_id', $userId))
            ->withCount('users')
            ->having('users_count', 2)
            ->first();

        if ($chat) {
            return redirect()->route('chats.show', $chat->id);
        }

        $chat = Chat::create(['type' => 'personal']);
        $chat->users()->attach([Auth::id(), $userId]);
        return redirect()->route('chats.show', $chat->id);
    }

    public function myKey($chatId)
{
    $userId = auth()->id();

    // Находим запись ключа участника
    $record = \DB::table('chat_member_keys')
        ->where('chat_id', $chatId)
        ->where('user_id', $userId)
        ->first();

    if (!$record) {
        return response()->json(['error' => 'No key found'], 404);
    }

    // Получаем публичный ключ ИНИЦИАТОРА чата
    $chat = Chat::with('initiator.profile')->findOrFail($chatId);
    $initiatorPublicKey = $chat->initiator?->profile?->public_key;

    $keySenderPublicKey = $record->sender_public_key ?: $initiatorPublicKey;

    if (!$keySenderPublicKey) {
        Log::error("Публичный ключ отправителя ключа чата отсутствует", [
            'chat_id' => $chatId,
            'initiator_id' => $chat->initiator_id
        ]);
        return response()->json(['error' => 'Chat key sender public key missing'], 500);
    }

    return response()->json([
        'encrypted_key' => $record->encrypted_key,
        'nonce' => $record->nonce,
        'initiator_public_key' => $keySenderPublicKey,
    ]);
}

    /**
     * Добавление участника. Клиент передаёт ключ чата,
     * зашифрованный публичным ключом нового участника.
     */
    public function addParticipant(Request $request, Chat $chat)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'encrypted_key' => 'required|string',
            'nonce' => 'required|string',
        ]);

        $actor = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$actor || !in_array($actor->role ?? 'member', ['owner', 'admin'], true)) {
            abort(403);
        }

        if (in_array($chat->type, ['personal', 'saved'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Personal chats cannot have extra participants'], 422);
        }

        $senderPublicKey = Auth::user()->profile?->public_key;
        if (!$senderPublicKey) {
            return response()->json(['status' => 'error', 'message' => 'Your public key is not configured'], 422);
        }

        $userId = (int) $validated['user_id'];
        if ($chat->users()->where('users.id', $userId)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'User is already a participant'], 409);
        }

        DB::transaction(function () use ($chat, $userId, $validated, $senderPublicKey) {
            $chat->users()->attach($userId, [
                'role' => 'member',
                'joined_at' => now(),
            ]);

            ChatMemberKey::create([
                'chat_id' => $chat->id,
                'user_id' => $userId,
                'encrypted_key' => $validated['encrypted_key'],
                'nonce' => $validated['nonce'],
                'sender_public_key' => $senderPublicKey,
            ]);
        });

        return response()->json(['status' => 'success']);
    }

    /**
     * Исключение участника. Администратор не может исключить владельца
     * или другого администратора.
     */
    public function removeParticipant(Chat $chat, User $user)
    {
        if (in_array($chat->type, ['personal', 'saved'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Personal chat participants cannot be managed'], 422);
        }

        $actor = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', Auth::id())
            ->first();

        $target = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$actor || !$target || !in_array($actor->role ?? 'member', ['owner', 'admin'], true)) {
            abort(403);
        }

        if (($target->role ?? 'member') === 'owner') {
            return response()->json(['status' => 'error', 'message' => 'The owner cannot be removed'], 422);
        }

        if (($actor->role ?? 'member') === 'admin' && ($target->role ?? 'member') === 'admin') {
            abort(403);
        }

        DB::transaction(function () use ($chat, $user) {
            ChatMemberKey::where('chat_id', $chat->id)
                ->where('user_id', $user->id)
                ->delete();

            $chat->users()->detach($user->id);
        });

        return response()->json(['status' => 'success']);
    }

    /**
     * Назначение администратора или модератора доступно только владельцу.
     */
    public function updateParticipantRole(Request $request, Chat $chat, User $user)
    {
        if (in_array($chat->type, ['personal', 'saved'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Personal chat roles cannot be changed'], 422);
        }

        $validated = $request->validate([
            'role' => 'required|string|in:admin,moderator,member',
        ]);

        $actor = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', Auth::id())
            ->first();

        $target = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$actor || ($actor->role ?? 'member') !== 'owner' || !$target) {
            abort(403);
        }

        if (($target->role ?? 'member') === 'owner') {
            return response()->json(['status' => 'error', 'message' => 'The owner role cannot be changed'], 422);
        }

        DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', $user->id)
            ->update(['role' => $validated['role']]);

        return response()->json(['status' => 'success', 'role' => $validated['role']]);
    }

    /**
     * Настройка персонального отключения уведомлений для участника чата.
     * duration: 60, 480, 1440, 10080 или 43200 минут; null — включить уведомления.
     */
    public function updateMute(Request $request, Chat $chat)
    {
        $validated = $request->validate([
            'duration' => 'present|nullable|integer|in:60,480,1440,10080,43200',
        ]);

        $membership = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', Auth::id())
            ->first();

        abort_unless($membership, 403);

        $mutedUntil = isset($validated['duration'])
            ? now()->addMinutes((int) $validated['duration'])
            : null;

        DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', Auth::id())
            ->update(['muted_until' => $mutedUntil]);

        return response()->json([
            'status' => 'success',
            'muted_until' => $mutedUntil?->toIso8601String(),
        ]);
    }

    /**
     * Возвращает список участников чата для подсказок @упоминаний.
     */
    public function mentionUsers(Chat $chat)
    {
        abort_unless(
            $chat->users()->where('users.id', Auth::id())->exists(),
            403
        );

        $users = $chat->users()
            ->where('users.id', '!=', Auth::id())
            ->with('profile')
            ->get(['users.id', 'users.name']);

        return response()->json($users->map(fn($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'nickname' => $user->profile?->nickname,
        ])->values());
    }

    /**
     * Создание темы внутри группового чата или канала.
     */
    public function createTopic(Request $request, Chat $chat)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
        ]);

        $actor = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', Auth::id())
            ->first();

        if (
            !$actor
            || !in_array($actor->role ?? 'member', ['owner', 'admin', 'moderator'], true)
            || $chat->type === 'personal'
        ) {
            abort(403);
        }

        $topic = $chat->topics()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'status' => 'success',
            'topic' => $topic,
        ], 201);
    }

}