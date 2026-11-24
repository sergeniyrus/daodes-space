<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;
use App\Services\IPFSService;
use App\Models\Message;
use App\Events\ChatMessageCreated;

// Команда, которая выводит вдохновляющую цитату
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Команда для обработки предложений на основе результатов голосования
// Artisan::command('offers:process', function () {
//     $this->call('offers:process');
// })->describe('Process offers based on voting results every minute');


// Автоматически удаляем истёкшие истории и снимаем pin с их зашифрованных файлов.
Schedule::call(function () {
    if (!Schema::hasTable('chat_stories')) {
        return;
    }

    $expiredStories = DB::table('chat_stories')
        ->where('expires_at', '<=', now())
        ->get(['id', 'ipfs_cid']);

    if ($expiredStories->isEmpty()) {
        return;
    }

    $ipfs = app(IPFSService::class);

    foreach ($expiredStories as $story) {
        try {
            $ipfs->unpinFile($story->ipfs_cid);
            DB::table('chat_stories')->where('id', $story->id)->delete();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
})->name('prune-expired-chat-stories')->hourly()->withoutOverlapping();

// Отправляем наступившие отложенные сообщения. В БД хранится только ciphertext и nonce.
Schedule::call(function () {
    if (!Schema::hasTable('chat_scheduled_messages')) {
        return;
    }

    // Возвращаем в очередь задачи, зависшие при аварийном завершении worker-процесса.
    DB::table('chat_scheduled_messages')
        ->where('status', 'processing')
        ->where('updated_at', '<', now()->subMinutes(10))
        ->update([
            'status' => 'pending',
            'scheduled_for' => now(),
            'updated_at' => now(),
        ]);

    $ids = DB::table('chat_scheduled_messages')
        ->where('status', 'pending')
        ->where('scheduled_for', '<=', now())
        ->orderBy('scheduled_for')
        ->limit(100)
        ->pluck('id');

    foreach ($ids as $id) {
        $scheduled = DB::transaction(function () use ($id) {
            $row = DB::table('chat_scheduled_messages')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$row || $row->status !== 'pending' || \Illuminate\Support\Carbon::parse($row->scheduled_for)->isFuture()) {
                return null;
            }

            DB::table('chat_scheduled_messages')
                ->where('id', $id)
                ->update([
                    'status' => 'processing',
                    'attempts' => DB::raw('attempts + 1'),
                    'updated_at' => now(),
                ]);

            return $row;
        });

        if (!$scheduled) {
            continue;
        }

        $senderStillMember = DB::table('chat_user')
            ->where('chat_id', $scheduled->chat_id)
            ->where('user_id', $scheduled->sender_id)
            ->exists();

        if (!$senderStillMember) {
            DB::table('chat_scheduled_messages')->where('id', $id)->update([
                'status' => 'cancelled',
                'last_error' => 'Отправитель больше не состоит в чате.',
                'updated_at' => now(),
            ]);
            continue;
        }

        try {
            $cid = (new Message())->uploadMessageToIPFS($scheduled->nonce . '|' . $scheduled->encrypted_message);

            $message = DB::transaction(function () use ($scheduled, $cid, $id) {
                $message = Message::create([
                    'chat_id' => $scheduled->chat_id,
                    'sender_id' => $scheduled->sender_id,
                    'ipfs_cid' => $cid,
                    'reply_to_message_id' => $scheduled->reply_to_message_id,
                    'topic_id' => $scheduled->topic_id,
                    'message_type' => 'text',
                ]);

                DB::table('chat_scheduled_messages')
                    ->where('id', $id)
                    ->update([
                        'status' => 'sent',
                        'sent_message_id' => $message->id,
                        'last_error' => null,
                        'updated_at' => now(),
                    ]);

                return $message;
            });
        } catch (\Throwable $exception) {
            $attempts = (int) $scheduled->attempts + 1;
            DB::table('chat_scheduled_messages')
                ->where('id', $id)
                ->where('status', 'processing')
                ->update([
                    'status' => $attempts >= 5 ? 'failed' : 'pending',
                    'scheduled_for' => $attempts >= 5 ? $scheduled->scheduled_for : now()->addMinutes(2),
                    'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                    'updated_at' => now(),
                ]);

            report($exception);
            continue;
        }

        // Внешние уведомления не должны переводить уже доставленное сообщение обратно в очередь.
        try {
            $recipientIds = DB::table('chat_user')
                ->where('chat_id', $scheduled->chat_id)
                ->where('user_id', '!=', $scheduled->sender_id)
                ->where(function ($query) {
                    $query->whereNull('muted_until')
                        ->orWhere('muted_until', '<=', now());
                })
                ->pluck('user_id');

            if ($recipientIds->isNotEmpty()) {
                $now = now();
                DB::table('notifications')->insert(
                    $recipientIds->map(fn ($userId) => [
                        'user_id' => $userId,
                        'message_id' => $message->id,
                        'is_read' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            }
        } catch (\Throwable $notificationException) {
            report($notificationException);
        }

        try {
            broadcast(new ChatMessageCreated(
                chatId: (int) $message->chat_id,
                messageId: (int) $message->id,
                senderId: (int) $message->sender_id,
                topicId: $message->topic_id ? (int) $message->topic_id : null,
                createdAt: $message->created_at->toIso8601String(),
            ));
        } catch (\Throwable $broadcastException) {
            report($broadcastException);
        }
    }
})->name('deliver-scheduled-chat-messages')->everyMinute()->withoutOverlapping(5);

