<?php

namespace Tests\Unit;

use App\Console\Commands\ConfigureOrganizationTwoTvFourMenu;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use Tests\TestCase;

#[CoversClass(ConfigureOrganizationTwoTvFourMenu::class)]
class ConfigureOrganizationTwoTvFourMenuTest extends TestCase
{
    public function test_menu_definition_contains_the_six_confirmed_items_and_prices(): void
    {
        $command = app(ConfigureOrganizationTwoTvFourMenu::class);

        $method = new ReflectionMethod($command, 'menuDefinition');
        $method->setAccessible(true);

        $menu = $method->invoke($command);

        $this->assertSame(['Лапша WOK', 'Паста'], array_keys($menu));
        $this->assertCount(4, $menu['Лапша WOK']);
        $this->assertCount(2, $menu['Паста']);

        $items = collect($menu)->collapse()->keyBy('name');

        $this->assertSame(350, $items['Манго чили']['price']);
        $this->assertSame(300, $items['Манго чили']['weight']);
        $this->assertSame(350, $items['Терияки']['price']);
        $this->assertSame(300, $items['Терияки']['weight']);
        $this->assertSame(350, $items['Сладкий чили']['price']);
        $this->assertSame(300, $items['Сладкий чили']['weight']);

        $this->assertSame(380, $items['С креветками Терияки']['price']);
        $this->assertSame(300, $items['С креветками Терияки']['weight']);
        $this->assertStringContainsString('креветки', $items['С креветками Терияки']['description']);
        $this->assertStringNotContainsString('жареная курица', $items['С креветками Терияки']['description']);

        $this->assertSame(350, $items['Карбонара']['price']);
        $this->assertSame(330, $items['Карбонара']['weight']);
        $this->assertSame(350, $items['С курицей и грибами']['price']);
        $this->assertSame(330, $items['С курицей и грибами']['weight']);
    }
}
