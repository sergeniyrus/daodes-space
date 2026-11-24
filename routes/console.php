<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;
use App\Services\IPFSService;

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
