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
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeOrderController extends Controller
{
    public function index(
        Request $request,
        Organization $organization
    ): View {
        $employee = Auth::guard('employee')->user();
        $membership = $request->attributes->get('employee_membership');

        abort_unless(
            $employee
            && $membership instanceof OrganizationEmployeeMembership
            && in_array($membership->role, ['cook', 'courier'], true),
            403
        );

        $activeStatuses = $membership->role === 'cook'
            ? [
                Order::STATUS_NEW,
                Order::STATUS_CONFIRMED,
                Order::STATUS_PREPARING,
                Order::STATUS_READY,
            ]
            : [
                Order::STATUS_READY,
                Order::STATUS_HANDED_TO_COURIER,
                Order::STATUS_IN_TRANSIT,
                Order::STATUS_DELIVERED,
            ];

        $ordersQuery = $organization->orders()
            ->whereIn('status', $activeStatuses)
            ->with([
                'items',
                'assignments.employee',
            ]);

        // Кухня — общая очередь организации. Курьеры видят свободные
        // готовые доставки и заказы, которые уже забрали в работу сами.
        if ($membership->role === 'courier') {
            $ordersQuery
                ->where('fulfillment_type', Order::FULFILLMENT_DELIVERY)
                ->where(function ($query) use ($employee) {
                    $query->whereHas('assignments', function ($assignmentQuery) use ($employee) {
                        $assignmentQuery->where('employee_id', $employee->id)
                            ->where('role', 'courier')
                            ->whereNull('unassigned_at');
                    })->orWhere(function ($availableQuery) {
                        $availableQuery->where('status', Order::STATUS_READY)
                            ->whereDoesntHave('assignments', function ($assignmentQuery) {
                                $assignmentQuery->where('role', 'courier')
                                    ->whereNull('unassigned_at');
                            });
                    });
                });
        }

        $orders = $ordersQuery
            ->latest()
            ->paginate(20);

        return view('employee.orders.index', [
            'employee' => $employee,
            'organization' => $organization,
            'membership' => $membership,
            'orders' => $orders,
        ]);
    }

    /**
     * Подробная карточка доставки доступна только назначенному курьеру.
     */
    public function show(
        Request $request,
        Organization $organization,
        Order $order
    ): View {
        $employee = Auth::guard('employee')->user();
        $membership = $request->attributes->get('employee_membership');

        abort_unless(
            $employee
            && $membership instanceof OrganizationEmployeeMembership
            && $membership->role === 'courier',
            403
        );

        $order = $organization->orders()
            ->whereKey($order->id)
            ->where('fulfillment_type', Order::FULFILLMENT_DELIVERY)
            ->whereIn('status', [
                Order::STATUS_READY,
                Order::STATUS_HANDED_TO_COURIER,
                Order::STATUS_IN_TRANSIT,
                Order::STATUS_DELIVERED,
            ])
            ->where(function ($query) use ($employee) {
                $query->whereHas('assignments', function ($assignmentQuery) use ($employee) {
                    $assignmentQuery->where('employee_id', $employee->id)
                        ->where('role', 'courier')
                        ->whereNull('unassigned_at');
                })->orWhere(function ($availableQuery) {
                    $availableQuery->where('status', Order::STATUS_READY)
                        ->whereDoesntHave('assignments', function ($assignmentQuery) {
                            $assignmentQuery->where('role', 'courier')
                                ->whereNull('unassigned_at');
                        });
                });
            })
            ->with(['items'])
            ->firstOrFail();

        return view('employee.orders.show', [
            'employee' => $employee,
            'organization' => $organization,
            'membership' => $membership,
            'order' => $order,
        ]);
    }

    public function updates(
        Request $request,
        Organization $organization
    ): Response {
        $view = $this->index($request, $organization);

        return response()
            ->view('employee.orders.index', $view->getData())
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function updateStatus(
        Request $request,
        Organization $organization,
        Order $order
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                'in:preparing,ready,in_transit,delivered,completed',
            ],
        ]);

        $employee = Auth::guard('employee')->user();

        DB::transaction(function () use (
            $organization,
            $order,
            $validated,
            $employee
        ) {
            $membership = OrganizationEmployeeMembership::query()
                ->where('organization_id', $organization->id)
                ->where('employee_id', $employee->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($membership->role, ['cook', 'courier'], true)) {
                abort(403);
            }

            $lockedOrder = Order::query()
                ->where('organization_id', $organization->id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Повар работает с общей кухонной очередью организации,
            // поэтому персональное назначение не требуется. Курьер по-прежнему
            // может менять статус только собственного активного назначения.
            if ($membership->role === 'courier') {
                $assignment = $lockedOrder->assignments()
                    ->where('employee_id', $employee->id)
                    ->where('role', 'courier')
                    ->whereNull('unassigned_at')
                    ->lockForUpdate()
                    ->first();

                abort_unless($assignment, 403);
            }

            $fromStatus = $lockedOrder->status;
            $toStatus = $validated['status'];
            $hasActiveCourier = false;

            $transitionError = OrderTransitionPolicy::employeeError(
                $membership->role,
                $fromStatus,
                $toStatus,
                $lockedOrder->fulfillment_type,
                $hasActiveCourier,
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

            if ($toStatus === Order::STATUS_READY) {
                $updates['ready_at'] = now();
            }

            if ($toStatus === Order::STATUS_COMPLETED) {
                $updates['completed_at'] = now();
            }

            $lockedOrder->update($updates);

            // Назначение курьера сохраняется до полного закрытия заказа.
            if ($toStatus === Order::STATUS_COMPLETED) {
                $lockedOrder->assignments()
                    ->where('employee_id', $employee->id)
                    ->where('role', 'courier')
                    ->whereNull('unassigned_at')
                    ->update(['unassigned_at' => now()]);
            }

            $lockedOrder->statusHistory()->create([
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_type' => 'employee',
                'actor_id' => $employee->id,
                'comment' => $this->statusComment($toStatus),
                'created_at' => now(),
            ]);

            app(OrderNotificationService::class)
                ->notifyCustomerAboutStatusChange($lockedOrder, $toStatus);
        }, 3);

        return redirect()
            ->route('employee.organization.orders.index', $organization)
            ->with('success', 'Статус заказа обновлён.');
    }


    /**
     * Курьер самостоятельно забирает свободный готовый заказ из общей очереди.
     * Блокировка заказа не позволяет двум курьерам одновременно забрать одну доставку.
     */
    public function claimDelivery(
        Request $request,
        Organization $organization,
        Order $order
    ): RedirectResponse {
        $employee = Auth::guard('employee')->user();

        DB::transaction(function () use ($organization, $order, $employee) {
            $membership = OrganizationEmployeeMembership::query()
                ->where('organization_id', $organization->id)
                ->where('employee_id', $employee->id)
                ->where('role', 'courier')
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            $lockedOrder = Order::query()
                ->where('organization_id', $organization->id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedOrder->fulfillment_type !== Order::FULFILLMENT_DELIVERY
                || $lockedOrder->status !== Order::STATUS_READY
            ) {
                throw ValidationException::withMessages([
                    'order' => 'Забрать можно только готовый заказ с доставкой.',
                ]);
            }

            $alreadyClaimed = $lockedOrder->assignments()
                ->where('role', 'courier')
                ->whereNull('unassigned_at')
                ->exists();

            if ($alreadyClaimed) {
                throw ValidationException::withMessages([
                    'order' => 'Этот заказ уже забрал другой курьер.',
                ]);
            }

            $lockedOrder->assignments()->create([
                'employee_id' => $employee->id,
                'role' => 'courier',
                'assigned_by_user_id' => null,
                'assigned_at' => now(),
            ]);

            $fromStatus = $lockedOrder->status;
            $lockedOrder->update([
                'status' => Order::STATUS_HANDED_TO_COURIER,
            ]);

            $lockedOrder->statusHistory()->create([
                'from_status' => $fromStatus,
                'to_status' => Order::STATUS_HANDED_TO_COURIER,
                'actor_type' => 'employee',
                'actor_id' => $employee->id,
                'comment' => 'Курьер самостоятельно забрал готовый заказ из общей очереди.',
                'created_at' => now(),
            ]);

            app(OrderNotificationService::class)
                ->notifyCustomerAboutStatusChange($lockedOrder, Order::STATUS_HANDED_TO_COURIER);
        }, 3);

        return redirect()
            ->route('employee.organization.orders.show', [$organization, $order])
            ->with('success', 'Заказ закреплён за вами. Можно начинать доставку.');
    }

    /** Курьер подтверждает получение оплаты при доставке. Онлайн-оплата — только webhook. */
    public function markPaymentReceived(Request $request, Organization $organization, Order $order): RedirectResponse
    {
        $employee = Auth::guard('employee')->user();
        DB::transaction(function () use ($organization, $order, $employee) {
            $membership = OrganizationEmployeeMembership::query()
                ->where('organization_id', $organization->id)->where('employee_id', $employee->id)
                ->where('status', 'active')->lockForUpdate()->firstOrFail();
            abort_unless($membership->role === 'courier', 403);
            $lockedOrder = Order::query()->where('organization_id', $organization->id)
                ->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $assignment = $lockedOrder->assignments()->where('employee_id', $employee->id)
                ->where('role', 'courier')->whereNull('unassigned_at')->lockForUpdate()->first();
            abort_unless($assignment, 403);

            if ($lockedOrder->fulfillment_type !== Order::FULFILLMENT_DELIVERY
                || $lockedOrder->status !== Order::STATUS_DELIVERED) {
                throw ValidationException::withMessages(['payment' => 'Получение оплаты можно подтвердить только после вручения заказа.']);
            }
            if (!in_array($lockedOrder->payment_method, [Order::PAYMENT_CASH_ON_RECEIPT, Order::PAYMENT_CARD_ON_RECEIPT], true)) {
                throw ValidationException::withMessages(['payment' => 'Курьер может подтвердить только оплату наличными или картой при получении. Онлайн-оплата подтверждается автоматически.']);
            }
            if ($lockedOrder->payment_status === Order::PAYMENT_SUCCEEDED) return;
            if ($lockedOrder->payment_status !== Order::PAYMENT_PENDING) {
                throw ValidationException::withMessages(['payment' => 'Оплата заказа уже находится в конечном состоянии.']);
            }
            $payments = $lockedOrder->payments()->where('status', Order::PAYMENT_PENDING)->lockForUpdate()->get();
            if ($payments->isEmpty()) {
                throw ValidationException::withMessages(['payment' => 'Не найдена ожидающая оплаты попытка платежа.']);
            }
            $paidAt = now();
            foreach ($payments as $payment) {
                $metadata = $payment->metadata ?? [];
                $metadata['received_by_employee_id'] = $employee->id;
                $metadata['received_by_role'] = 'courier';
                $metadata['received_at'] = $paidAt->toIso8601String();
                $payment->update(['status' => Order::PAYMENT_SUCCEEDED, 'paid_at' => $paidAt, 'metadata' => $metadata]);
            }
            $lockedOrder->update(['payment_status' => Order::PAYMENT_SUCCEEDED]);
        }, 3);
        return redirect()->route('employee.organization.orders.show', [$organization, $order])
            ->with('success', 'Получение оплаты подтверждено. Теперь заказ можно закрыть.');
    }

    private function statusComment(string $status): string
    {
        return match ($status) {
            Order::STATUS_PREPARING => 'Повар начал приготовление заказа.',
            Order::STATUS_READY => 'Заказ приготовлен и готов к выдаче.',
            Order::STATUS_HANDED_TO_COURIER => 'Заказ передан курьеру.',
            Order::STATUS_IN_TRANSIT => 'Курьер принял заказ и начал доставку.',
            Order::STATUS_DELIVERED => 'Курьер отметил заказ как доставленный.',
            Order::STATUS_COMPLETED => 'Курьер закрыл заказ после подтверждения оплаты.',
            default => 'Статус обновлён сотрудником.',
        };
    }
}
