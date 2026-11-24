<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatGroupCallController extends Controller
{
    public function token(int $meeting): JsonResponse
    {
        $record = DB::table('chat_meetings as meetings')
            ->join('chats', 'chats.id', '=', 'meetings.chat_id')
            ->join('chat_user', 'chat_user.chat_id', '=', 'chats.id')
            ->join('chat_meeting_participants as participants', 'participants.meeting_id', '=', 'meetings.id')
            ->where('meetings.id', $meeting)
            ->where('participants.user_id', auth()->id())
            ->where('chat_user.user_id', auth()->id())
            ->where('participants.response', 'accepted')
            ->first([
                'meetings.id',
                'meetings.starts_at',
                'meetings.duration_minutes',
                'meetings.status',
                'chats.type as chat_type',
            ]);

        abort_unless($record, 403, 'Для участия необходимо принять приглашение.');

        abort_unless($record->chat_type === 'group', 422, 'Групповые звонки доступны только в групповых чатах.');

        abort_unless($record->status === 'scheduled', 409, 'Встреча отменена или завершена.');

        $startsAt = Carbon::parse($record->starts_at);
        $endsAt = $startsAt->copy()->addMinutes((int) $record->duration_minutes);

        abort_if($startsAt->gt(now()->addMinutes(10)), 409, 'Встреча ещё не началась.');

        abort_if($endsAt->lte(now()), 409, 'Время встречи истекло.');

        $serverUrl = (string) config('livekit.url');
        $apiKey = (string) config('livekit.api_key');
        $apiSecret = (string) config('livekit.api_secret');

        abort_unless(
            Str::startsWith($serverUrl, 'wss://') && $apiKey !== '' && $apiSecret !== '',
            503,
            'Сервис групповых звонков пока не настроен.'
        );

        $now = now()->timestamp;
        $expiresAt = min($endsAt->timestamp + 900, $now + 86400);
        $expiresAt = max($expiresAt, $now + 600);

        $room = 'daodes-meeting-' . (int) $record->id;
        $identity = 'user-' . (int) auth()->id();

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $apiKey,
            'sub' => $identity,
            'nbf' => $now - 5,
            'exp' => $expiresAt,
            'video' => [
                'roomJoin' => true,
                'room' => $room,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => true,
            ],
        ], JSON_THROW_ON_ERROR));

        $unsignedToken = $header . '.' . $payload;
        $signature = hash_hmac('sha256', $unsignedToken, $apiSecret, true);
        $token = $unsignedToken . '.' . $this->base64UrlEncode($signature);

        return response()->json([
            'server_url' => $serverUrl,
            'token' => $token,
            'room' => $room,
            'identity' => $identity,
            'expires_at' => Carbon::createFromTimestamp($expiresAt)->toIso8601String(),
            'e2ee_required' => true,
        ]);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
