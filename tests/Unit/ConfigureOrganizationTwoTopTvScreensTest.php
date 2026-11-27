<?php

namespace Tests\Unit;

use App\Console\Commands\ConfigureOrganizationTwoTopTvScreens;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use Tests\TestCase;

#[CoversClass(ConfigureOrganizationTwoTopTvScreens::class)]
class ConfigureOrganizationTwoTopTvScreensTest extends TestCase
{
    public function test_two_additional_screens_contain_the_high_demand_sales_positions(): void
    {
        $reflection = new ReflectionClass(ConfigureOrganizationTwoTopTvScreens::class);
        $screens = $reflection->getReflectionConstant('SCREENS')->getValue();

        $this->assertSame([5, 6], array_keys($screens));
        $this->assertSame('Хиты кухни', $screens[5]['name']);
        $this->assertSame('Напитки и десерты', $screens[6]['name']);

        $this->assertCount(2, $screens[5]['sections']);
        $this->assertCount(4, $screens[6]['sections']);

        $screenFiveItems = collect($screens[5]['sections'])->flatten()->all();
        $screenSixItems = collect($screens[6]['sections'])->flatten()->all();

        $this->assertCount(19, $screenFiveItems);
        $this->assertCount(18, $screenSixItems);
        $this->assertContains('ШАУРМА КЛАССИКА С КУРИЦЕЙ СТ 370г', $screenFiveItems);
        $this->assertContains('КАРТОФЕЛЬ ФРИ 180г', $screenFiveItems);
        $this->assertContains('МОРС БРУСНИЧНЫЙ 500мл', $screenSixItems);
        $this->assertContains('МОРОЖЕНОЕ В РОЖКЕ 100г', $screenSixItems);
        $this->assertNotContains('КОМБО 1.11-15.11', $screenFiveItems);
    }
}
