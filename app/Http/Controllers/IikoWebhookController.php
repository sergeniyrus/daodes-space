<?php

namespace App\Http\Controllers;

use App\Jobs\Iiko\ProcessIikoWebhookEvent;
use App\Models\IikoConnection;
use App\Models\IikoWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IikoWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $events = $request->json()->all();

        if (!is_array($events)) {
            return response()->json(['accepted' => false], 400);
        }

        // iiko sends an array. Accept one object as well for safe diagnostics.
        if (isset($events['eventType'])) {
            $events = [$events];
        }

        $authorization = (string) $request->header('Authorization', '');
        $accepted = 0;

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $organizationId = (string) (
                $event['organizationId']
                ?? data_get($event, 'eventInfo.organizationId')
                ?? ''
            );

            if ($organizationId === '') {
                continue;
            }

            $connection = IikoConnection::query()
                ->where('iiko_organization_id', $organizationId)
                ->where('webhook_enabled', true)
                ->first();

            if (!$connection || !filled($connection->webhook_secret)) {
                continue;
            }

            if (!hash_equals((string) $connection->webhook_secret, $authorization)) {
                return response()->json(['accepted' => false], 401);
            }

            $eventType = (string) ($event['eventType'] ?? 'Unknown');
            $eventInfo = is_array($event['eventInfo'] ?? null) ? $event['eventInfo'] : [];
            $orderInfo = is_array($eventInfo['order'] ?? null) ? $eventInfo['order'] : [];

            $iikoOrderId = isset($eventInfo['id']) ? (string) $eventInfo['id'] : null;
            $eventStatus = (string) (
                $orderInfo['status']
                ?? $eventInfo['creationStatus']
                ?? ''
            );

            $eventKey = hash('sha256', implode('|', [
                $organizationId,
                $eventType,
                $iikoOrderId ?? '',
                (string) ($event['eventTime'] ?? ''),
                (string) ($event['correlationId'] ?? ''),
                $eventStatus,
            ]));

            $inserted = DB::table('iiko_webhook_events')->insertOrIgnore([
                'organization_id' => $connection->organization_id,
                'iiko_connection_id' => $connection->id,
                'iiko_organization_id' => $connection->iiko_organization_id,
                'terminal_group_id' => $connection->terminal_group_id,
                'event_key' => $eventKey,
                'event_type' => $eventType,
                'iiko_order_id' => $iikoOrderId,
                'event_status' => $eventStatus !== '' ? $eventStatus : null,
                'processing_status' => 'pending',
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted) {
                $webhookEvent = IikoWebhookEvent::query()
                    ->where('event_key', $eventKey)
                    ->first();

                if ($webhookEvent) {
                    ProcessIikoWebhookEvent::dispatch($webhookEvent->id)->afterCommit();
                }
            }

            $accepted++;
        }

        // Respond quickly. Heavy processing and stop-list refresh run in queue jobs.
        return response()->json([
            'accepted' => true,
            'events' => $accepted,
        ], 200);
    }
}
