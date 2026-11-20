<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Organization;
use App\Models\OrganizationActivityLog;
use App\Models\OrganizationEmployeeMembership;
use App\Support\OrderNotificationService;
use App\Support\OrderTransitionPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeAdminOrderController extends Controller
{
    private function assertAdmin(Organization $organization): void
    {
        $employee = Auth::guard('employee')->user();
        abort_unless($employee, 403);
        OrganizationEmployeeMembership::query()
            ->where('organization_id', $organization->id)
            ->where('employee_id', $employee->id)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->firstOrFail();
    }

    public function overview(Organization $organization): View
    {
        $this->assertAdmin($organization);

        $organizationTimezone = $organization->orderSettings?->timezoneName() ?? 'Asia/Krasnoyarsk';
        $todayLocal = now($organizationTimezone)->startOfDay();
        $todayStart = $todayLocal->copy()->utc();
        $tomorrowStart = $todayLocal->copy()->addDay()->utc();

        $todayOrders = $organization->orders()
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<', $tomorrowStart);

        $currentOrders = $organization->orders()
            ->whereIn('status', [
                Order::STATUS_NEW,
                Order::STATUS_CONFIRMED,
                Order::STATUS_PREPARING,
                Order::STATUS_READY,
                Order::STATUS_HANDED_TO_COURIER,
                Order::STATUS_IN_TRANSIT,
            ]);

        $overviewStats = [
            'orders_today' => (clone $todayOrders)->count(),
            'new_orders' => (clone $currentOrders)->where('status', Order::STATUS_NEW)->count(),
            'kitchen_orders' => (clone $currentOrders)->whereIn('status', [
                Order::STATUS_CONFIRMED,
                Order::STATUS_PREPARING,
            ])->count(),
            'ready_orders' => (clone $currentOrders)->where('status', Order::STATUS_READY)->count(),
            'delivery_orders' => (clone $currentOrders)->whereIn('status', [
                Order::STATUS_HANDED_TO_COURIER,
                Order::STATUS_IN_TRANSIT,
            ])->count(),
            'completed_today' => $organization->orders()
                ->where('status', Order::STATUS_COMPLETED)
                ->where('completed_at', '>=', $todayStart)
                ->where('completed_at', '<', $tomorrowStart)
                ->count(),
            'cancelled_today' => $organization->orders()
                ->where('status', Order::STATUS_CANCELLED)
                ->where('cancelled_at', '>=', $todayStart)
                ->where('cancelled_at', '<', $tomorrowStart)
                ->count(),
        ];

        $paidToday = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('orders.organization_id', $organization->id)
            ->where('payments.status', Order::PAYMENT_SUCCEEDED)
            ->whereNotNull('payments.paid_at')
            ->where('payments.paid_at', '>=', $todayStart)
            ->where('payments.paid_at', '<', $tomorrowStart)
            ->sum('payments.amount');

        $activityLogs = OrganizationActivityLog::query()
            ->with('actor:id,name')
            ->where('organization_id', $organization->id)
            ->latest('id')
            ->limit(30)
            ->get();

        $orderSettings = $organization->orderSettings;

        return view('employee.admin.organizations.overview', compact(
            'organization',
            'overviewStats',
            'paidToday',
            'activityLogs',
            'orderSettings'
        ));
    }

    public function team(Organization $organization): View
    {
        $this->assertAdmin($organization);

        $staffMemberships = $organization->employeeMemberships()
            ->with('employee')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'pending' THEN 1 WHEN 'blocked' THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->get();

        return view('employee.admin.organizations.team', compact(
            'organization',
            'staffMemberships'
        ));
    }

    public function index(Request $request, Organization $organization): View
    {
        $this->assertAdmin($organization);
        $filters = $request->validate([
            'period' => ['nullable', 'in:day,yesterday,last7,last30,week,month,year,all,custom'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:new,awaiting_payment,confirmed,preparing,ready,handed_to_courier,in_transit,issued,served,delivered,completed,cancelled'],
            'payment_status' => ['nullable', 'in:pending,succeeded,failed,cancelled,refunded'],
            'fulfillment_type' => ['nullable', 'in:delivery,pickup,dine_in'],
            'payment_method' => ['nullable', 'in:online,cash_on_receipt,card_on_receipt,guest_account'],
        ]);

        $period = $filters['period'] ?? 'month';
        $timezone = $organization->orderSettings?->timezoneName() ?? config('app.timezone', 'UTC');
        $now = now($timezone);
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
                $dateFrom = !empty($filters['date_from']) ? \Illuminate\Support\Carbon::parse($filters['date_from'], $timezone)->startOfDay() : $now->copy()->startOfMonth();
                $dateTo = !empty($filters['date_to']) ? \Illuminate\Support\Carbon::parse($filters['date_to'], $timezone)->endOfDay() : $now->copy()->endOfDay();
                break;
            case 'month':
            default:
                $dateFrom = $now->copy()->startOfMonth();
                break;
        }

        $queryDateFrom = $dateFrom?->copy()->utc();
        $queryDateTo = $dateTo?->copy()->utc();

        $periodQuery = $organization->orders()
            ->when($queryDateFrom, fn ($q) => $q->where('created_at', '>=', $queryDateFrom))
            ->when($queryDateTo, fn ($q) => $q->where('created_at', '<=', $queryDateTo));

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
            ->when($queryDateFrom, fn ($q) => $q->where('orders.created_at', '>=', $queryDateFrom))
            ->when($queryDateTo, fn ($q) => $q->where('orders.created_at', '<=', $queryDateTo))
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

        return view('employee.admin.orders.index', compact(
            'organization', 'orders', 'filters', 'summary', 'period', 'dateFrom', 'dateTo',
            'bestSellingProducts', 'leastSellingProducts'
        ));
    }

    public function show(Organization $organization, int $order): View
    {
        $this->assertAdmin($organization);
        $order = $organization->orders()->with(['customer', 'items', 'payments', 'assignments.employee', 'assignments.assignedBy', 'statusHistory'])
            ->whereKey($order)->firstOrFail();
        return view('employee.admin.orders.show', compact('organization', 'order'));
    }

    public function updateStatus(Request $request, Organization $organization, Order $order): RedirectResponse
    {
        $this->assertAdmin($organization);
        $data = $request->validate(['status' => ['required', 'in:served,completed,cancelled']]);
        $employee = Auth::guard('employee')->user();
        DB::transaction(function () use ($organization, $order, $data, $employee) {
            $locked = Order::where('organization_id', $organization->id)->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            $to = $data['status'];
            $error = OrderTransitionPolicy::managerError($from, $to, $locked->fulfillment_type, $locked->payment_status);
            if ($error) throw ValidationException::withMessages(['status' => $error]);
            $updates = ['status' => $to];
            if ($to === Order::STATUS_COMPLETED) $updates['completed_at'] = now();
            if ($to === Order::STATUS_CANCELLED) {
                $updates['cancelled_at'] = now();
                $updates['payment_status'] = Order::PAYMENT_CANCELLED;
                $locked->payments()->where('status', Order::PAYMENT_PENDING)->update(['status' => Order::PAYMENT_CANCELLED]);
            }
            $locked->update($updates);
            if (in_array($to, [Order::STATUS_CANCELLED, Order::STATUS_ISSUED, Order::STATUS_SERVED, Order::STATUS_COMPLETED], true)) {
                $locked->assignments()->whereNull('unassigned_at')->update(['unassigned_at' => now()]);
            }
            $locked->statusHistory()->create([
                'from_status' => $from, 'to_status' => $to, 'actor_type' => 'employee',
                'actor_id' => $employee->id, 'comment' => 'Действие выполнено администратором организации.',
                'created_at' => now(),
            ]);
            app(OrderNotificationService::class)->notifyCustomerAboutStatusChange($locked, $to);
        }, 3);
        return redirect()->route('employee.organization.admin.orders.show', [$organization, $order])->with('success', 'Статус заказа обновлён.');
    }

    public function markPaid(Organization $organization, Order $order): RedirectResponse
    {
        $this->assertAdmin($organization);
        $employee = Auth::guard('employee')->user();
        DB::transaction(function () use ($organization, $order, $employee) {
            $locked = Order::where('organization_id', $organization->id)->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === Order::STATUS_CANCELLED || $locked->payment_method === Order::PAYMENT_ONLINE || $locked->payment_method === Order::PAYMENT_GUEST_ACCOUNT) {
                throw ValidationException::withMessages(['payment' => 'Для этого заказа нельзя подтвердить оплату вручную.']);
            }
            if ($locked->payment_status === Order::PAYMENT_SUCCEEDED) return;
            if ($locked->payment_status !== Order::PAYMENT_PENDING) {
                throw ValidationException::withMessages(['payment' => 'Оплата уже находится в конечном состоянии.']);
            }
            $required = match ($locked->fulfillment_type) {
                Order::FULFILLMENT_DELIVERY => Order::STATUS_DELIVERED,
                Order::FULFILLMENT_PICKUP => Order::STATUS_ISSUED,
                Order::FULFILLMENT_DINE_IN => Order::STATUS_SERVED,
                default => null,
            };
            if ($locked->status !== $required) {
                throw ValidationException::withMessages(['payment' => 'Подтвердить оплату можно только после фактической передачи заказа клиенту.']);
            }
            $payments = $locked->payments()->where('status', Order::PAYMENT_PENDING)->lockForUpdate()->get();
            if ($payments->isEmpty()) throw ValidationException::withMessages(['payment' => 'Не найдена ожидающая оплаты попытка платежа.']);
            $paidAt = now();
            foreach ($payments as $payment) {
                $metadata = $payment->metadata ?? [];
                $metadata['received_by_employee_id'] = $employee->id;
                $metadata['received_by_role'] = 'admin';
                $metadata['received_at'] = $paidAt->toIso8601String();
                $payment->update(['status' => Order::PAYMENT_SUCCEEDED, 'paid_at' => $paidAt, 'metadata' => $metadata]);
            }
            $locked->update(['payment_status' => Order::PAYMENT_SUCCEEDED]);
        }, 3);
        return redirect()->route('employee.organization.admin.orders.show', [$organization, $order])->with('success', 'Получение оплаты подтверждено.');
    }
}
