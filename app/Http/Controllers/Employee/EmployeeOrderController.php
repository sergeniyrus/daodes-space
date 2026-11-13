<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAssignment;
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
                Order::STATUS_CONFIRMED,
                Order::STATUS_PREPARING,
                Order::STATUS_READY,
            ]
            : [
                Order::STATUS_HANDED_TO_COURIER,
                Order::STATUS_IN_TRANSIT,
            ];

        $ordersQuery = $organization->orders()
            ->whereIn('status', $activeStatuses)
            ->with([
                'items',
                'assignments.employee',
            ]);

        // Кухня — общая очередь организации: все активные повара видят
        // каждый подтверждённый заказ, даже без персонального назначения.
        // Для курьера сохраняем прежнюю изоляцию по активному назначению.
        if ($membership->role === 'courier') {
            $orderIds = OrderAssignment::query()
                ->where('employee_id', $employee->id)
                ->where('role', 'courier')
                ->whereNull('unassigned_at')
                ->pluck('order_id');

            $ordersQuery
                ->where('fulfillment_type', Order::FULFILLMENT_DELIVERY)
                ->whereIn('id', $orderIds);
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
                Order::STATUS_HANDED_TO_COURIER,
                Order::STATUS_IN_TRANSIT,
            ])
            ->whereHas('assignments', function ($query) use ($employee) {
                $query->where('employee_id', $employee->id)
                    ->where('role', 'courier')
                    ->whereNull('unassigned_at');
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
                'in:preparing,ready,handed_to_courier,in_transit,delivered',
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

            if (
                $membership->role === 'cook'
                && $fromStatus === Order::STATUS_READY
                && $toStatus === Order::STATUS_HANDED_TO_COURIER
                && $lockedOrder->fulfillment_type === Order::FULFILLMENT_DELIVERY
            ) {
                $hasActiveCourier = $lockedOrder->assignments()
                    ->where('role', 'courier')
                    ->whereNull('unassigned_at')
                    ->whereHas('employee', function ($query) {
                        $query->whereNotNull('phone_verified_at');
                    })
                    ->whereHas('employee.memberships', function ($query) use ($organization) {
                        $query->where('organization_id', $organization->id)
                            ->where('role', 'courier')
                            ->where('status', 'active');
                    })
                    ->exists();
            }

            $transitionError = OrderTransitionPolicy::employeeError(
                $membership->role,
                $fromStatus,
                $toStatus,
                $lockedOrder->fulfillment_type,
                $hasActiveCourier
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

            $lockedOrder->update($updates);

            if (in_array($toStatus, [
                Order::STATUS_HANDED_TO_COURIER,
                Order::STATUS_DELIVERED,
            ], true)) {
                $lockedOrder->assignments()
                    ->where('employee_id', $employee->id)
                    ->where('role', $membership->role)
                    ->whereNull('unassigned_at')
                    ->update([
                        'unassigned_at' => now(),
                    ]);
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

    private function statusComment(string $status): string
    {
        return match ($status) {
            Order::STATUS_PREPARING => 'Повар начал приготовление заказа.',
            Order::STATUS_READY => 'Заказ приготовлен и готов к выдаче.',
            Order::STATUS_HANDED_TO_COURIER => 'Заказ передан курьеру.',
            Order::STATUS_IN_TRANSIT => 'Курьер принял заказ и начал доставку.',
            Order::STATUS_DELIVERED => 'Курьер отметил заказ как доставленный.',
            default => 'Статус обновлён сотрудником.',
        };
    }
}
