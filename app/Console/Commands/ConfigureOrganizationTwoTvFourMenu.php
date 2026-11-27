<?php

namespace App\Console\Commands;

use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConfigureOrganizationTwoTvFourMenu extends Command
{
    protected $signature = 'menu:configure-org2-tv4 {--apply : Apply the changes to organization 2}';

    protected $description = 'Prepare the WOK and pasta menu on organization 2 TV4';

    private const ORGANIZATION_ID = 2;

    private const SCREEN_NUMBER = 4;

    private const SCREEN_NAME = 'Шаурма воки 2';

    public function handle(): int
    {
        if (!$this->hasRequiredSchema()) {
            $this->error('Не найдена необходимая схема меню или TV-экранов. Проверьте миграции перед запуском.');

            return self::FAILURE;
        }

        $organization = Organization::query()->find(self::ORGANIZATION_ID);

        if (!$organization) {
            $this->error('Организация с ID 2 не найдена.');

            return self::FAILURE;
        }

        $this->info('Организация: ' . $organization->name . ' (ID ' . self::ORGANIZATION_ID . ')');
        $this->info('Экран: TV4 — ' . self::SCREEN_NAME);
        $this->newLine();

        $menu = $this->menuDefinition();

        foreach ($menu as $sectionName => $items) {
            $this->line($sectionName . ':');

            foreach ($items as $item) {
                $this->line(sprintf(
                    '  • %s — %s ₽ / %s г',
                    $item['name'],
                    number_format($item['price'], 0, ',', ' '),
                    $item['weight']
                ));
            }
        }

        if (!$this->option('apply')) {
            $this->newLine();
            $this->warn('Режим проверки: база данных не изменена.');
            $this->line('Для применения запустите: php artisan menu:configure-org2-tv4 --apply');

            return self::SUCCESS;
        }

        $result = DB::transaction(function () use ($menu): array {
            $lockedOrganization = Organization::query()
                ->whereKey(self::ORGANIZATION_ID)
                ->lockForUpdate()
                ->firstOrFail();

            $currentScreenCount = max(
                1,
                (int) ($lockedOrganization->tv_screens_count ?? 3)
            );

            $screenConfigs = $lockedOrganization->tv_screens_config ?? [];
            $screenConfig = $screenConfigs[self::SCREEN_NUMBER] ?? [];

            $screenConfig['name'] = self::SCREEN_NAME;
            $screenConfig['background_fit'] = $screenConfig['background_fit'] ?? 'cover';
            $screenConfig['rotation_seconds'] = (int) ($screenConfig['rotation_seconds'] ?? 0);

            $screenConfigs[self::SCREEN_NUMBER] = $screenConfig;

            $lockedOrganization->tv_screens_count = max(
                $currentScreenCount,
                self::SCREEN_NUMBER
            );
            $lockedOrganization->tv_screens_config = $screenConfigs;
            $lockedOrganization->save();

            $createdSections = 0;
            $createdItems = 0;
            $existingItems = 0;

            foreach ($menu as $sectionName => $items) {
                $section = MenuSection::query()
                    ->where('organization_id', self::ORGANIZATION_ID)
                    ->where('tv_screen', self::SCREEN_NUMBER)
                    ->where('name', $sectionName)
                    ->first();

                if (!$section) {
                    $maxSortOrder = $lockedOrganization->menuSections()->max('sort_order');

                    $section = $lockedOrganization->menuSections()->create([
                        'name' => $sectionName,
                        'sort_order' => ($maxSortOrder ?? 0) + 1,
                        'tv_screen' => self::SCREEN_NUMBER,
                        'is_active' => true,
                        'delivery_enabled' => true,
                        'pickup_enabled' => true,
                        'dine_in_enabled' => true,
                    ]);

                    $createdSections++;
                }

                foreach ($items as $itemData) {
                    $existingItem = MenuItem::query()
                        ->where('menu_section_id', $section->id)
                        ->where('name', $itemData['name'])
                        ->first();

                    if ($existingItem) {
                        // Не перезаписываем существующие блюда: их цена, состав,
                        // активность и доступность могли быть изменены владельцем.
                        $existingItems++;

                        continue;
                    }

                    $maxItemSortOrder = $section->items()->max('sort_order');

                    $section->items()->create([
                        'name' => $itemData['name'],
                        'weight' => $itemData['weight'],
                        'unit' => 'г',
                        'price' => $itemData['price'],
                        'description' => $itemData['description'],
                        'sort_order' => ($maxItemSortOrder ?? 0) + 1,
                        'is_active' => true,
                        'image_path' => null,
                        'image_cid' => null,
                        'delivery_enabled' => true,
                        'pickup_enabled' => true,
                        'dine_in_enabled' => true,
                    ]);

                    $createdItems++;
                }
            }

            return [
                'screen_count' => (int) $lockedOrganization->tv_screens_count,
                'created_sections' => $createdSections,
                'created_items' => $createdItems,
                'existing_items' => $existingItems,
            ];
        });

        $this->newLine();
        $this->info('Настройки TV4 и меню организации №2 сохранены.');
        $this->line('Количество TV-экранов: ' . $result['screen_count']);
        $this->line('Создано разделов: ' . $result['created_sections']);
        $this->line('Создано блюд: ' . $result['created_items']);
        $this->line('Уже существовало и оставлено без изменений: ' . $result['existing_items']);
        $this->newLine();
        $this->line('Адрес экрана: /organization/2/tv?screen=4');

        return self::SUCCESS;
    }

    private function hasRequiredSchema(): bool
    {
        return Schema::hasTable('organizations')
            && Schema::hasTable('menu_sections')
            && Schema::hasTable('menu_items')
            && Schema::hasColumn('organizations', 'tv_screens_count')
            && Schema::hasColumn('organizations', 'tv_screens_config')
            && Schema::hasColumns('menu_sections', [
                'organization_id',
                'name',
                'sort_order',
                'tv_screen',
                'is_active',
                'delivery_enabled',
                'pickup_enabled',
                'dine_in_enabled',
            ])
            && Schema::hasColumns('menu_items', [
                'menu_section_id',
                'name',
                'weight',
                'unit',
                'price',
                'description',
                'sort_order',
                'is_active',
                'image_path',
                'image_cid',
                'delivery_enabled',
                'pickup_enabled',
                'dine_in_enabled',
            ]);
    }

    private function menuDefinition(): array
    {
        return [
            'Лапша WOK' => [
                [
                    'name' => 'Манго чили',
                    'weight' => 300,
                    'price' => 350,
                    'description' => 'Лапша удон, жареная курица, болгарский перец, красный лук, морковь, соус манго чили, зелёный лук, кунжут.',
                ],
                [
                    'name' => 'Терияки',
                    'weight' => 300,
                    'price' => 350,
                    'description' => 'Лапша удон, жареная курица, болгарский перец, красный лук, морковь, соус терияки, зелёный лук, кунжут.',
                ],
                [
                    'name' => 'Сладкий чили',
                    'weight' => 300,
                    'price' => 350,
                    'description' => 'Лапша удон, жареная курица, болгарский перец, красный лук, морковь, соус сладкий чили, зелёный лук, кунжут.',
                ],
                [
                    'name' => 'С креветками Терияки',
                    'weight' => 300,
                    'price' => 380,
                    'description' => 'Лапша удон, креветки, болгарский перец, красный лук, морковь, соус терияки, зелёный лук, кунжут.',
                ],
            ],
            'Паста' => [
                [
                    'name' => 'Карбонара',
                    'weight' => 330,
                    'price' => 350,
                    'description' => 'Феттучини, бекон, красный лук, сливки, моцарелла, пармезан, желток.',
                ],
                [
                    'name' => 'С курицей и грибами',
                    'weight' => 330,
                    'price' => 350,
                    'description' => 'Феттучини, жареная курица, шампиньоны, репчатый лук, сливки, моцарелла, зелёный лук.',
                ],
            ],
        ];
    }
}
