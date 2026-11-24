<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatScheduledMessageController extends Controller
{
    public function index(Chat $chat)
    {
        $this->ensureMember($chat);

        $messages = DB::table('chat_scheduled_messages')
            ->where('chat_id', $chat->id)
            ->where('sender_id', Auth::id())
            ->whereIn('status', ['pending', 'failed'])
            ->orderBy('scheduled_for')
            ->get([
                'id', 'encrypted_message', 'nonce', 'topic_id', 'scheduled_for', 'status', 'last_error',
            ]);

        return response()->json($messages);
    }

    public function store(Request $request, Chat $chat)
    {
        $membership = $this->ensureMember($chat);

        abort_if(
            $chat->type === 'channel'
            && !in_array($membership->role ?? 'member', ['owner', 'admin', 'moderator'], true),
            403,
            'Only channel moderators can schedule messages.'
        );

        $validated = $request->validate([
            'encrypted_message' => 'required|string|max:100000',
            'nonce' => 'required|string|max:255',
            'scheduled_for' => 'required|date|after:now',
            'topic_id' => 'nullable|integer',
            'reply_to_message_id' => 'nullable|integer',
        ]);

        $scheduledFor = \Illuminate\Support\Carbon::parse($validated['scheduled_for']);
        if ($scheduledFor->lt(now()->addMinute()) || $scheduledFor->gt(now()->addYear())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Выберите время не менее чем через минуту и не более чем через год.',
            ], 422);
        }

        $topicId = $validated['topic_id'] ?? null;
        if ($topicId && !$chat->topics()->whereKey($topicId)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Тема не найдена.'], 422);
        }

        $replyToId = $validated['reply_to_message_id'] ?? null;
        if ($replyToId && !Message::where('chat_id', $chat->id)->whereKey($replyToId)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Сообщение для ответа не найдено в этом чате.'], 422);
        }

        $id = DB::table('chat_scheduled_messages')->insertGetId([
            'chat_id' => $chat->id,
            'sender_id' => Auth::id(),
            'topic_id' => $topicId,
            'reply_to_message_id' => $replyToId,
            'encrypted_message' => $validated['encrypted_message'],
            'nonce' => $validated['nonce'],
            'scheduled_for' => $scheduledFor,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'scheduled_message' => [
                'id' => $id,
                'scheduled_for' => $scheduledFor->toIso8601String(),
                'status' => 'pending',
            ],
        ], 201);
    }

    public function update(Request $request, Chat $chat, int $scheduledMessage)
    {
        $membership = $this->ensureMember($chat);

        abort_if(
            $chat->type === 'channel'
            && !in_array($membership->role ?? 'member', ['owner', 'admin', 'moderator'], true),
            403,
            'Only channel moderators can schedule messages.'
        );

        $validated = $request->validate([
            'encrypted_message' => 'required|string|max:100000',
            'nonce' => 'required|string|max:255',
            'scheduled_for' => 'required|date|after:now',
            'topic_id' => 'sometimes|nullable|integer',
            'reply_to_message_id' => 'sometimes|nullable|integer',
        ]);

        $scheduledFor = \\Illuminate\\Support\\Carbon::parse($validated['scheduled_for']);
        if ($scheduledFor->lt(now()->addMinute()) || $scheduledFor->gt(now()->addYear())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Выберите время не менее чем через минуту и не более чем через год.',
            ], 422);
        }

        $changes = [
            'encrypted_message' => $validated['encrypted_message'],
            'nonce' => $validated['nonce'],
            'scheduled_for' => $scheduledFor,
            'last_error' => null,
            'updated_at' => now(),
        ];

        if (array_key_exists('topic_id', $validated)) {
            $topicId = $validated['topic_id'];
            if ($topicId && !$chat->topics()->whereKey($topicId)->exists()) {
                return response()->json(['status' => 'error', 'message' => 'Тема не найдена.'], 422);
            }
            $changes['topic_id'] = $topicId;
        }

        if (array_key_exists('reply_to_message_id', $validated)) {
            $replyToId = $validated['reply_to_message_id'];
            if ($replyToId && !Message::where('chat_id', $chat->id)->whereKey($replyToId)->exists()) {
                return response()->json(['status' => 'error', 'message' => 'Сообщение для ответа не найдено в этом чате.'], 422);
            }
            $changes['reply_to_message_id'] = $replyToId;
        }

        $updated = DB::table('chat_scheduled_messages')
            ->where('id', $scheduledMessage)
            ->where('chat_id', $chat->id)
            ->where('sender_id', Auth::id())
            ->whereIn('status', ['pending', 'failed'])
            ->update($changes);

        abort_unless($updated, 404);

        return response()->json([
            'status' => 'success',
            'scheduled_message' => [
                'id' => $scheduledMessage,
                'scheduled_for' => $scheduledFor->toIso8601String(),
                'status' => 'pending',
            ],
        ]);
    }

    public function destroy(Chat $chat, int $scheduledMessage)
    {
        $this->ensureMember($chat);

        $deleted = DB::table('chat_scheduled_messages')
            ->where('id', $scheduledMessage)
            ->where('chat_id', $chat->id)
            ->where('sender_id', Auth::id())
            ->whereIn('status', ['pending', 'failed'])
            ->delete();

        abort_unless($deleted, 404);

        return response()->json(['status' => 'success']);
    }

    private function ensureMember(Chat $chat): object
    {
        $membership = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', Auth::id())
            ->first();

        abort_unless($membership, 403);

        return $membership;
    }
}
