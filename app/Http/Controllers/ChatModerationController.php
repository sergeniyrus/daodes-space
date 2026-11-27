<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatModerationController extends Controller
{
    private function membership(Chat $chat): object
    {
        abort_unless(in_array($chat->type, ['group', 'channel'], true), 404);

        $membership = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', Auth::id())
            ->first();

        abort_unless($membership, 403);

        return $membership;
    }

    private function canModerate(object $membership): bool
    {
        return in_array($membership->role ?? 'member', ['owner', 'admin', 'moderator'], true);
    }

    public function index(Chat $chat)
    {
        $membership = $this->membership($chat);
        abort_unless($this->canModerate($membership), 403);

        $reports = DB::table('chat_message_reports as reports')
            ->leftJoin('users as reporters', 'reporters.id', '=', 'reports.reporter_id')
            ->leftJoin('users as handlers', 'handlers.id', '=', 'reports.handled_by')
            ->where('reports.chat_id', $chat->id)
            ->orderByRaw("CASE reports.status WHEN 'new' THEN 0 WHEN 'reviewing' THEN 1 ELSE 2 END")
            ->orderByDesc('reports.created_at')
            ->limit(100)
            ->get([
                'reports.id',
                'reports.message_id',
                'reports.message_author_name',
                'reports.message_text',
                'reports.reason',
                'reports.status',
                'reports.created_at',
                'reports.handled_at',
                'reporters.name as reporter_name',
                'handlers.name as handler_name',
            ]);

        return response()->json([
            'status' => 'success',
            'reports' => $reports,
            'can_delete_messages' => true,
        ]);
    }

    public function store(Request $request, Chat $chat, Message $message)
    {
        $membership = $this->membership($chat);
        abort_unless((int) $message->chat_id === (int) $chat->id, 404);
        abort_unless((int) $message->sender_id !== (int) Auth::id(), 422, 'Нельзя пожаловаться на собственное сообщение.');

        $validated = $request->validate([
            'message_text' => 'required|string|max:10000',
            'reason' => 'required|string|in:spam,abuse,harassment,scam,inappropriate,other',
        ]);

        $inserted = DB::table('chat_message_reports')->insertOrIgnore([
            'chat_id' => $chat->id,
            'message_id' => $message->id,
            'reporter_id' => Auth::id(),
            'message_author_id' => $message->sender_id,
            'message_author_name' => $message->sender?->name ?? 'Пользователь',
            'message_text' => $validated['message_text'],
            'reason' => $validated['reason'],
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (!$inserted) {
            return response()->json([
                'status' => 'error',
                'message' => 'Вы уже отправили жалобу на это сообщение.',
            ], 409);
        }

        return response()->json(['status' => 'success']);
    }

    public function update(Request $request, Chat $chat, int $report)
    {
        $membership = $this->membership($chat);
        abort_unless($this->canModerate($membership), 403);

        $validated = $request->validate([
            'status' => 'required|string|in:reviewing,resolved,rejected',
        ]);

        $record = DB::table('chat_message_reports')
            ->where('chat_id', $chat->id)
            ->where('id', $report)
            ->first();

        abort_unless($record, 404);

        DB::table('chat_message_reports')
            ->where('id', $record->id)
            ->update([
                'status' => $validated['status'],
                'handled_by' => Auth::id(),
                'handled_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['status' => 'success']);
    }
}
