<?php
// app/Http/Controllers/MessageController.php

namespace App\Http\Controllers;

use App\Events\ChatMessageCreated;
use App\Models\Message;
use App\Models\ChatUpload;
use App\Models\MessageAttachment;
use App\Models\MessageReaction;
use App\Models\Chat;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\TelegramNotifier;
use App\Services\IPFSService;

class MessageController extends Controller
{
    /**
     * TTL кэша для списка сообщений (в секундах)
     * Короткий TTL для активной переписки и быстрой синхронизации
     */
    private const MESSAGES_CACHE_TTL = 3;

    /**
     * TTL кэша для превью ответа
     */
    private const REPLY_INFO_CACHE_TTL = 60;

    /**
     * Получение списка сообщений чата
     * Кэшируем метаданные в Redis, контент тянем из IPFS
     */
    public function index(Chat $chat)
    {
        if (!$chat->users()->where('user_id', auth()->id())->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $lastId = (int) request('last_id', 0);
        $beforeId = (int) request('before_id', 0);
        $topicId = (int) request('topic_id', 0);

        if ($topicId > 0 && !$chat->topics()->whereKey($topicId)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Topic not found'], 404);
        }

        // Ключ кэша учитывает тему и направление пагинации.
        $cacheKey = "chat:{$chat->id}:messages:{$topicId}:{$lastId}:{$beforeId}";

        // Получаем метаданные из кэша или из БД
        $messagesMeta = Cache::remember($cacheKey, self::MESSAGES_CACHE_TTL, function () use ($chat, $lastId, $beforeId, $topicId) {
            $messageQuery = $chat->messages()
                ->when($topicId > 0, fn($query) => $query->where('topic_id', $topicId))
                ->with([
                    'sender:id,name',
                    'attachments:id,message_id,ipfs_cid,media_type,byte_size,position',
                    'reactions:id,message_id,user_id,reaction',
                    'mentions:id,message_id,user_id',
                ]);

            if ($beforeId > 0) {
                // Подгружаем предыдущую страницу истории при прокрутке вверх.
                $newMessages = $messageQuery
                    ->where('id', '<', $beforeId)
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get()
                    ->reverse()
                    ->values();
            } elseif ($lastId > 0) {
                $newMessages = $messageQuery
                    ->where('id', '>', $lastId)
                    ->orderBy('id', 'asc')
                    ->limit(100)
                    ->get();
            } else {
                // Начальная загрузка: последние 50 сообщений, а не вся история чата.
                $newMessages = $messageQuery
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get()
                    ->reverse()
                    ->values();
            }

            $recentlyEdited = $beforeId > 0
                ? collect()
                : $chat->messages()
                    ->when($topicId > 0, fn($query) => $query->where('topic_id', $topicId))
                    ->where('edited_at', 1)
                    ->where('updated_at', '>', now()->subMinutes(5))
                    ->with('sender:id,name')
                    ->get();

            return $newMessages->merge($recentlyEdited)->unique('id')->sortBy('id')->values();
        });

        // Контент тянем из IPFS (не кэшируем в Redis — он уже в IPFS!)
        $messages = $messagesMeta->map(function ($msg) {
            try {
                $payload = $msg->getMessageFromIPFS($msg->ipfs_cid);
            } catch (\Exception $e) {
                Log::error("Failed to load message {$msg->id} from IPFS: " . $e->getMessage());
                $payload = $msg->ipfs_cid; // fallback — возвращаем сам CID
            }

            return [
                'id' => $msg->id,
                'sender' => [
                    'id' => $msg->sender->id,
                    'name' => $msg->sender->name,
                ],
                'message' => $payload,
                'ipfs_cid' => $msg->ipfs_cid,
                'reply_to_message_id' => $msg->reply_to_message_id,
                'topic_id' => $msg->topic_id,
                'message_type' => $msg->message_type ?? 'text',
                'attachments' => $msg->attachments->map(fn($attachment) => [
                    'id' => $attachment->id,
                    'media_type' => $attachment->media_type,
                    'byte_size' => $attachment->byte_size,
                    'position' => $attachment->position,
                ])->values(),
                'reactions' => $msg->reactions->map(fn($reaction) => [
                    'user_id' => $reaction->user_id,
                    'reaction' => $reaction->reaction,
                ])->values(),
                'mentions' => $msg->mentions->pluck('user_id')->values(),
                'created_at' => $msg->created_at->toIso8601String(),
                'is_edited' => $msg->edited_at == 1,
            ];
        })->sortBy('id')->values();

        return response()->json($messages);
    }

    /**
     * Отправка нового сообщения
     */
    public function store(Request $request, Chat $chat)
    {
        $membership = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$membership) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        if (
            $chat->type === 'channel'
            && !in_array($membership->role ?? 'member', ['owner', 'admin', 'moderator'], true)
        ) {
            return response()->json(['status' => 'error', 'message' => 'Only channel moderators can publish'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string',
            'nonce'   => 'required|string',
            'reply_to_message_id' => 'nullable|integer|exists:messages,id',
            'topic_id' => 'nullable|integer|exists:chat_topics,id',
            'message_type' => 'nullable|string|in:text,voice,media,file,poll',
            'poll_is_anonymous' => 'required_if:message_type,poll|boolean',
            'poll_allows_multiple' => 'required_if:message_type,poll|boolean',
            'attachment_upload_ids' => 'nullable|array|max:10',
            'attachment_upload_ids.*' => 'required|integer|distinct|exists:chat_uploads,id',
            'mentioned_user_ids' => 'nullable|array|max:50',
            'mentioned_user_ids.*' => 'required|integer|distinct|exists:users,id',
        ]);

        try {
            $mentionedUserIds = collect($validated['mentioned_user_ids'] ?? [])
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values();

            if ($mentionedUserIds->isNotEmpty()) {
                $validMentionIds = $chat->users()
                    ->whereIn('users.id', $mentionedUserIds)
                    ->pluck('users.id')
                    ->map(fn($id) => (int) $id);

                if ($validMentionIds->count() !== $mentionedUserIds->count()) {
                    return response()->json(['status' => 'error', 'message' => 'Mentions must target chat participants'], 422);
                }
            }

            // Проверяем тему до загрузки, чтобы не создавать недоступный orphan CID.
            $topicId = null;
            if (!empty($request->topic_id)) {
                $topicExists = $chat->topics()->whereKey($request->topic_id)->exists();
                if (!$topicExists) {
                    return response()->json(['status' => 'error', 'message' => 'Invalid topic'], 422);
                }
                $topicId = (int) $request->topic_id;
            }

            $uploadIds = $validated['attachment_upload_ids'] ?? [];
            $uploads = collect();

            if (!empty($uploadIds)) {
                $uploads = ChatUpload::query()
                    ->whereIn('id', $uploadIds)
                    ->where('chat_id', $chat->id)
                    ->where('user_id', Auth::id())
                    ->where('expires_at', '>', now())
                    ->get();

                if ($uploads->count() !== count($uploadIds)) {
                    return response()->json(['status' => 'error', 'message' => 'Invalid or expired attachment upload'], 422);
                }
            }

            $messageType = $validated['message_type'] ?? 'text';

            if ($uploads->isNotEmpty() && !in_array($messageType, ['voice', 'media', 'file'], true)) {
                return response()->json(['status' => 'error', 'message' => 'Attachment message type is required'], 422);
            }

            if ($messageType === 'poll' && $uploads->isNotEmpty()) {
                return response()->json(['status' => 'error', 'message' => 'Poll messages cannot contain file attachments'], 422);
            }

            if ($messageType === 'media' && $uploads->contains(fn($upload) => !in_array($upload->media_type, ['image', 'video'], true))) {
                return response()->json(['status' => 'error', 'message' => 'Media messages support images and videos'], 422);
            }

            if ($messageType === 'voice' && (
                $uploads->count() !== 1
                || $uploads->first()->media_type !== 'audio'
            )) {
                return response()->json(['status' => 'error', 'message' => 'Voice messages require one audio attachment'], 422);
            }

            if (in_array($messageType, ['media', 'file'], true) && $uploads->isEmpty()) {
                return response()->json(['status' => 'error', 'message' => 'Attachment is required'], 422);
            }

            $fullPayload = $request->nonce . '|' . $request->message;
            $cid = (new Message())->uploadMessageToIPFS($fullPayload);

            // Проверяем, что reply_to_message_id из того же чата
            $replyToId = null;
            if ($request->reply_to_message_id) {
                $replyMessage = Message::where('id', $request->reply_to_message_id)
                    ->where('chat_id', $chat->id)
                    ->first();
                if ($replyMessage) {
                    $replyToId = $replyMessage->id;
                }
            }

            $message = DB::transaction(function () use ($chat, $cid, $replyToId, $topicId, $validated, $uploads, $uploadIds, $mentionedUserIds, $messageType) {
                $message = Message::create([
                    'chat_id' => $chat->id,
                    'sender_id' => Auth::id(),
                    'ipfs_cid' => $cid,
                    'reply_to_message_id' => $replyToId,
                    'topic_id' => $topicId,
                    'message_type' => $messageType,
                ]);

                if ($messageType === 'poll') {
                    DB::table('chat_polls')->insert([
                        'message_id' => $message->id,
                        'is_anonymous' => (bool) $validated['poll_is_anonymous'],
                        'allows_multiple' => (bool) $validated['poll_allows_multiple'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                foreach ($uploads as $position => $upload) {
                    $message->attachments()->create([
                        'ipfs_cid' => $upload->ipfs_cid,
                        'media_type' => $upload->media_type,
                        'byte_size' => $upload->byte_size,
                        'position' => $position,
                    ]);
                }

                if ($mentionedUserIds->isNotEmpty()) {
                    foreach ($mentionedUserIds as $mentionedUserId) {
                        $message->mentions()->create(['user_id' => $mentionedUserId]);
                    }
                }

                if (!empty($uploadIds)) {
                    ChatUpload::whereIn('id', $uploadIds)->delete();
                }

                return $message;
            });

            // Инвалидируем кэш чата — список сообщений изменился
            $this->invalidateChatCache($chat->id);

            // Уведомления получают только участники, у которых сейчас не действует mute.
            // Настройка персональная: она не влияет на остальных участников чата.
            $recipientIds = DB::table('chat_user')
                ->where('chat_id', $chat->id)
                ->where('user_id', '!=', Auth::id())
                ->where(function ($query) {
                    $query->whereNull('muted_until')
                        ->orWhere('muted_until', '<=', now());
                })
                ->pluck('user_id');

            if ($recipientIds->isNotEmpty()) {
                $notifications = $recipientIds->map(fn($id) => [
                    'user_id' => $id,
                    'message_id' => $message->id,
                    'is_read' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->toArray();
                Notification::insert($notifications);
            }

            // Telegram-уведомления
            $sender = Auth::user();
            $baseUrl = config('app.url');
            $chatUrl = "{$baseUrl}/chats/{$chat->id}";
            $miniAppUrl = "https://t.me/DAODES_Robot/DAODES_Dapp?chat_id={$chat->id}";
            $isPersonal = $chat->type === 'personal';

            foreach ($recipientIds as $recipientId) {
                $payload = [
                    'is_personal' => $isPersonal,
                    'sender_login' => $sender->name,
                    'chat_name' => $isPersonal ? 'личный чат' : $chat->name,
                    'chat_url' => $chatUrl,
                    'mini_app_url' => $miniAppUrl,
                ];
                TelegramNotifier::notifyNewMessage($recipientId, $payload);
            }

            // Отправляем только метаданные события после сохранения сообщения.
            // Если Reverb временно недоступен, само сообщение всё равно считается отправленным.
            try {
                broadcast(new ChatMessageCreated(
                    chatId: (int) $chat->id,
                    messageId: (int) $message->id,
                    senderId: (int) $message->sender_id,
                    topicId: $message->topic_id ? (int) $message->topic_id : null,
                    createdAt: $message->created_at->toIso8601String(),
                ));
            } catch (\Throwable $broadcastException) {
                Log::warning('Chat message broadcast failed', [
                    'chat_id' => $chat->id,
                    'message_id' => $message->id,
                    'error' => $broadcastException->getMessage(),
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => [
                    'id' => $message->id,
                    'sender' => $sender->name,
                    'ipfs_cid' => $message->ipfs_cid,
                    'reply_to_message_id' => $message->reply_to_message_id,
                    'topic_id' => $message->topic_id,
                    'message_type' => $message->message_type,
                    'attachments' => $message->attachments()->get(['id', 'media_type', 'byte_size']),
                    'created_at' => now()->toDateTimeString(),
                    'is_edited' => false,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка отправки: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }


    /**
     * Загружает уже зашифрованное на клиенте вложение в IPFS.
     * Временная запись привязана к чату и автору и может быть использована один раз.
     */
    public function uploadAttachment(Request $request, Chat $chat, IPFSService $ipfs)
    {
        $membership = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', Auth::id())
            ->first();

        if (
            !$membership
            || (
                $chat->type === 'channel'
                && !in_array($membership->role ?? 'member', ['owner', 'admin', 'moderator'], true)
            )
        ) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'file' => 'required|file|max:51200',
            'media_type' => 'required|string|in:image,video,audio,file',
            'encrypted' => 'required|accepted',
        ]);

        ChatUpload::where('expires_at', '<=', now())->delete();

        try {
            $file = $request->file('file');
            $cid = $ipfs->uploadFile($file);

            $upload = ChatUpload::create([
                'chat_id' => $chat->id,
                'user_id' => Auth::id(),
                'ipfs_cid' => $cid,
                'media_type' => $validated['media_type'],
                'byte_size' => $file->getSize(),
                'expires_at' => now()->addHours(2),
            ]);

            return response()->json([
                'status' => 'success',
                'upload_id' => $upload->id,
                'media_type' => $upload->media_type,
                'byte_size' => $upload->byte_size,
            ], 201);
        } catch (\Throwable $exception) {
            Log::error('Encrypted chat attachment upload failed', [
                'chat_id' => $chat->id,
                'user_id' => Auth::id(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Attachment upload failed'], 503);
        }
    }

    /**
     * Выдаёт зашифрованные байты вложения только участнику исходного чата.
     */
    public function downloadAttachment(Chat $chat, Message $message, MessageAttachment $attachment, IPFSService $ipfs)
    {
        if (
            $message->chat_id !== $chat->id
            || $attachment->message_id !== $message->id
            || !$chat->users()->where('user_id', Auth::id())->exists()
        ) {
            abort(404);
        }

        try {
            $content = $ipfs->downloadFile($attachment->ipfs_cid);

            return response($content, 200, [
                'Content-Type' => 'application/octet-stream',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Chat attachment retrieval failed', [
                'chat_id' => $chat->id,
                'message_id' => $message->id,
                'attachment_id' => $attachment->id,
            ]);

            return response()->json(['status' => 'error', 'message' => 'Attachment unavailable'], 503);
        }
    }

    /**
     * Редактирование сообщения
     */
    public function update(Request $request, Message $message)
    {
        $request->validate([
            'message' => 'required|string',
            'nonce'   => 'required|string',
        ]);

        if (!$message->chat || !$message->chat->users()->where('user_id', Auth::id())->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        if ($message->sender_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $newPayload = $request->nonce . '|' . $request->message;
            $newCid = (new Message())->uploadMessageToIPFS($newPayload);

            $message->update([
                'ipfs_cid' => $newCid,
                'edited_at' => 1,
            ]);

            // Инвалидируем кэш чата
            $this->invalidateChatCache($message->chat_id);

            return response()->json([
                'status' => 'success',
                'message' => [
                    'id' => $message->id,
                    'sender' => Auth::user()->name,
                    'ipfs_cid' => $message->ipfs_cid,
                    'reply_to_message_id' => $message->reply_to_message_id,
                    'created_at' => $message->created_at->toDateTimeString(),
                    'is_edited' => true,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка редактирования: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Удаление сообщения
     */
    public function destroy(Message $message)
    {
        $membership = $message->chat
            ? DB::table('chat_user')
                ->where('chat_id', $message->chat_id)
                ->where('user_id', Auth::id())
                ->first()
            : null;

        if (!$membership) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $canModerate = in_array($membership->role ?? 'member', ['owner', 'admin', 'moderator'], true);
        if ($message->sender_id !== Auth::id() && !$canModerate) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $chatId = $message->chat_id;
        $message->delete();

        // Инвалидируем кэш чата
        $this->invalidateChatCache($chatId);

        return response()->json(['status' => 'success']);
    }

    /**
     * Закрепляет сообщение в чате. Содержимое остаётся зашифрованным в IPFS.
     */
    public function pin(Message $message)
    {
        $chat = $message->chat;
        $membership = DB::table('chat_user')
            ->where('chat_id', $message->chat_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$chat || !$membership) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        if ($chat->type !== 'personal'
            && !in_array($membership->role ?? 'member', ['owner', 'admin', 'moderator'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Only chat moderators can pin messages'], 403);
        }

        $nextPosition = (int) DB::table('chat_pinned_messages')
            ->where('chat_id', $chat->id)
            ->max('position') + 1;

        DB::table('chat_pinned_messages')->insertOrIgnore([
            'chat_id' => $chat->id,
            'message_id' => $message->id,
            'pinned_by' => Auth::id(),
            'position' => $nextPosition,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'pinned' => true,
            'message_id' => $message->id,
        ]);
    }

    /**
     * Снимает закрепление. Удалять закрепления могут модераторы и автор закрепления.
     */
    public function unpin(Message $message)
    {
        $membership = DB::table('chat_user')
            ->where('chat_id', $message->chat_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$membership) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $pinned = DB::table('chat_pinned_messages')
            ->where('chat_id', $message->chat_id)
            ->where('message_id', $message->id)
            ->first();

        if (!$pinned) {
            return response()->json(['status' => 'success', 'pinned' => false]);
        }

        $canModerate = $message->chat?->type === 'personal'
            || in_array($membership->role ?? 'member', ['owner', 'admin', 'moderator'], true);
        if (!$canModerate && (int) $pinned->pinned_by !== (int) Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        DB::table('chat_pinned_messages')
            ->where('chat_id', $message->chat_id)
            ->where('message_id', $message->id)
            ->delete();

        return response()->json([
            'status' => 'success',
            'pinned' => false,
            'message_id' => $message->id,
        ]);
    }

    /**
     * Возвращает список закреплений без выдачи CID или зашифрованного содержимого.
     */
    public function pinnedMessages(Chat $chat)
    {
        $membership = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', Auth::id())
            ->first();

        abort_unless($membership, 403);

        $canManagePins = $chat->type === 'personal'
            || in_array($membership->role ?? 'member', ['owner', 'admin', 'moderator'], true);

        $items = DB::table('chat_pinned_messages as pins')
            ->join('messages', 'messages.id', '=', 'pins.message_id')
            ->join('users', 'users.id', '=', 'messages.sender_id')
            ->where('pins.chat_id', $chat->id)
            ->orderBy('pins.position')
            ->orderByDesc('pins.created_at')
            ->limit(100)
            ->get([
                'messages.id as message_id',
                'messages.topic_id',
                'users.name as sender_name',
                'pins.pinned_by',
                'pins.position',
                'pins.created_at as pinned_at',
            ])
            ->map(function ($item) use ($membership, $canManagePins) {
                $item->can_unpin = $canManagePins || (int) $item->pinned_by === (int) Auth::id();
                return $item;
            });

        return response()->json($items);
    }

    /**
     * Сохраняет пользовательский порядок закреплённых сообщений.
     * Изменять порядок могут владельцы, администраторы и модераторы чата.
     */
    public function reorderPinnedMessages(Request $request, Chat $chat)
    {
        $membership = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', Auth::id())
            ->first();

        abort_unless($membership, 403);

        $canManagePins = $chat->type === 'personal'
            || in_array($membership->role ?? 'member', ['owner', 'admin', 'moderator'], true);

        abort_unless($canManagePins, 403);

        $validated = $request->validate([
            'message_ids' => 'required|array|min:1|max:100',
            'message_ids.*' => 'required|integer|distinct',
        ]);

        $messageIds = array_map('intval', $validated['message_ids']);
        $pinnedQuery = DB::table('chat_pinned_messages')->where('chat_id', $chat->id);
        $pinnedCount = (clone $pinnedQuery)->count();
        $matchedCount = (clone $pinnedQuery)->whereIn('message_id', $messageIds)->count();

        if (count($messageIds) !== $pinnedCount || $matchedCount !== $pinnedCount) {
            return response()->json([
                'status' => 'error',
                'message' => 'The order must contain every pinned message exactly once',
            ], 422);
        }

        DB::transaction(function () use ($chat, $messageIds) {
            foreach ($messageIds as $position => $messageId) {
                DB::table('chat_pinned_messages')
                    ->where('chat_id', $chat->id)
                    ->where('message_id', $messageId)
                    ->update([
                        'position' => $position,
                        'updated_at' => now(),
                    ]);
            }
        });

        return response()->json(['status' => 'success']);
    }

    /**
     * Возвращает агрегированные результаты голосования без раскрытия IPFS CID.
     */
    public function pollResults(Message $message)
    {
        abort_unless($message->chat?->users()->where('users.id', Auth::id())->exists(), 403);

        $poll = DB::table('chat_polls')->where('message_id', $message->id)->first();
        abort_unless($poll, 404);

        return response()->json($this->buildPollResults($poll));
    }

    /**
     * Голосует за один или несколько вариантов. Для анонимного опроса
     * вместо user_id сохраняется HMAC-идентификатор участника.
     */
    public function votePoll(Request $request, Message $message)
    {
        abort_unless($message->chat?->users()->where('users.id', Auth::id())->exists(), 403);

        $poll = DB::table('chat_polls')->where('message_id', $message->id)->first();
        abort_unless($poll, 404);

        if ($poll->closed_at) {
            return response()->json(['status' => 'error', 'message' => 'Poll is closed'], 409);
        }

        $validated = $request->validate([
            'option_ids' => 'required|array|min:1|max:10',
            'option_ids.*' => 'required|integer|min:0|max:9|distinct',
        ]);

        $optionIds = array_values(array_unique(array_map('intval', $validated['option_ids'])));
        if (!$poll->allows_multiple && count($optionIds) !== 1) {
            return response()->json(['status' => 'error', 'message' => 'This poll accepts one answer'], 422);
        }

        $voterHash = $poll->is_anonymous
            ? hash_hmac('sha256', $poll->id . ':' . Auth::id(), (string) config('app.key'))
            : null;

        DB::transaction(function () use ($poll, $optionIds, $voterHash) {
            $votes = DB::table('chat_poll_votes')->where('poll_id', $poll->id);

            if ($poll->is_anonymous) {
                $votes->where('voter_hash', $voterHash);
            } else {
                $votes->where('user_id', Auth::id());
            }

            $votes->delete();

            foreach ($optionIds as $optionId) {
                DB::table('chat_poll_votes')->insert([
                    'poll_id' => $poll->id,
                    'user_id' => $poll->is_anonymous ? null : Auth::id(),
                    'voter_hash' => $voterHash,
                    'option_id' => $optionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json($this->buildPollResults($poll));
    }

    /**
     * Закрывает опрос его автором или модератором чата.
     */
    public function closePoll(Message $message)
    {
        $membership = DB::table('chat_user')
            ->where('chat_id', $message->chat_id)
            ->where('user_id', Auth::id())
            ->first();

        abort_unless($membership, 403);

        $poll = DB::table('chat_polls')->where('message_id', $message->id)->first();
        abort_unless($poll, 404);

        $canModerate = in_array($membership->role ?? 'member', ['owner', 'admin', 'moderator'], true);
        abort_unless($message->sender_id === Auth::id() || $canModerate, 403);

        DB::table('chat_polls')
            ->where('id', $poll->id)
            ->update(['closed_at' => now(), 'updated_at' => now()]);

        $poll->closed_at = now();

        return response()->json($this->buildPollResults($poll));
    }

    /**
     * Формирует только агрегаты и, для открытого опроса, имена проголосовавших.
     * В анонимном режиме сервер не возвращает личности участников.
     */
    private function buildPollResults(object $poll): array
    {
        $votes = DB::table('chat_poll_votes as votes')
            ->leftJoin('users', 'users.id', '=', 'votes.user_id')
            ->where('votes.poll_id', $poll->id)
            ->get([
                'votes.option_id',
                'votes.user_id',
                'votes.voter_hash',
                'users.name as voter_name',
            ]);

        $counts = $votes->groupBy('option_id')
            ->map(fn($items, $optionId) => [
                'option_id' => (int) $optionId,
                'count' => $items->count(),
            ])->values();

        $mine = $votes->filter(function ($vote) use ($poll) {
            return $poll->is_anonymous
                ? hash_equals((string) $vote->voter_hash, hash_hmac('sha256', $poll->id . ':' . Auth::id(), (string) config('app.key')))
                : (int) $vote->user_id === (int) Auth::id();
        })->pluck('option_id')->map(fn($id) => (int) $id)->values();

        $voters = [];
        if (!$poll->is_anonymous) {
            $voters = $votes->groupBy('option_id')
                ->map(fn($items) => $items->pluck('voter_name')->filter()->unique()->values())
                ->all();
        }

        $totalVoters = $poll->is_anonymous
            ? $votes->pluck('voter_hash')->filter()->unique()->count()
            : $votes->pluck('user_id')->filter()->unique()->count();

        return [
            'status' => 'success',
            'is_anonymous' => (bool) $poll->is_anonymous,
            'allows_multiple' => (bool) $poll->allows_multiple,
            'closed_at' => $poll->closed_at,
            'total_voters' => $totalVoters,
            'counts' => $counts,
            'my_option_ids' => $mine,
            'voters' => $voters,
        ];
    }

    /**
     * Переключение реакции участника на сообщении.
     */
    public function toggleReaction(Request $request, Message $message)
    {
        if (!$message->chat || !$message->chat->users()->where('user_id', Auth::id())->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'reaction' => 'required|string|in:👍,❤️,😂,🔥,👏,😮,😢,🎉',
        ]);

        $existing = MessageReaction::where('message_id', $message->id)
            ->where('user_id', Auth::id())
            ->where('reaction', $validated['reaction'])
            ->first();

        if ($existing) {
            $existing->delete();
            $active = false;
        } else {
            MessageReaction::create([
                'message_id' => $message->id,
                'user_id' => Auth::id(),
                'reaction' => $validated['reaction'],
            ]);
            $active = true;
        }

        $reactions = MessageReaction::where('message_id', $message->id)
            ->select('reaction', DB::raw('COUNT(*) as total'))
            ->groupBy('reaction')
            ->get()
            ->map(fn($item) => [
                'reaction' => $item->reaction,
                'count' => (int) $item->total,
                'mine' => MessageReaction::where('message_id', $message->id)
                    ->where('user_id', Auth::id())
                    ->where('reaction', $item->reaction)
                    ->exists(),
            ])
            ->values();

        return response()->json([
            'status' => 'success',
            'active' => $active,
            'reactions' => $reactions,
        ]);
    }

    /**
     * Получение информации о сообщении для превью ответа
     * Кэшируем в Redis, т.к. превью запрашивается часто
     */
    public function getReplyInfo(Message $message)
    {
        $isParticipant = $message->chat->users()
            ->where('user_id', Auth::id())
            ->exists();

        if (!$isParticipant) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden'
            ], 403);
        }

        // Кэшируем результат (метаданные + контент из IPFS)
        $cacheKey = "message:{$message->id}:reply_info";

        $data = Cache::remember($cacheKey, self::REPLY_INFO_CACHE_TTL, function () use ($message) {
            try {
                $payload = $message->getMessageFromIPFS($message->ipfs_cid);
            } catch (\Exception $e) {
                Log::error("Failed to load reply message {$message->id} from IPFS: " . $e->getMessage());
                return null;
            }

            return [
                'id' => $message->id,
                'sender' => [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                ],
                'content' => $payload,
            ];
        });

        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'IPFS unavailable'
            ], 503);
        }

        return response()->json([
            'status' => 'success',
            'message' => $data,
        ]);
    }

    /**
     * Отмечает входящие сообщения выбранного чата/темы как прочитанные
     * до указанного сообщения. Содержимое сообщений не затрагивается.
     */
    public function markRead(Request $request, Chat $chat)
    {
        abort_unless(
            $chat->users()->where('users.id', Auth::id())->exists(),
            403
        );

        $validated = $request->validate([
            'message_id' => 'required|integer|min:1',
            'topic_id' => 'nullable|integer',
        ]);

        $target = Message::query()
            ->where('chat_id', $chat->id)
            ->whereKey($validated['message_id'])
            ->first();

        if (!$target) {
            return response()->json(['status' => 'error', 'message' => 'Message not found in this chat'], 404);
        }

        $topicId = $validated['topic_id'] ?? null;
        if ($topicId !== null && !$chat->topics()->whereKey($topicId)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Topic not found'], 404);
        }

        if (($target->topic_id === null) !== ($topicId === null)
            || ($topicId !== null && (int) $target->topic_id !== (int) $topicId)) {
            return response()->json(['status' => 'error', 'message' => 'Message does not belong to this topic'], 422);
        }

        Message::query()
            ->where('chat_id', $chat->id)
            ->where('id', '<=', $target->id)
            ->where('sender_id', '!=', Auth::id())
            ->when(
                $topicId !== null,
                fn ($query) => $query->where('topic_id', $topicId),
                fn ($query) => $query->whereNull('topic_id')
            )
            ->orderBy('id')
            ->chunkById(500, function ($messages) {
                $now = now();
                $rows = $messages->map(fn ($message) => [
                    'message_id' => $message->id,
                    'user_id' => Auth::id(),
                    'read_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows) {
                    DB::table('message_reads')->insertOrIgnore($rows);
                }
            });

        return response()->json([
            'status' => 'success',
            'read_through' => $target->id,
        ]);
    }

    /**
     * Возвращает статусы прочтения для сообщений текущего пользователя,
     * которые сейчас отображаются в интерфейсе.
     */
    public function readReceipts(Request $request, Chat $chat)
    {
        abort_unless(
            $chat->users()->where('users.id', Auth::id())->exists(),
            403
        );

        $validated = $request->validate([
            'message_ids' => 'required|array|max:100',
            'message_ids.*' => 'required|integer|distinct',
        ]);

        $messageIds = Message::query()
            ->where('chat_id', $chat->id)
            ->where('sender_id', Auth::id())
            ->whereIn('id', $validated['message_ids'])
            ->pluck('id');

        if ($messageIds->isEmpty()) {
            return response()->json([]);
        }

        $receipts = DB::table('message_reads')
            ->whereIn('message_id', $messageIds)
            ->select(['message_id', 'user_id'])
            ->orderBy('message_id')
            ->get()
            ->groupBy('message_id')
            ->map(fn ($items, $messageId) => [
                'message_id' => (int) $messageId,
                'user_ids' => $items->pluck('user_id')->map(fn ($id) => (int) $id)->values(),
            ])
            ->values();

        return response()->json($receipts);
    }

    /**
     * Получение ID всех сообщений чата (для проверки удалений)
     * Лёгкий запрос — только ID, без IPFS
     */
    public function getMessageIds(Chat $chat)
    {
        if (!$chat->users()->where('user_id', auth()->id())->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $cacheKey = "chat:{$chat->id}:message_ids";

        $ids = Cache::remember($cacheKey, self::MESSAGES_CACHE_TTL, function () use ($chat) {
            return $chat->messages()->pluck('id')->toArray();
        });

        return response()->json($ids);
    }

    /**
     * Инвалидация всех кэшей, связанных с чатом
     * Вызывается при создании/редактировании/удалении сообщений
     */
    private function invalidateChatCache(int $chatId): void
    {
        // Удаляем кэш списка сообщений для всех last_id
        // Используем tags, если Redis настроен с поддержкой tags
        // Иначе — удаляем по известным ключам
        
        // Основная первая страница истории (общий поток).
        Cache::forget("chat:{$chatId}:messages:0:0:0");
        Cache::forget("chat:{$chatId}:messages:0");
        
        // Кэш ID сообщений
        Cache::forget("chat:{$chatId}:message_ids");
        
        // Можно также использовать паттерн, если Redis настроен:
        // Cache::tags(["chat:{$chatId}"])->flush();
    }
}