<?php

namespace Tests\Unit;

use App\Console\Commands\SyncOrganizationTwoTopTvPrices;
use App\Models\MenuItem;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use Tests\TestCase;

#[CoversClass(SyncOrganizationTwoTopTvPrices::class)]
class SyncOrganizationTwoTopTvPricesTest extends TestCase
{
    private function invokePrivate(string $method, array $arguments): mixed
    {
        $reflection = new ReflectionClass(SyncOrganizationTwoTopTvPrices::class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $callable = $reflection->getMethod($method);

        return $callable->invokeArgs($instance, $arguments);
    }

    public function test_names_are_normalized_before_matching(): void
    {
        $normalized = $this->invokePrivate('normalizeName', ["  ШАУРМА   КЛАССИКА  С КУРИЦЕЙ  "]);

        $this->assertSame('шаурма классика с курицей', $normalized);
    }

    public function test_price_is_the_only_field_allowed_to_differ_during_sync(): void
    {
        $source = new MenuItem([
            'name' => 'Шаурма классика',
            'weight' => '370.000',
            'unit' => 'г',
            'price' => '350.00',
            'description' => 'Курица, овощи, соус',
            'image_path' => null,
            'image_cid' => 'bafy-source',
            'delivery_enabled' => true,
            'pickup_enabled' => true,
            'dine_in_enabled' => false,
        ]);

        $target = new MenuItem([
            'name' => 'Шаурма классика',
            'weight' => '370.000',
            'unit' => 'г',
            'price' => '390.00',
            'description' => 'Курица, овощи, соус',
            'image_path' => null,
            'image_cid' => 'bafy-source',
            'delivery_enabled' => true,
            'pickup_enabled' => true,
            'dine_in_enabled' => false,
        ]);

        $this->assertTrue($this->invokePrivate('sameNonPriceData', [$source, $target]));

        $target->description = 'Изменённый состав';

        $this->assertFalse($this->invokePrivate('sameNonPriceData', [$source, $target]));
    }

    public function test_fulfillment_flags_are_part_of_the_safety_match(): void
    {
        $source = new MenuItem([
            'name' => 'Лимонад',
            'weight' => '500.000',
            'unit' => 'мл',
            'price' => '150.00',
            'description' => null,
            'image_path' => null,
            'image_cid' => null,
            'delivery_enabled' => true,
            'pickup_enabled' => true,
            'dine_in_enabled' => true,
        ]);

        $target = new MenuItem([
            'name' => 'Лимонад',
            'weight' => '500.000',
            'unit' => 'мл',
            'price' => '170.00',
            'description' => null,
            'image_path' => null,
            'image_cid' => null,
            'delivery_enabled' => true,
            'pickup_enabled' => false,
            'dine_in_enabled' => true,
        ]);

        $this->assertFalse($this->invokePrivate('sameNonPriceData', [$source, $target]));
    }
}
