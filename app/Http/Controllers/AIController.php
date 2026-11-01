<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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

            report($e);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Одобрение патча.
     *
     * Фактическое применение выполняет локальный AI Runner.
     */
    public function approve(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:80'],
        ]);

        $id = $validated['id'];

        try {

            $item = \ApprovalQueue::transition(
                $id,
                'pending',
                'approved'
            );

            return response()->json([
                'status' => $item['status'],
                'message' => 'Patch approved. Waiting for local AI Runner.',
                'patch_id' => $id,
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 409);
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
