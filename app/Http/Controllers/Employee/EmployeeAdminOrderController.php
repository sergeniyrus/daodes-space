<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Organization;
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

    public function index(Request $request, Organization $organization): View
    {
        $this->assertAdmin($organization);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:new,awaiting_payment,confirmed,preparing,ready,handed_to_courier,in_transit,issued,served,delivered,completed,cancelled'],
            'payment_status' => ['nullable', 'in:pending,succeeded,failed,cancelled,refunded'],
        ]);
        $orders = $organization->orders()->with('items')
            ->when($filters['q'] ?? null, function ($q, $v) {
                $q->where(function ($w) use ($v) {
                    $w->where('order_number', 'like', "%{$v}%")
                        ->orWhere('customer_name', 'like', "%{$v}%")
                        ->orWhere('customer_phone', 'like', "%{$v}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['payment_status'] ?? null, fn ($q, $v) => $q->where('payment_status', $v))
            ->latest()->paginate(25)->withQueryString();
        return view('employee.admin.orders.index', compact('organization', 'orders', 'filters'));
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
            if ($locked->status === Order::STATUS_CANCELLED || $locked->payment_method === Order::PAYMENT_ONLINE) {
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
