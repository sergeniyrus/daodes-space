<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Organization;
use App\Models\OrganizationEmployeeMembership;
use App\Support\OrderNotificationService;
use App\Support\OrderTransitionPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrganizationOrderController extends Controller
{
    public function index(Organization $organization): View
    {
        $orders = $organization->orders()
            ->with([
                'customer',
                'items',
                'assignments.employee',
                'statusHistory',
            ])
            ->latest()
            ->paginate(20);

        $staffMemberships = $organization->employeeMemberships()
            ->with('employee')
            ->where('status', 'active')
            ->whereIn('role', ['cook', 'courier'])
            ->whereHas('employee', function ($query) {
                $query->whereNotNull('phone_verified_at');
            })
            ->orderBy('role')
            ->orderBy('id')
            ->get();

        $courierLoads = DB::table('order_assignments')
            ->join('orders', 'orders.id', '=', 'order_assignments.order_id')
            ->join('organization_employee_memberships as memberships', function ($join) use ($organization) {
                $join->on('memberships.employee_id', '=', 'order_assignments.employee_id')
                    ->where('memberships.organization_id', '=', $organization->id)
                    ->where('memberships.role', '=', 'courier')
                    ->where('memberships.status', '=', 'active');
            })
            ->join('organization_employees as employees', 'employees.id', '=', 'order_assignments.employee_id')
            ->where('orders.organization_id', $organization->id)
            ->where('order_assignments.role', 'courier')
            ->whereNull('order_assignments.unassigned_at')
            ->whereIn('orders.status', [
                Order::STATUS_HANDED_TO_COURIER,
                Order::STATUS_IN_TRANSIT,
            ])
            ->whereNotNull('employees.phone_verified_at')
            ->select(
                'order_assignments.employee_id',
                DB::raw('COUNT(DISTINCT orders.id) as active_delivery_orders_count')
            )
            ->groupBy('order_assignments.employee_id')
            ->pluck('active_delivery_orders_count', 'order_assignments.employee_id');

        foreach ($staffMemberships->where('role', 'courier') as $membership) {
            $membership->employee?->setAttribute(
                'active_delivery_orders_count',
                (int) ($courierLoads[$membership->employee_id] ?? 0)
            );
        }

        return view('organizations.orders.index', [
            'organization' => $organization,
            'orders' => $orders,
            'staffMemberships' => $staffMemberships,
        ]);
    }

    public function updates(Organization $organization): Response
    {
        $view = $this->index($organization);

        return response()
            ->view('organizations.orders.index', $view->getData())
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function updateStatus(
        Request $request,
        Organization $organization,
        Order $order
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', 'in:confirmed,cancelled,issued,served,completed'],
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

            $updatedPayments = $lockedOrder->payments()
                ->where('status', Order::PAYMENT_PENDING)
                ->update([
                    'status' => Order::PAYMENT_SUCCEEDED,
                    'paid_at' => now(),
                ]);

            if ($updatedPayments < 1) {
                throw ValidationException::withMessages([
                    'payment' => 'Не найдена ожидающая оплаты попытка платежа.',
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

    public function assign(
        Request $request,
        Organization $organization,
        Order $order
    ): RedirectResponse {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:organization_employees,id'],
            'role' => ['required', 'in:cook,courier'],
        ]);

        DB::transaction(function () use ($organization, $order, $validated) {
            $lockedOrder = Order::query()
                ->where('organization_id', $organization->id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedOrder->status, [
                Order::STATUS_CANCELLED,
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
            ], true)) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Нельзя назначать сотрудника на закрытый заказ.',
                ]);
            }

            if (
                $validated['role'] === 'courier'
                && $lockedOrder->fulfillment_type !== Order::FULFILLMENT_DELIVERY
            ) {
                throw ValidationException::withMessages([
                    'role' => 'Курьера можно назначить только на заказ с доставкой.',
                ]);
            }

            $assignableStatuses = $validated['role'] === 'cook'
                ? [
                    Order::STATUS_NEW,
                    Order::STATUS_CONFIRMED,
                    Order::STATUS_PREPARING,
                    Order::STATUS_READY,
                ]
                : [
                    Order::STATUS_NEW,
                    Order::STATUS_CONFIRMED,
                    Order::STATUS_PREPARING,
                    Order::STATUS_READY,
                    Order::STATUS_HANDED_TO_COURIER,
                ];

            if (!in_array($lockedOrder->status, $assignableStatuses, true)) {
                throw ValidationException::withMessages([
                    'employee_id' => 'На этом этапе заказа нельзя менять назначение сотрудника.',
                ]);
            }

            $membership = OrganizationEmployeeMembership::query()
                ->where('organization_id', $organization->id)
                ->where('employee_id', $validated['employee_id'])
                ->where('role', $validated['role'])
                ->where('status', 'active')
                ->whereHas('employee', function ($query) {
                    $query->whereNotNull('phone_verified_at');
                })
                ->lockForUpdate()
                ->first();

            if (!$membership) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Сотрудник не имеет активного членства с выбранной ролью в этой организации.',
                ]);
            }

            $lockedOrder->assignments()
                ->where('role', $validated['role'])
                ->whereNull('unassigned_at')
                ->update([
                    'unassigned_at' => now(),
                ]);

            $lockedOrder->assignments()->create([
                'employee_id' => $membership->employee_id,
                'role' => $validated['role'],
                'assigned_by_user_id' => auth()->id(),
                'assigned_at' => now(),
            ]);

            app(\App\Support\OrderNotificationService::class)
                ->notifyEmployeeAssigned(
                    $lockedOrder,
                    (int) $membership->employee_id,
                    $validated['role']
                );
        }, 3);

        return redirect()
            ->route('organizations.orders.index', $organization)
            ->with('success', 'Сотрудник назначен на заказ.');
    }
}
