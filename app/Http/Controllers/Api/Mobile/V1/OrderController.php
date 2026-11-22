<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Support\OrderNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $orders = $customer->orders()
            ->with('organization')
            ->latest()
            ->paginate(min(50, max(1, (int) $request->query('per_page', 20))));

        return response()->json([
            'data' => $orders->getCollection()->map(fn (Order $order) => $this->payload($order))->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function show(Request $request, int $order): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $ownedOrder = $customer->orders()
            ->with(['organization', 'items', 'statusHistory', 'payments'])
            ->findOrFail($order);

        return response()->json(['data' => $this->payload($ownedOrder, true)])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function updates(Request $request, int $order): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $ownedOrder = $customer->orders()->with('statusHistory')->findOrFail($order);

        return response()->json([
            'status' => $ownedOrder->status,
            'payment_status' => $ownedOrder->payment_status,
            'updated_at' => $ownedOrder->updated_at?->toIso8601String(),
            'history' => $ownedOrder->statusHistory->map(fn ($history) => [
                'status' => $history->to_status,
                'created_at' => $history->created_at?->toIso8601String(),
            ])->values(),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function confirmPickup(Request $request, int $order): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $updated = app(\App\Services\OrderPickupConfirmationService::class)
            ->confirm($customer, $order);

        return response()->json(['data' => $this->payload($updated->load(['organization', 'items', 'statusHistory', 'payments']))]);
    }

    private function payload(Order $order, bool $details = false): array
    {
        $data = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'organization' => [
                'id' => $order->organization?->id,
                'name' => $order->organization?->name,
            ],
            'status' => $order->status,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'fulfillment_type' => $order->fulfillment_type,
            'requested_at' => $order->requested_at?->toIso8601String(),
            'subtotal' => $order->subtotal,
            'delivery_fee' => $order->delivery_fee,
            'discount_total' => $order->discount_total,
            'total' => $order->total,
            'currency' => $order->currency,
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'can_confirm_pickup' => $order->fulfillment_type === Order::FULFILLMENT_PICKUP
                && $order->status === Order::STATUS_READY,
        ];

        if (!$details) {
            return $data;
        }

        $data['customer_name'] = $order->customer_name;
        $data['customer_comment'] = $order->customer_comment;
        $data['delivery_address'] = $order->delivery_address;
        $data['pickup_address'] = $order->pickup_address_snapshot;
        $data['table_number'] = $order->table_number;
        $data['items'] = $order->items->map(fn ($item) => [
            'id' => $item->id,
            'menu_item_id' => $item->menu_item_id,
            'name' => $item->item_name,
            'description' => $item->item_description,
            'weight' => $item->weight,
            'unit' => $item->unit,
            'unit_price' => $item->unit_price,
            'quantity' => $item->quantity,
            'line_total' => $item->line_total,
        ])->values();
        $data['status_history'] = $order->statusHistory->map(fn ($history) => [
            'from_status' => $history->from_status,
            'to_status' => $history->to_status,
            'comment' => $history->comment,
            'created_at' => $history->created_at?->toIso8601String(),
        ])->values();
        $data['payments'] = $order->payments->map(fn ($payment) => [
            'method' => $payment->method,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ])->values();

        return $data;
    }
}
