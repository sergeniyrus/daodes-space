<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Organization;
use App\Models\OrganizationEmployeeMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ])
            ->latest()
            ->paginate(20);

        $staffMemberships = $organization->employeeMemberships()
            ->with('employee')
            ->where('status', 'active')
            ->whereIn('role', ['cook', 'courier'])
            ->orderBy('role')
            ->orderBy('id')
            ->get();

        return view('organizations.orders.index', [
            'organization' => $organization,
            'orders' => $orders,
            'staffMemberships' => $staffMemberships,
        ]);
    }

    public function updateStatus(
        Request $request,
        Organization $organization,
        Order $order
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', 'in:confirmed,cancelled'],
        ]);

        DB::transaction(function () use ($organization, $order, $validated) {
            $lockedOrder = Order::query()
                ->where('organization_id', $organization->id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $fromStatus = $lockedOrder->status;
            $toStatus = $validated['status'];

            if ($toStatus === Order::STATUS_CONFIRMED) {
                if ($fromStatus !== Order::STATUS_NEW) {
                    throw ValidationException::withMessages([
                        'status' => 'Подтвердить можно только новый заказ.',
                    ]);
                }

                $lockedOrder->update([
                    'status' => Order::STATUS_CONFIRMED,
                    'confirmed_at' => now(),
                ]);
            } else {
                $cancellableStatuses = [
                    Order::STATUS_NEW,
                    Order::STATUS_CONFIRMED,
                    Order::STATUS_PREPARING,
                    Order::STATUS_READY,
                ];

                if (!in_array($fromStatus, $cancellableStatuses, true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Этот заказ уже нельзя отменить из панели менеджера.',
                    ]);
                }

                $lockedOrder->update([
                    'status' => Order::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'payment_status' => Order::PAYMENT_CANCELLED,
                ]);

                $lockedOrder->payments()
                    ->where('status', Order::PAYMENT_PENDING)
                    ->update([
                        'status' => Order::PAYMENT_CANCELLED,
                    ]);
            }

            $lockedOrder->statusHistory()->create([
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_type' => 'user',
                'actor_id' => auth()->id(),
                'comment' => $toStatus === Order::STATUS_CONFIRMED
                    ? 'Заказ подтверждён менеджером.'
                    : 'Заказ отменён менеджером.',
                'created_at' => now(),
            ]);
        });

        return redirect()
            ->route('organizations.orders.index', $organization)
            ->with('success', $validated['status'] === 'confirmed'
                ? 'Заказ подтверждён.'
                : 'Заказ отменён.');
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

            $membership = OrganizationEmployeeMembership::query()
                ->where('organization_id', $organization->id)
                ->where('employee_id', $validated['employee_id'])
                ->where('role', $validated['role'])
                ->where('status', 'active')
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
        });

        return redirect()
            ->route('organizations.orders.index', $organization)
            ->with('success', 'Сотрудник назначен на заказ.');
    }
}
