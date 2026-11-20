<?php

namespace Tests\Unit;

use App\Jobs\Iiko\SendIikoOrder;
use App\Models\IikoConnection;
use App\Models\Order;
use App\Services\Iiko\IikoOrderService;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use RuntimeException;
use Tests\TestCase;

class IikoOrderSubmissionSafetyTest extends TestCase
{
    public function test_order_submission_job_serializes_attempts_for_the_same_order(): void
    {
        $job = new SendIikoOrder(123);

        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_order_without_stable_idempotency_uuid_is_rejected_before_submission(): void
    {
        $order = new Order([
            'id' => 123,
            'idempotency_key' => null,
            'fulfillment_type' => Order::FULFILLMENT_PICKUP,
            'currency' => 'RUB',
        ]);

        $connection = new IikoConnection([
            'iiko_organization_id' => '11111111-1111-4111-8111-111111111111',
            'terminal_group_id' => '33333333-3333-4333-8333-333333333333',
            'iiko_currency_iso_name' => 'RUB',
        ]);

        try {
            app(IikoOrderService::class)->send($order, $connection);
            $this->fail('An order without a stable idempotency UUID must not be sent.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'У заказа отсутствует корректный ключ идемпотентности. Передача в iiko остановлена.',
                $exception->getMessage()
            );
        }
    }
}
