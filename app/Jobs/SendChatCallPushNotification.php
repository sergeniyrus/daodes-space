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
use Throwable;

class SendChatCallPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 30, 120];

    public function __construct(
        public readonly int $deviceId,
        public readonly int $callId
    ) {
    }

    public function handle(FirebaseMessagingService $messaging): void
    {
        $device = UserPushDevice::query()->find($this->deviceId);

        if (!$device || !$device->is_active) {
            return;
        }

        $call = DB::table('chat_calls')
            ->where('id', $this->callId)
            ->where('callee_id', $device->user_id)
            ->where('status', 'ringing')
            ->first();

        // Не отправляем уже неактуальный вызов, если очередь обработала job поздно.
        if (!$call) {
            return;
        }

        try {
            $title = $call->call_type === 'video'
                ? 'Входящий видеозвонок'
                : 'Входящий аудиозвонок';

            $messaging->send(
                $device->push_token,
                $title,
                'Вам звонят в DAODES',
                [
                    'url' => url('/chats/' . $call->chat_id),
                    'event_type' => 'incoming_call',
                    'call_id' => (string) $call->id,
                    'chat_id' => (string) $call->chat_id,
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

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
