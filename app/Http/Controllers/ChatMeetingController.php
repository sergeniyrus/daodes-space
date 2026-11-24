<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatMeetingController extends Controller
{
    public function index(Chat $chat)
    {
        $this->ensureMember($chat);

        $meetings = DB::table('chat_meetings as meetings')
            ->join('chat_meeting_participants as participants', 'participants.meeting_id', '=', 'meetings.id')
            ->where('meetings.chat_id', $chat->id)
            ->where('participants.user_id', Auth::id())
            ->whereIn('meetings.status', ['scheduled', 'completed'])
            ->where('meetings.starts_at', '>=', now()->subDay())
            ->orderBy('meetings.starts_at')
            ->get([
                'meetings.id',
                'meetings.chat_id',
                'meetings.organizer_id',
                'meetings.encrypted_title',
                'meetings.title_nonce',
                'meetings.encrypted_description',
                'meetings.description_nonce',
                'meetings.starts_at',
                'meetings.duration_minutes',
                'meetings.status',
                'participants.response',
            ]);

        return response()->json($meetings);
    }

    public function store(Request $request, Chat $chat)
    {
        $this->ensureMember($chat);

        $validated = $request->validate([
            'encrypted_title' => 'required|string|max:4000',
            'title_nonce' => 'required|string|max:255',
            'encrypted_description' => 'nullable|string|max:20000',
            'description_nonce' => 'nullable|string|max:255',
            'starts_at' => 'required|date|after:now',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'participant_ids' => 'nullable|array|max:100',
            'participant_ids.*' => 'integer|distinct',
        ]);

        $participantIds = collect($validated['participant_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->push((int) Auth::id())
            ->unique()
            ->values();

        $chatParticipantIds = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id);

        if ($participantIds->diff($chatParticipantIds)->isNotEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'Приглашать можно только участников этого чата.'], 422);
        }

        $startsAt = \Illuminate\Support\Carbon::parse($validated['starts_at']);
        if ($startsAt->lt(now()->addMinute()) || $startsAt->gt(now()->addYear())) {
            return response()->json(['status' => 'error', 'message' => 'Выберите время в пределах ближайшего года.'], 422);
        }

        $meetingId = DB::transaction(function () use ($chat, $validated, $participantIds, $startsAt) {
            $id = DB::table('chat_meetings')->insertGetId([
                'chat_id' => $chat->id,
                'organizer_id' => Auth::id(),
                'encrypted_title' => $validated['encrypted_title'],
                'title_nonce' => $validated['title_nonce'],
                'encrypted_description' => $validated['encrypted_description'] ?? null,
                'description_nonce' => $validated['description_nonce'] ?? null,
                'starts_at' => $startsAt,
                'duration_minutes' => $validated['duration_minutes'],
                'status' => 'scheduled',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $now = now();
            DB::table('chat_meeting_participants')->insert(
                $participantIds->map(fn ($userId) => [
                    'meeting_id' => $id,
                    'user_id' => $userId,
                    'response' => $userId === (int) Auth::id() ? 'accepted' : 'pending',
                    'responded_at' => $userId === (int) Auth::id() ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );

            return $id;
        });

        return response()->json(['status' => 'success', 'meeting_id' => $meetingId], 201);
    }

    public function respond(Request $request, int $meeting)
    {
        $validated = $request->validate([
            'response' => 'required|in:accepted,declined',
        ]);

        $participant = DB::table('chat_meeting_participants')
            ->where('meeting_id', $meeting)
            ->where('user_id', Auth::id())
            ->first();

        abort_unless($participant, 404);

        DB::table('chat_meeting_participants')
            ->where('meeting_id', $meeting)
            ->where('user_id', Auth::id())
            ->update([
                'response' => $validated['response'],
                'responded_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['status' => 'success']);
    }

    public function destroy(int $meeting)
    {
        $deleted = DB::table('chat_meetings')
            ->where('id', $meeting)
            ->where('organizer_id', Auth::id())
            ->where('status', 'scheduled')
            ->delete();

        abort_unless($deleted, 404);

        return response()->json(['status' => 'success']);
    }

    private function ensureMember(Chat $chat): void
    {
        abort_unless(
            DB::table('chat_user')
                ->where('chat_id', $chat->id)
                ->where('user_id', Auth::id())
                ->exists(),
            403
        );
    }
}
