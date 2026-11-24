<?php

namespace App\Jobs;

use App\Exceptions\FirebaseMessagingException;
use App\Models\UserPushDevice;
use App\Services\FirebaseMessagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendChatMeetingReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 180];

    public function __construct(
        public readonly int $participantId,
        public readonly int $deviceId
    ) {
    }

    public function handle(FirebaseMessagingService $messaging): void
    {
        $device = UserPushDevice::query()->find($this->deviceId);

        if (!$device || !$device->is_active) {
            return;
        }

        $reminder = DB::table('chat_meeting_participants as participants')
            ->join('chat_meetings as meetings', 'meetings.id', '=', 'participants.meeting_id')
            ->where('participants.id', $this->participantId)
            ->where('participants.user_id', $device->user_id)
            ->where('participants.response', 'accepted')
            ->where('meetings.status', 'scheduled')
            ->where('meetings.starts_at', '>', now())
            ->first([
                'meetings.id as meeting_id',
                'meetings.chat_id',
            ]);

        if (!$reminder) {
            return;
        }

        try {
            $messaging->send(
                $device->push_token,
                'Скоро встреча DAODES',
                'Запланированная встреча начнётся в ближайшее время.',
                [
                    'url' => url('/chats/' . $reminder->chat_id),
                    'event_type' => 'meeting_reminder',
                    'meeting_id' => (string) $reminder->meeting_id,
                    'chat_id' => (string) $reminder->chat_id,
                ]
            );
        } catch (FirebaseMessagingException $exception) {
            if ($exception->isUnregisteredToken()) {
                $device->update(['is_active' => false]);
                return;
            }

            throw $exception;
        }

        $device->update(['last_seen_at' => now()]);
    }
}
