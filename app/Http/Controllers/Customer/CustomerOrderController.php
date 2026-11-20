<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use App\Support\OrderNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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


    /**
     * Клиент самостоятельно подтверждает, что забрал готовый заказ самовывоза.
     */
    public function confirmPickup(Order $order): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        if (!$customer) {
            return redirect()->guest(route('customer.login'));
        }

        DB::transaction(function () use ($customer, $order) {
            $lockedOrder = Order::query()
                ->where('customer_id', $customer->id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedOrder->fulfillment_type !== Order::FULFILLMENT_PICKUP
                || $lockedOrder->status !== Order::STATUS_READY
            ) {
                throw ValidationException::withMessages([
                    'pickup' => 'Подтвердить получение можно только для готового заказа на самовывоз.',
                ]);
            }

            $lockedOrder->update([
                'status' => Order::STATUS_ISSUED,
            ]);

            $lockedOrder->statusHistory()->create([
                'from_status' => Order::STATUS_READY,
                'to_status' => Order::STATUS_ISSUED,
                'actor_type' => 'customer',
                'actor_id' => $customer->id,
                'comment' => 'Клиент самостоятельно подтвердил получение заказа.',
                'created_at' => now(),
            ]);

            app(OrderNotificationService::class)
                ->notifyCustomerAboutStatusChange($lockedOrder, Order::STATUS_ISSUED);
        }, 3);

        return redirect()
            ->route('customer.orders.show', $order)
            ->with('success', 'Получение заказа подтверждено. Спасибо!');
    }

    /**
     * Публичный электронный чек доступен только по случайному bearer-токену
     * с ограниченным сроком действия. В базе хранится только хеш токена.
     */
    public function receipt(string $token)
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/', $token) === 1, 404);

        $order = Order::query()
            ->where('receipt_token_hash', hash('sha256', $token))
            ->where('receipt_token_expires_at', '>', now())
            ->with(['organization', 'items', 'payments'])
            ->firstOrFail();

        return response()
            ->view('customer.orders.receipt', ['order' => $order])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
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
            'review',
        ]);

        return view('customer.orders.show', [
            'order' => $order,
        ]);
    }
}
