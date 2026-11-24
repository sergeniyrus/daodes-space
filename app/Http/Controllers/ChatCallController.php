<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChatCallController extends Controller
{
    public function active(Chat $chat)
    {
        $this->ensurePersonalMember($chat);

        $call = DB::table('chat_calls')
            ->where('chat_id', $chat->id)
            ->whereIn('status', ['ringing', 'accepted'])
            ->where('created_at', '>', now()->subHours(2))
            ->where(function ($query) {
                $query->where('caller_id', Auth::id())
                    ->orWhere('callee_id', Auth::id());
            })
            ->orderByDesc('id')
            ->first();

        if ($call && $call->status === 'ringing' && \Illuminate\Support\Carbon::parse($call->created_at)->lt(now()->subSeconds(60))) {
            DB::table('chat_calls')->where('id', $call->id)->update([
                'status' => 'missed',
                'ended_at' => now(),
                'updated_at' => now(),
            ]);
            $call = null;
        }

        return response()->json([
            'call' => $call ? [
                'id' => (int) $call->id,
                'chat_id' => (int) $call->chat_id,
                'caller_id' => (int) $call->caller_id,
                'callee_id' => (int) $call->callee_id,
                'call_type' => $call->call_type,
                'status' => $call->status,
                'is_caller' => (int) $call->caller_id === (int) Auth::id(),
                'created_at' => $call->created_at,
            ] : null,
        ]);
    }

    public function start(Request $request, Chat $chat)
    {
        $this->ensurePersonalMember($chat);

        $validated = $request->validate([
            'call_type' => 'required|in:audio,video',
            'encrypted_offer' => 'required|string|max:50000',
        ]);

        $participantIds = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id);

        if ($participantIds->count() !== 2) {
            return response()->json(['status' => 'error', 'message' => 'Личный звонок доступен только в чате один на один.'], 422);
        }

        $calleeId = (int) $participantIds->first(fn ($id) => $id !== (int) Auth::id());

        if (Schema::hasTable('chat_contact_blocks')) {
            $blocked = DB::table('chat_contact_blocks')
                ->where(function ($query) use ($calleeId) {
                    $query->where('user_id', Auth::id())->where('blocked_user_id', $calleeId);
                })
                ->orWhere(function ($query) use ($calleeId) {
                    $query->where('user_id', $calleeId)->where('blocked_user_id', Auth::id());
                })
                ->exists();

            if ($blocked) {
                return response()->json(['status' => 'error', 'message' => 'Звонок недоступен: один из пользователей заблокировал другого.'], 403);
            }
        }

        $existing = DB::table('chat_calls')
            ->where('chat_id', $chat->id)
            ->whereIn('status', ['ringing', 'accepted'])
            ->where('created_at', '>', now()->subHours(2))
            ->exists();

        if ($existing) {
            return response()->json(['status' => 'error', 'message' => 'В этом чате уже есть активный звонок.'], 409);
        }

        $callId = DB::table('chat_calls')->insertGetId([
            'chat_id' => $chat->id,
            'caller_id' => Auth::id(),
            'callee_id' => $calleeId,
            'call_type' => $validated['call_type'],
            'status' => 'ringing',
            'last_activity_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('chat_call_signals')->insert([
            'call_id' => $callId,
            'sender_id' => Auth::id(),
            'signal_type' => 'offer',
            'encrypted_payload' => $validated['encrypted_offer'],
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'call' => [
                'id' => $callId,
                'call_type' => $validated['call_type'],
                'status' => 'ringing',
            ],
        ], 201);
    }

    public function signals(Request $request, int $call)
    {
        $callRow = $this->ensureCallParticipant($call);
        $afterId = max(0, (int) $request->query('after_id', 0));

        DB::table('chat_calls')->where('id', $call)->update([
            'last_activity_at' => now(),
            'updated_at' => now(),
        ]);

        $signals = DB::table('chat_call_signals')
            ->where('call_id', $call)
            ->where('sender_id', '!=', Auth::id())
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(100)
            ->get(['id', 'sender_id', 'signal_type', 'encrypted_payload', 'created_at']);

        return response()->json([
            'status' => $callRow->status,
            'signals' => $signals,
        ]);
    }

    public function signal(Request $request, int $call)
    {
        $callRow = $this->ensureCallParticipant($call);

        $validated = $request->validate([
            'signal_type' => 'required|in:answer,ice,reject,end',
            'encrypted_payload' => 'required_if:signal_type,answer,ice|nullable|string|max:50000',
        ]);

        if (!in_array($callRow->status, ['ringing', 'accepted'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Звонок уже завершён.'], 409);
        }

        if ($validated['signal_type'] === 'answer') {
            abort_unless((int) $callRow->callee_id === (int) Auth::id(), 403);
            abort_unless($callRow->status === 'ringing', 409);

            DB::table('chat_calls')->where('id', $call)->update([
                'status' => 'accepted',
                'last_activity_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (in_array($validated['signal_type'], ['end', 'reject'], true)) {
            if ($validated['signal_type'] === 'reject') {
                abort_unless((int) $callRow->callee_id === (int) Auth::id(), 403);
            }

            DB::table('chat_calls')->where('id', $call)->update([
                'status' => $validated['signal_type'] === 'reject' ? 'rejected' : 'ended',
                'ended_at' => now(),
                'last_activity_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('chat_calls')->where('id', $call)->update([
                'last_activity_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('chat_call_signals')->insert([
            'call_id' => $call,
            'sender_id' => Auth::id(),
            'signal_type' => $validated['signal_type'],
            'encrypted_payload' => $validated['encrypted_payload'] ?? null,
            'created_at' => now(),
        ]);

        return response()->json(['status' => 'success']);
    }

    private function ensurePersonalMember(Chat $chat): void
    {
        abort_unless($chat->type === 'personal', 422, 'Calls are only available in personal chats.');

        abort_unless(
            DB::table('chat_user')
                ->where('chat_id', $chat->id)
                ->where('user_id', Auth::id())
                ->exists(),
            403
        );
    }

    private function ensureCallParticipant(int $callId): object
    {
        $call = DB::table('chat_calls')->where('id', $callId)->first();
        abort_unless($call, 404);

        abort_unless(
            in_array((int) Auth::id(), [(int) $call->caller_id, (int) $call->callee_id], true),
            403
        );

        return $call;
    }
}
