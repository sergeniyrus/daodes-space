<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

require_once base_path('ai/engine/approval_queue.php');
require_once base_path('ai/engine/apply_patch.php');

class AIController extends Controller
{
    public function patches(): JsonResponse
    {
        return response()->json(\ApprovalQueue::load());
    }

    public function approve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:100'],
        ]);

        $id = $validated['id'];
        $item = \ApprovalQueue::find($id);

        if (!$item) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Патч не найден.',
            ], 404);
        }

        if (($item['status'] ?? null) !== 'pending') {
            return response()->json([
                'status' => 'conflict',
                'message' => 'Патч уже обработан или находится в обработке.',
            ], 409);
        }

        if (!\ApprovalQueue::updateStatus($id, 'applying', 'pending')) {
            return response()->json([
                'status' => 'conflict',
                'message' => 'Патч уже забрал другой процесс.',
            ], 409);
        }

        try {
            if (!isset($item['patch']) || !is_array($item['patch'])) {
                throw new RuntimeException('Некорректная структура патча в очереди.');
            }

            \PatchApplier::applyApproved($item['patch']);

            \ApprovalQueue::updateStatus($id, 'applied', 'applying');

            return response()->json([
                'status' => 'applied',
                'message' => 'Патч успешно проверен и применён.',
            ]);
        } catch (\Throwable $exception) {
            \ApprovalQueue::updateStatus($id, 'failed', 'applying');

            report($exception);

            return response()->json([
                'status' => 'failed',
                'message' => 'Патч не применён: ' . $exception->getMessage(),
            ], 422);
        }
    }

    public function reject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:100'],
        ]);

        $updated = \ApprovalQueue::updateStatus(
            $validated['id'],
            'rejected',
            'pending'
        );

        if (!$updated) {
            return response()->json([
                'status' => 'conflict',
                'message' => 'Патч не найден или уже обработан.',
            ], 409);
        }

        return response()->json([
            'status' => 'rejected',
            'message' => 'Патч отклонён.',
        ]);
    }
}
