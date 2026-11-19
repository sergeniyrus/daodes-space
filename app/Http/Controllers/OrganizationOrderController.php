<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Organization;
use App\Support\OrderNotificationService;
use App\Support\OrderTransitionPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationOrderController extends Controller
{
    public function index(Request $request, Organization $organization): View
    {
        $filters = $request->validate([
            'period' => ['nullable', 'in:day,yesterday,last7,last30,week,month,year,all,custom'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:new,awaiting_payment,confirmed,preparing,ready,handed_to_courier,in_transit,issued,served,delivered,completed,cancelled'],
            'payment_status' => ['nullable', 'in:pending,succeeded,failed,cancelled,refunded'],
            'fulfillment_type' => ['nullable', 'in:delivery,pickup,dine_in'],
            'payment_method' => ['nullable', 'in:online,cash_on_receipt,card_on_receipt'],
        ]);

        $period = $filters['period'] ?? 'month';
        $now = now();
        $dateFrom = null;
        $dateTo = $now->copy()->endOfDay();

        switch ($period) {
            case 'day':
                $dateFrom = $now->copy()->startOfDay();
                break;
            case 'yesterday':
                $dateFrom = $now->copy()->subDay()->startOfDay();
                $dateTo = $now->copy()->subDay()->endOfDay();
                break;
            case 'last7':
                $dateFrom = $now->copy()->subDays(6)->startOfDay();
                break;
            case 'last30':
                $dateFrom = $now->copy()->subDays(29)->startOfDay();
                break;
            case 'week':
                $dateFrom = $now->copy()->startOfWeek();
                break;
            case 'year':
                $dateFrom = $now->copy()->startOfYear();
                break;
            case 'all':
                $dateFrom = null;
                $dateTo = null;
                break;
            case 'custom':
                $dateFrom = !empty($filters['date_from']) ? \Illuminate\Support\Carbon::parse($filters['date_from'])->startOfDay() : $now->copy()->startOfMonth();
                $dateTo = !empty($filters['date_to']) ? \Illuminate\Support\Carbon::parse($filters['date_to'])->endOfDay() : $now->copy()->endOfDay();
                break;
            case 'month':
            default:
                $dateFrom = $now->copy()->startOfMonth();
                break;
        }

        $periodQuery = $organization->orders()
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo));

        $summary = [
            'count' => (clone $periodQuery)->count(),
            'paid_count' => (clone $periodQuery)->where('payment_status', Order::PAYMENT_SUCCEEDED)->count(),
            'pending_count' => (clone $periodQuery)->where('payment_status', Order::PAYMENT_PENDING)->count(),
            'cancelled_count' => (clone $periodQuery)->where('status', Order::STATUS_CANCELLED)->count(),
            'amounts' => (clone $periodQuery)->select('currency')
                ->selectRaw('COUNT(*) as orders_count')
                ->selectRaw('SUM(total) as total_amount')
                ->selectRaw('AVG(total) as average_amount')
                ->selectRaw('SUM(CASE WHEN payment_status = ? THEN total ELSE 0 END) as paid_amount', [Order::PAYMENT_SUCCEEDED])
                ->selectRaw('SUM(CASE WHEN payment_status = ? THEN total ELSE 0 END) as pending_amount', [Order::PAYMENT_PENDING])
                ->groupBy('currency')->get(),
            'statuses' => (clone $periodQuery)->select('status')
                ->selectRaw('COUNT(*) as orders_count')->groupBy('status')->get(),
            'fulfillment' => (clone $periodQuery)->select('fulfillment_type')
                ->selectRaw('COUNT(*) as orders_count')->groupBy('fulfillment_type')->get(),
            'payment_methods' => (clone $periodQuery)->select('payment_method')
                ->selectRaw('COUNT(*) as orders_count')->groupBy('payment_method')->get(),
        ];

        // Аналитика блюд строится только по фактически оплаченным и неотменённым заказам.
        // В выборке остаются все позиции меню организации, включая временно отключённые,
        // чтобы видеть товары без продаж за выбранный период.
        $productSalesQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.organization_id', $organization->id)
            ->where('orders.payment_status', Order::PAYMENT_SUCCEEDED)
            ->where('orders.status', '<>', Order::STATUS_CANCELLED)
            ->when($dateFrom, fn ($q) => $q->where('orders.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('orders.created_at', '<=', $dateTo))
            ->select('order_items.menu_item_id')
            ->selectRaw('SUM(order_items.quantity) as sold_quantity')
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->groupBy('order_items.menu_item_id');

        $productAnalyticsQuery = DB::table('menu_items')
            ->join('menu_sections', 'menu_sections.id', '=', 'menu_items.menu_section_id')
            ->leftJoinSub($productSalesQuery, 'product_sales', function ($join) {
                $join->on('product_sales.menu_item_id', '=', 'menu_items.id');
            })
            ->where('menu_sections.organization_id', $organization->id)
            ->select(
                'menu_items.id',
                'menu_items.name',
                'menu_items.is_active'
            )
            ->selectRaw('COALESCE(product_sales.sold_quantity, 0) as sold_quantity')
            ->selectRaw('COALESCE(product_sales.orders_count, 0) as orders_count');

        $bestSellingProducts = (clone $productAnalyticsQuery)
            ->orderByDesc('sold_quantity')
            ->orderBy('menu_items.name')
            ->limit(10)
            ->get();

        $leastSellingProducts = (clone $productAnalyticsQuery)
            ->orderBy('sold_quantity')
            ->orderBy('menu_items.name')
            ->limit(10)
            ->get();

        $orders = (clone $periodQuery)->with('items')
            ->when($filters['q'] ?? null, function ($q, $v) {
                $q->where(function ($w) use ($v) {
                    $w->where('order_number', 'like', "%{$v}%")
                        ->orWhere('customer_name', 'like', "%{$v}%")
                        ->orWhere('customer_phone', 'like', "%{$v}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['payment_status'] ?? null, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($filters['fulfillment_type'] ?? null, fn ($q, $v) => $q->where('fulfillment_type', $v))
            ->when($filters['payment_method'] ?? null, fn ($q, $v) => $q->where('payment_method', $v))
            ->latest()->paginate(25)->withQueryString();

        return view('organizations.orders.index', compact(
            'organization', 'orders', 'filters', 'summary', 'period', 'dateFrom', 'dateTo',
            'bestSellingProducts', 'leastSellingProducts'
        ));
    }

    public function show(Organization $organization, int $order): View
    {
        $order = $organization->orders()
            ->with([
                'customer',
                'items',
                'payments',
                'assignments.employee',
                'assignments.assignedBy',
                'statusHistory',
            ])
            ->whereKey($order)
            ->firstOrFail();

        return view('organizations.orders.show', [
            'organization' => $organization,
            'order' => $order,
        ]);
    }

    /**
     * Печатный чек заказа: термопринтер 80 мм или лист A4.
     * Заказ выбирается только внутри текущей организации.
     */
    public function receipt(
        Request $request,
        Organization $organization,
        int $order
    ): View {
        $validated = $request->validate([
            'format' => ['nullable', 'in:80mm,a4'],
        ]);

        $order = $organization->orders()
            ->with(['items', 'payments'])
            ->whereKey($order)
            ->firstOrFail();

        $format = $validated['format'] ?? '80mm';

        return view('organizations.orders.receipt', [
            'organization' => $organization,
            'order' => $order,
            'format' => $format,
            'qrValue' => route('organizations.orders.show', [
                'organization' => $organization->id,
                'order' => $order->id,
            ], true),
        ]);
    }

    /**
     * Экспорт отфильтрованных заказов текущей организации в CSV.
     */
    public function export(Request $request, Organization $organization): StreamedResponse
    {
        $ordersQuery = $this->filteredOrdersQuery($request, $organization);

        return response()->streamDownload(function () use ($ordersQuery) {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            // BOM обеспечивает корректное отображение кириллицы в Excel.
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'Номер заказа',
                'Дата создания',
                'Клиент',
                'Телефон',
                'Статус',
                'Получение',
                'Способ оплаты',
                'Статус оплаты',
                'Состав заказа',
                'Сумма',
                'Валюта',
                'Комментарий',
            ], ';', '"', '\\');

            $safeCsvValue = static function ($value): string {
                $value = (string) ($value ?? '');

                // Защита от CSV/Excel formula injection для пользовательских полей.
                if (preg_match('/^\s*[=+@-]/u', $value) === 1) {
                    return "'" . $value;
                }

                return $value;
            };

            $ordersQuery->chunk(500, function ($orders) use ($output, $safeCsvValue) {
                foreach ($orders as $order) {
                    $items = $order->items
                        ->map(fn ($item) => $item->item_name . ' × ' . $item->quantity)
                        ->implode(' | ');

                    fputcsv($output, [
                        $safeCsvValue($order->order_number),
                        $order->created_at?->format('Y-m-d H:i:s'),
                        $safeCsvValue($order->customer_name),
                        $safeCsvValue($order->customer_phone),
                        $safeCsvValue($order->status),
                        $safeCsvValue($order->fulfillment_type),
                        $safeCsvValue($order->payment_method),
                        $safeCsvValue($order->payment_status),
                        $safeCsvValue($items),
                        $order->total,
                        $order->currency,
                        $safeCsvValue($order->customer_comment),
                    ], ';', '"', '\\');
                }
            });

            fclose($output);
        }, 'orders-' . $organization->id . '-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * Общая выборка истории заказов с фильтрами.
     * Все условия применяются только к заказам текущей организации.
     */
    private function filteredOrdersQuery(Request $request, Organization $organization)
    {
        $filters = $request->validate([
            'period' => ['nullable', 'in:day,yesterday,last7,last30,week,month,year,all,custom'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:new,awaiting_payment,confirmed,preparing,ready,handed_to_courier,in_transit,issued,served,delivered,completed,cancelled'],
            'fulfillment_type' => ['nullable', 'in:delivery,pickup,dine_in'],
            'payment_method' => ['nullable', 'in:online,cash_on_receipt,card_on_receipt'],
            'payment_status' => ['nullable', 'in:pending,succeeded,failed,cancelled,refunded'],
        ]);

        $period = $filters['period'] ?? 'month';
        $now = now();
        $dateFrom = null;
        $dateTo = $now->copy()->endOfDay();

        switch ($period) {
            case 'day':
                $dateFrom = $now->copy()->startOfDay();
                break;
            case 'yesterday':
                $dateFrom = $now->copy()->subDay()->startOfDay();
                $dateTo = $now->copy()->subDay()->endOfDay();
                break;
            case 'last7':
                $dateFrom = $now->copy()->subDays(6)->startOfDay();
                break;
            case 'last30':
                $dateFrom = $now->copy()->subDays(29)->startOfDay();
                break;
            case 'week':
                $dateFrom = $now->copy()->startOfWeek();
                break;
            case 'year':
                $dateFrom = $now->copy()->startOfYear();
                break;
            case 'all':
                $dateFrom = null;
                $dateTo = null;
                break;
            case 'custom':
                $dateFrom = !empty($filters['date_from'])
                    ? \\Illuminate\\Support\\Carbon::parse($filters['date_from'])->startOfDay()
                    : $now->copy()->startOfMonth();
                $dateTo = !empty($filters['date_to'])
                    ? \\Illuminate\\Support\\Carbon::parse($filters['date_to'])->endOfDay()
                    : $now->copy()->endOfDay();
                break;
            case 'month':
            default:
                $dateFrom = $now->copy()->startOfMonth();
                break;
        }

        $query = $organization->orders()
            ->with(['items'])
            ->when($dateFrom, fn ($builder) => $builder->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($builder) => $builder->where('created_at', '<=', $dateTo));

        if (!empty($filters['q'])) {
            $search = trim($filters['q']);

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('order_number', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%')
                    ->orWhere('customer_phone', 'like', '%' . $search . '%');
            });
        }

        foreach ((['status', 'fulfillment_type', 'payment_method', 'payment_status']) as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function updateStatus(
        Request $request,
        Organization $organization,
        Order $order
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', 'in:cancelled,served,completed'],
        ]);

        DB::transaction(function () use ($organization, $order, $validated) {
            $lockedOrder = Order::query()
                ->where('organization_id', $organization->id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $fromStatus = $lockedOrder->status;
            $toStatus = $validated['status'];
            $transitionError = OrderTransitionPolicy::managerError(
                $fromStatus,
                $toStatus,
                $lockedOrder->fulfillment_type,
                $lockedOrder->payment_status
            );

            if ($transitionError !== null) {
                throw ValidationException::withMessages([
                    'status' => $transitionError,
                ]);
            }

            $updates = [
                'status' => $toStatus,
            ];

            if ($toStatus === Order::STATUS_CONFIRMED) {
                $updates['confirmed_at'] = now();
            } elseif ($toStatus === Order::STATUS_COMPLETED) {
                $updates['completed_at'] = now();
            } elseif ($toStatus === Order::STATUS_CANCELLED) {
                $updates['cancelled_at'] = now();
                $updates['payment_status'] = Order::PAYMENT_CANCELLED;

                $lockedOrder->payments()
                    ->where('status', Order::PAYMENT_PENDING)
                    ->update([
                        'status' => Order::PAYMENT_CANCELLED,
                    ]);
            }

            $lockedOrder->update($updates);

            if (in_array($toStatus, [
                Order::STATUS_CANCELLED,
                Order::STATUS_ISSUED,
                Order::STATUS_SERVED,
                Order::STATUS_COMPLETED,
            ], true)) {
                $lockedOrder->assignments()
                    ->whereNull('unassigned_at')
                    ->update([
                        'unassigned_at' => now(),
                    ]);
            }

            $comments = [
                Order::STATUS_CONFIRMED => 'Заказ подтверждён менеджером.',
                Order::STATUS_ISSUED => 'Заказ выдан клиенту.',
                Order::STATUS_SERVED => 'Заказ подан клиенту в заведении.',
                Order::STATUS_COMPLETED => 'Заказ завершён менеджером.',
                Order::STATUS_CANCELLED => 'Заказ отменён менеджером.',
            ];

            $lockedOrder->statusHistory()->create([
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_type' => 'user',
                'actor_id' => auth()->id(),
                'comment' => $comments[$toStatus] ?? 'Статус изменён менеджером.',
                'created_at' => now(),
            ]);

            app(OrderNotificationService::class)
                ->notifyCustomerAboutStatusChange($lockedOrder, $toStatus);
        }, 3);

        $messages = [
            'confirmed' => 'Заказ подтверждён.',
            'cancelled' => 'Заказ отменён.',
            'issued' => 'Заказ отмечен как выданный.',
            'served' => 'Заказ отмечен как поданный.',
            'completed' => 'Заказ завершён.',
        ];

        return redirect()
            ->route('organizations.orders.index', $organization)
            ->with('success', $messages[$validated['status']]);
    }

    public function markPaid(
        Organization $organization,
        Order $order
    ): RedirectResponse {
        DB::transaction(function () use ($organization, $order) {
            $lockedOrder = Order::query()
                ->where('organization_id', $organization->id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedOrder->status === Order::STATUS_CANCELLED
                || $lockedOrder->payment_method === Order::PAYMENT_ONLINE
            ) {
                throw ValidationException::withMessages([
                    'payment' => 'Для этого заказа нельзя отметить оплату вручную.',
                ]);
            }

            if ($lockedOrder->payment_status === Order::PAYMENT_SUCCEEDED) {
                return;
            }

            if ($lockedOrder->payment_status !== Order::PAYMENT_PENDING) {
                throw ValidationException::withMessages([
                    'payment' => 'Оплата заказа уже находится в конечном состоянии.',
                ]);
            }

            $collectableStatus = match ($lockedOrder->fulfillment_type) {
                Order::FULFILLMENT_DELIVERY => Order::STATUS_DELIVERED,
                Order::FULFILLMENT_PICKUP => Order::STATUS_ISSUED,
                Order::FULFILLMENT_DINE_IN => Order::STATUS_SERVED,
                default => null,
            };

            if ($lockedOrder->status !== $collectableStatus) {
                throw ValidationException::withMessages([
                    'payment' => 'Отметить оплату можно после фактической передачи заказа клиенту.',
                ]);
            }

            $payments = $lockedOrder->payments()
                ->where('status', Order::PAYMENT_PENDING)
                ->lockForUpdate()
                ->get();

            if ($payments->isEmpty()) {
                throw ValidationException::withMessages([
                    'payment' => 'Не найдена ожидающая оплаты попытка платежа.',
                ]);
            }

            $paidAt = now();

            foreach ($payments as $payment) {
                $metadata = $payment->metadata ?? [];
                $metadata['received_by_user_id'] = auth()->id();
                $metadata['received_by_role'] = 'manager';
                $metadata['received_at'] = $paidAt->toIso8601String();

                $payment->update([
                    'status' => Order::PAYMENT_SUCCEEDED,
                    'paid_at' => $paidAt,
                    'metadata' => $metadata,
                ]);
            }

            $lockedOrder->update([
                'payment_status' => Order::PAYMENT_SUCCEEDED,
            ]);
        }, 3);

        return redirect()
            ->route('organizations.orders.index', $organization)
            ->with('success', 'Оплата отмечена как полученная.');
    }

}