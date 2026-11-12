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
            $updates = ['status' => $toStatus];

            if ($toStatus === Order::STATUS_CONFIRMED) {
                if ($fromStatus !== Order::STATUS_NEW) {
                    throw ValidationException::withMessages([
                        'status' => 'Подтвердить можно только новый заказ.',
                    ]);
                }

                $updates['confirmed_at'] = now();
            } elseif ($toStatus === Order::STATUS_ISSUED) {
                if (
                    $fromStatus !== Order::STATUS_READY
                    || $lockedOrder->fulfillment_type !== Order::FULFILLMENT_PICKUP
                ) {
                    throw ValidationException::withMessages([
                        'status' => 'Выдать можно только готовый заказ на самовывоз.',
                    ]);
                }
            } elseif ($toStatus === Order::STATUS_SERVED) {
                if (
                    $fromStatus !== Order::STATUS_READY
                    || $lockedOrder->fulfillment_type !== Order::FULFILLMENT_DINE_IN
                ) {
                    throw ValidationException::withMessages([
                        'status' => 'Отметить подачу можно только для готового заказа в заведении.',
                    ]);
                }
            } elseif ($toStatus === Order::STATUS_COMPLETED) {
                if (!in_array($fromStatus, [
                    Order::STATUS_DELIVERED,
                    Order::STATUS_ISSUED,
                    Order::STATUS_SERVED,
                ], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Завершить можно только доставленный или выданный заказ.',
                    ]);
                }

                if ($lockedOrder->payment_status !== Order::PAYMENT_SUCCEEDED) {
                    throw ValidationException::withMessages([
                        'status' => 'Сначала отметьте получение оплаты.',
                    ]);
                }

                $updates['completed_at'] = now();
            } elseif ($toStatus === Order::STATUS_CANCELLED) {
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

                $updates['cancelled_at'] = now();
                $updates['payment_status'] = Order::PAYMENT_CANCELLED;

                $lockedOrder->payments()
                    ->where('status', Order::PAYMENT_PENDING)
                    ->update([
                        'status' => Order::PAYMENT_CANCELLED,
                    ]);
            }

            $lockedOrder->update($updates);

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
