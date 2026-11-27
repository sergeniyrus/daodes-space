<?php

namespace App\Console\Commands;

use App\Jobs\SendChatMeetingReminder;
use App\Services\FirebaseMessagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SendChatMeetingReminders extends Command
{
    protected $signature = 'chat:send-meeting-reminders';

    protected $description = 'Queue push reminders for accepted participants of upcoming chat meetings';

    public function handle(FirebaseMessagingService $messaging): int
    {
        if (
            !Schema::hasTable('chat_meetings')
            || !Schema::hasTable('chat_meeting_participants')
            || !Schema::hasTable('user_push_devices')
            || !$messaging->isConfigured()
        ) {
            return self::SUCCESS;
        }

        $participants = DB::table('chat_meeting_participants as participants')
            ->join('chat_meetings as meetings', 'meetings.id', '=', 'participants.meeting_id')
            ->join('user_push_devices as devices', function ($join) {
                $join->on('devices.user_id', '=', 'participants.user_id')
                    ->where('devices.is_active', true);
            })
            ->where('participants.response', 'accepted')
            ->whereNull('participants.reminder_sent_at')
            ->where('meetings.status', 'scheduled')
            ->where('meetings.starts_at', '>', now())
            ->where('meetings.starts_at', '<=', now()->addMinutes(15))
            ->distinct()
            ->get(['participants.id', 'participants.meeting_id', 'participants.user_id']);

        foreach ($participants as $participant) {
            $deviceIds = DB::table('user_push_devices')
                ->where('user_id', $participant->user_id)
                ->where('is_active', true)
                ->pluck('id');

            if ($deviceIds->isEmpty()) {
                continue;
            }

            $claimed = DB::transaction(function () use ($participant) {
                $row = DB::table('chat_meeting_participants')
                    ->where('id', $participant->id)
                    ->lockForUpdate()
                    ->first();

                if (!$row || $row->response !== 'accepted' || $row->reminder_sent_at !== null) {
                    return false;
                }

                $meeting = DB::table('chat_meetings')
                    ->where('id', $row->meeting_id)
                    ->where('status', 'scheduled')
                    ->where('starts_at', '>', now())
                    ->where('starts_at', '<=', now()->addMinutes(15))
                    ->exists();

                if (!$meeting) {
                    return false;
                }

                DB::table('chat_meeting_participants')
                    ->where('id', $row->id)
                    ->update([
                        'reminder_sent_at' => now(),
                        'updated_at' => now(),
                    ]);

                return true;
            });

            if (!$claimed) {
                continue;
            }

            foreach ($deviceIds as $deviceId) {
                SendChatMeetingReminder::dispatch((int) $participant->id, (int) $deviceId);
            }
        }

        return self::SUCCESS;
    }
}
