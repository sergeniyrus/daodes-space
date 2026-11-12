<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
