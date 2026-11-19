<?php

namespace App\Http\Controllers;

use App\Models\OrderNotification;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrderNotificationController extends Controller
{
    public function managerIndex(Organization $organization): JsonResponse
    {
        $user = Auth::user();

        abort_unless($user, 401);

        return $this->notificationsResponse(
            'user',
            (int) $user->id,
            $organization,
            route('organizations.orders.index', $organization)
        );
    }

    public function employeeIndex(Organization $organization): JsonResponse
    {
        $employee = Auth::guard('employee')->user();

        abort_unless($employee, 401);

        return $this->notificationsResponse(
            'employee',
            (int) $employee->id,
            $organization,
            route('employee.organization.orders.index', $organization)
        );
    }

    public function markManagerRead(
        Organization $organization,
        OrderNotification $notification
    ): JsonResponse {
        $user = Auth::user();

        abort_unless($user, 401);

        return $this->markRead(
            'user',
            (int) $user->id,
            $organization,
            $notification
        );
    }

    public function employeeAdminIndex(Organization $organization): JsonResponse
    {
        $employee = Auth::guard('employee')->user();
        abort_unless($employee, 401);
        return $this->notificationsResponse(
            'employee',
            (int) $employee->id,
            $organization,
            route('employee.organization.admin.orders.index', $organization)
        );
    }

    public function markEmployeeAdminRead(Organization $organization, OrderNotification $notification): JsonResponse
    {
        $employee = Auth::guard('employee')->user();
        abort_unless($employee, 401);
        return $this->markRead('employee', (int) $employee->id, $organization, $notification);
    }

    public function markEmployeeRead(
        Organization $organization,
        OrderNotification $notification
    ): JsonResponse {
        $employee = Auth::guard('employee')->user();

        abort_unless($employee, 401);

        return $this->markRead(
            'employee',
            (int) $employee->id,
            $organization,
            $notification
        );
    }

    public function customerIndex(): JsonResponse
    {
        $customer = Auth::guard('customer')->user();

        abort_unless($customer, 401);

        $query = OrderNotification::query()
            ->where('recipient_type', 'customer')
            ->where('recipient_id', $customer->id)
            ->whereNull('read_at')
            ->where(function ($owned) use ($customer) {
                $owned->whereHas('order', fn ($orderQuery) => $orderQuery->where('customer_id', $customer->id))
                    ->orWhereHas('feedback', fn ($feedbackQuery) => $feedbackQuery->where('customer_id', $customer->id));
            });

        $unreadCount = (clone $query)->count();

        $notifications = (clone $query)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (OrderNotification $notification) => [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'created_at' => $notification->created_at?->toIso8601String(),
                'url' => $notification->feedback_id
                    ? route('customer.feedback.index')
                    : route('customer.orders.show', $notification->order_id),
            ])
            ->values();

        return response()
            ->json([
                'unread_count' => $unreadCount,
                'notifications' => $notifications,
            ])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function markCustomerRead(OrderNotification $notification): JsonResponse
    {
        $customer = Auth::guard('customer')->user();

        abort_unless($customer, 401);

        $ownedNotification = OrderNotification::query()
            ->whereKey($notification->id)
            ->where('recipient_type', 'customer')
            ->where('recipient_id', $customer->id)
            ->where(function ($owned) use ($customer) {
                $owned->whereHas('order', fn ($orderQuery) => $orderQuery->where('customer_id', $customer->id))
                    ->orWhereHas('feedback', fn ($feedbackQuery) => $feedbackQuery->where('customer_id', $customer->id));
            })
            ->firstOrFail();

        if ($ownedNotification->read_at === null) {
            $ownedNotification->update([
                'read_at' => now(),
            ]);
        }

        return response()
            ->json(['marked_read' => true])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function notificationsResponse(
        string $recipientType,
        int $recipientId,
        Organization $organization,
        string $targetUrl
    ): JsonResponse {
        $query = OrderNotification::query()
            ->where('organization_id', $organization->id)
            ->where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId)
            ->whereNull('read_at');

        $unreadCount = (clone $query)->count();

        $notifications = (clone $query)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (OrderNotification $notification) => [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'created_at' => $notification->created_at?->toIso8601String(),
                'url' => $notification->feedback_id
                    ? ($recipientType === 'employee'
                        ? route('employee.organization.admin.feedback.index', $organization)
                        : route('organizations.feedback.index', $organization))
                    : $targetUrl,
            ])
            ->values();

        return response()
            ->json([
                'unread_count' => $unreadCount,
                'notifications' => $notifications,
            ])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function markRead(
        string $recipientType,
        int $recipientId,
        Organization $organization,
        OrderNotification $notification
    ): JsonResponse {
        $ownedNotification = OrderNotification::query()
            ->whereKey($notification->id)
            ->where('organization_id', $organization->id)
            ->where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId)
            ->firstOrFail();

        if ($ownedNotification->read_at === null) {
            $ownedNotification->update([
                'read_at' => now(),
            ]);
        }

        return response()
            ->json(['marked_read' => true])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
