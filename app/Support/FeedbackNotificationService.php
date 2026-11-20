<?php

namespace App\Support;

use App\Models\OrganizationFeedback;
use App\Models\OrderNotification;
use Illuminate\Support\Facades\DB;

class FeedbackNotificationService
{
    public function notifyOrganizationAboutNewFeedback(OrganizationFeedback $feedback): void
    {
        $managerIds = DB::table('organization_users')
            ->where('organization_id', $feedback->organization_id)
            ->where('role', 'manager')
            ->where('status', 'active')
            ->pluck('user_id');

        foreach ($managerIds as $managerId) {
            OrderNotification::query()->create([
                'organization_id' => $feedback->organization_id,
                'order_id' => $feedback->order_id,
                'feedback_id' => $feedback->id,
                'recipient_type' => 'user',
                'recipient_id' => $managerId,
                'event_type' => 'new_feedback',
                'title' => 'Новое обращение',
                'message' => 'Поступило новое обращение: ' . $feedback->subject,
            ]);
        }

        $adminIds = DB::table('organization_employee_memberships')
            ->where('organization_id', $feedback->organization_id)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->pluck('employee_id');

        foreach ($adminIds as $adminId) {
            OrderNotification::query()->create([
                'organization_id' => $feedback->organization_id,
                'order_id' => $feedback->order_id,
                'feedback_id' => $feedback->id,
                'recipient_type' => 'employee',
                'recipient_id' => $adminId,
                'event_type' => 'new_feedback',
                'title' => 'Новое обращение',
                'message' => 'Поступило новое обращение: ' . $feedback->subject,
            ]);
        }
    }

    public function notifyCustomerAboutReply(OrganizationFeedback $feedback): void
    {
        if (!$feedback->customer_id) return;

        OrderNotification::query()->create([
            'organization_id' => $feedback->organization_id,
            'order_id' => $feedback->order_id,
            'feedback_id' => $feedback->id,
            'recipient_type' => 'customer',
            'recipient_id' => $feedback->customer_id,
            'event_type' => 'feedback_replied',
            'title' => 'Ответ на ваше обращение',
            'message' => 'Организация ответила на обращение: ' . $feedback->subject,
        ]);
    }
}
