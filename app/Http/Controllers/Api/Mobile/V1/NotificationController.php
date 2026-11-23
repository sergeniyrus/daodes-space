<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\OrderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $query = OrderNotification::query()
            ->where('recipient_type', 'customer')
            ->where('recipient_id', $customer->id)
            ->where(function ($owned) use ($customer) {
                $owned->whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
                    ->orWhereHas('feedback', fn ($q) => $q->where('customer_id', $customer->id));
            });

        $unreadCount = (clone $query)->whereNull('read_at')->count();

        $notifications = $query->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (OrderNotification $notification) => [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
                'order_id' => $notification->order_id,
                'feedback_id' => $notification->feedback_id,
            ])
            ->values();

        return response()->json([
            'data' => $notifications,
            'unread_count' => $unreadCount,
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function markRead(Request $request, int $notification): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $owned = OrderNotification::query()
            ->whereKey($notification)
            ->where('recipient_type', 'customer')
            ->where('recipient_id', $customer->id)
            ->where(function ($query) use ($customer) {
                $query->whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
                    ->orWhereHas('feedback', fn ($q) => $q->where('customer_id', $customer->id));
            })
            ->firstOrFail();

        $owned->update(['read_at' => $owned->read_at ?? now()]);

        return response()->json(['marked_read' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $updated = OrderNotification::query()
            ->where('recipient_type', 'customer')
            ->where('recipient_id', $customer->id)
            ->whereNull('read_at')
            ->where(function ($query) use ($customer) {
                $query->whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
                    ->orWhereHas('feedback', fn ($q) => $q->where('customer_id', $customer->id));
            })
            ->update(['read_at' => now()]);

        return response()->json(['marked_read' => true, 'updated' => $updated, 'unread_count' => 0]);
    }
}
