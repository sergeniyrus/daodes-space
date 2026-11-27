<?php
namespace App\Jobs;
use App\Exceptions\FirebaseMessagingException;
use App\Models\PushNotification;
use App\Models\PushNotificationDelivery;
use App\Services\FirebaseMessagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;
class SendCustomerPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 5;
    public array $backoff = [10, 60, 300, 900];
    public function __construct(public readonly int $deliveryId)
    {
    }
    public function handle(FirebaseMessagingService $messaging): void
    {
        $delivery = PushNotificationDelivery::query()->with(['notification', 'device'])->findOrFail($this->deliveryId);
        if ($delivery->status === 'sent') {
            return;
        }
        $device = $delivery->device;
        if (!$device || !$device->is_active) {
            $delivery->update(['status' => 'failed', 'last_error' => 'Device is no longer active.']);
            $this->refreshNotificationStatus($delivery->notification);
            return;
        }
        $delivery->increment('attempts');
        try {
            $messaging->send($device->push_token, $delivery->notification->title, $delivery->notification->body, $delivery->notification->data ?? []);
        } catch (FirebaseMessagingException $exception) {
            $delivery->update(['last_error' => Str::limit($exception->getMessage(), 1000)]);
            if ($exception->isUnregisteredToken()) {
                $device->update(['is_active' => false]);
                $delivery->update(['status' => 'failed']);
                $this->refreshNotificationStatus($delivery->notification);
                return;
            }
            throw $exception;
        } catch (Throwable $exception) {
            $delivery->update(['last_error' => Str::limit($exception->getMessage(), 1000)]);
            throw $exception;
        }
        $device->update(['last_seen_at' => now()]);
        $delivery->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
        $this->refreshNotificationStatus($delivery->notification);
    }
    public function failed(Throwable $exception): void
    {
        $delivery = PushNotificationDelivery::query()->with('notification')->find($this->deliveryId);
        if (!$delivery || $delivery->status === 'sent') {
            return;
        }
        $delivery->update(['status' => 'failed', 'last_error' => Str::limit($exception->getMessage(), 1000)]);
        $this->refreshNotificationStatus($delivery->notification);
    }
    private function refreshNotificationStatus(PushNotification $notification): void
    {
        $counts = $notification->deliveries()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $sent = (int) ($counts['sent'] ?? 0);
        $pending = (int) ($counts['pending'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);
        $status = $pending > 0 ? 'pending' : (($sent > 0 && $failed > 0) ? 'partial' : ($sent > 0 ? 'sent' : 'failed'));
        $notification->update(['status' => $status, 'sent_at' => $sent > 0 ? now() : null]);
    }
}
