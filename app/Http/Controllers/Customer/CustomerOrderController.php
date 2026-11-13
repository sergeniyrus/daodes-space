<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerOrderController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        if (!$customer) {
            return redirect()->guest(route('customer.login'));
        }

        $orders = $customer->orders()
            ->with('organization')
            ->latest()
            ->paginate(15);

        return view('customer.orders.index', [
            'orders' => $orders,
        ]);
    }


    /**
     * Возвращает актуальное состояние заказа для автоматического обновления
     * страницы клиента. Данные доступны только владельцу заказа.
     */
    public function updates(Order $order): JsonResponse|RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        if (!$customer) {
            return redirect()->guest(route('customer.login'));
        }

        abort_unless((int) $order->customer_id === (int) $customer->id, 404);

        $order->loadMissing('statusHistory');

        return response()->json([
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'updated_at' => $order->updated_at?->toIso8601String(),
            'history' => $order->statusHistory
                ->map(fn ($history) => [
                    'status' => $history->to_status,
                    'created_at' => $history->created_at?->toIso8601String(),
                ])
                ->values(),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function show(Order $order): View|RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        if (!$customer) {
            return redirect()->guest(route('customer.login'));
        }

        abort_unless((int) $order->customer_id === (int) $customer->id, 404);

        $order->load([
            'organization',
            'items',
            'statusHistory',
            'payments',
        ]);

        return view('customer.orders.show', [
            'order' => $order,
        ]);
    }
}
