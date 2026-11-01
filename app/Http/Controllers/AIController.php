<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

require_once base_path('ai/engine/approval_queue.php');

class AIController extends Controller
{
    /**
     * Получение очереди патчей.
     */
    public function patches()
    {
        try {

            return response()->json(
                \ApprovalQueue::load()
            );

        } catch (\Throwable $e) {

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Одобрение патча и запуск применения.
     */
    public function approve(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:80'],
        ]);

        $id = $validated['id'];

        try {

            // Одобряем только pending.
            \ApprovalQueue::transition(
                $id,
                'pending',
                'approved'
            );

        } catch (\Throwable $e) {

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 409);
        }

        try {

            $script = base_path('ai/engine/run_apply.php');

            if (!is_file($script)) {
                throw new \RuntimeException(
                    'AI apply script not found.'
                );
            }

            // Запускаем применение по ID, а не через временный diff-файл.
            $process = new Process([
                PHP_BINARY,
                $script,
                $id,
            ]);

            $process->setTimeout(180);

            $process->run();

            if (!$process->isSuccessful()) {

                return response()->json([
                    'status' => 'failed',
                    'message' => 'Patch application failed.',
                    'output' => $process->getOutput(),
                    'error' => $process->getErrorOutput(),
                    'patch_id' => $id,
                ], 500);
            }

            $item = \ApprovalQueue::find($id);

            return response()->json([
                'status' => $item['status'] ?? 'unknown',
                'message' => 'Patch processed successfully.',
                'patch_id' => $id,
                'output' => $process->getOutput(),
            ]);

        } catch (\Throwable $e) {

            // Если процесс не смог завершиться,
            // не оставляем патч в бесконечном состоянии approved/applying.
            try {

                $item = \ApprovalQueue::find($id);

                if ($item !== null) {

                    $currentStatus = $item['status'] ?? null;

                    if ($currentStatus === 'approved') {

                        \ApprovalQueue::transition(
                            $id,
                            'approved',
                            'failed'
                        );

                    } elseif ($currentStatus === 'applying') {

                        \ApprovalQueue::transition(
                            $id,
                            'applying',
                            'failed'
                        );
                    }
                }

            } catch (\Throwable $queueError) {

                report($queueError);
            }

            report($e);

            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
                'patch_id' => $id,
            ], 500);
        }
    }

    /**
     * Отклонение патча.
     */
    public function reject(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:80'],
        ]);

        $id = $validated['id'];

        try {

            // Отклонять можно только pending.
            \ApprovalQueue::transition(
                $id,
                'pending',
                'rejected'
            );

            return response()->json([
                'status' => 'rejected',
                'patch_id' => $id,
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 409);
        }
    }
}
