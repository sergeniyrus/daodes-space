<?php

namespace App\Console\Commands;

use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ConfigureOrganizationTwoTopTvScreens extends Command
{
    protected $signature = 'menu:configure-org2-top-tv-screens {--apply : Apply the changes to organization 2}';

    protected $description = 'Prepare two additional top-selling menu screens for organization 2';

    private const ORGANIZATION_ID = 2;

    private const SCREENS = [
        5 => [
            'name' => 'Хиты кухни',
            'sections' => [
                'Шаурма и донер' => [
                    'ШАУРМА КЛАССИКА С КУРИЦЕЙ СТ 370г',
                    'ШАУРМА ДЕРЕВЕНСКАЯ С КУРИЦЕЙ СТ 370г',
                    'ШАУРМА ЦЕЗАРЬ 370г',
                    'ШАУРМА КЛАССИКА С КУРИЦЕЙ Б/М 410г',
                    'ДЕНЕР БОКС 360г',
                    'ШАУРМА СЫРНАЯ С КУРИЦЕЙ СТ 360г',
                ],
                'Горячее и закуски' => [
                    'КАРТОФЕЛЬ ФРИ 180г',
                    'КАРТОФЕЛЬ ФРИ 100г',
                    'НАГГЕТСЫ КУРИНЫЕ 5ШТ 100г',
                    'НАГГЕТСЫ КУРИНЫЕ 9ШТ 180г',
                    'СЫРНЫЕ ПАЛОЧКИ 100г',
                    'ГРЕНКИ С ЧЕСНОЧНЫМ СОУСОМ 100/30',
                    'СЭНДВИЧ С КУРИЦЕЙ 200г',
                    'ЛАПША ВОК ТЕРИЯКИ 300г',
                    'СТРИПСЫ КУРИНЫЕ 5ШТ 160г',
                    'СЭНДВИЧ С ВЕТЧИНОЙ И СЫРОМ 200г',
                    'ПАСТА С КУРИЦЕЙ И ГРИБАМИ 300г',
                    'ПАСТА КАРБОНАРА 300г',
                    'КОМБО детское 1-30.11',
                ],
            ],
        ],
        6 => [
            'name' => 'Напитки и десерты',
            'sections' => [
                'Напитки' => [
                    'МОРС БРУСНИЧНЫЙ 500мл',
                    'ЧАЙ ПАКЕТИРОВАННЫЙ 1шт',
                    'ЛИМОНАД 500мл',
                    'КОФЕ КАПУЧИНО 300мл',
                    'КОМПОТ ИЗ СУХОФРУКТОВ 500мл',
                    'НАПИТОК ИЗ ШИПОВНИКА 500мл',
                    'БОНАКВА Б/Г 0.5л',
                    'ДОБРЫЙ КОЛА 0.5л',
                ],
                'Молочные коктейли' => [
                    'МОЛ. КОКТ. КЛУБНИЧНЫЙ 300мл',
                    'МОЛ. КОКТ. КЛАССИЧЕСКИЙ 300мл',
                    'МОЛ. КОКТ. КЛУБНИЧНЫЙ 500мл',
                    'МОЛ. КОКТ. КЛАССИЧЕСКИЙ 500мл',
                ],
                'Десерты' => [
                    'МОРОЖЕНОЕ В РОЖКЕ 100г',
                    'МОРОЖЕНОЕ В РОЖКЕ 100г АКЦИЯ 50%',
                    'МЕРЕНГИ 25г',
                ],
                'Соусы и добавки' => [
                    'СОУС СЫРНЫЙ 25г',
                    'КЕТЧУП 25г',
                    'СОУС ЧЕСНОЧНЫЙ 25г',
                ],
            ],
        ],
    ];

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

        $this->info('Организация: ' . $organization->name . ' (ID 2)');
        $missing = [];
        $matched = [];

        $sourceItems = MenuItem::query()
            ->whereHas('section', function ($query) {
                $query->where('organization_id', self::ORGANIZATION_ID);
            })
            ->where('is_active', true)
            ->with('section')
            ->orderBy('id')
            ->get();

        $sourceByName = $sourceItems->groupBy(fn (MenuItem $item) => $this->normalizeName($item->name));

        foreach (self::SCREENS as $screen => $screenData) {
            $this->newLine();
            $this->info('TV' . $screen . ' — ' . $screenData['name']);

            foreach ($screenData['sections'] as $sectionName => $names) {
                $this->line('  ' . $sectionName . ':');

                foreach ($names as $name) {
                    $matches = $sourceByName->get($this->normalizeName($name), collect());
                    $source = $matches->first();

                    if (!$source) {
                        $missing[] = $name;
                        $this->error('    НЕ НАЙДЕНО в активном меню: ' . $name);
                        continue;
                    }

                    $matched[$screen][$sectionName][] = [
                        'name' => $name,
                        'source_id' => $source->id,
                    ];

                    $this->line(sprintf(
                        '    • %s — %s ₽ / %s %s',
                        $source->name,
                        number_format((float) $source->price, 0, ',', ' '),
                        $source->weight ?? '—',
                        $source->unit ?? ''
                    ));
                }
            }
        }

        if ($missing !== []) {
            $this->newLine();
            $this->error('Нельзя применить настройку: отсутствуют активные позиции из отчёта продаж.');
            $this->line('Сначала сверьте названия и активность этих позиций в меню организации №2.');

            return self::FAILURE;
        }

        if (!$this->option('apply')) {
            $this->newLine();
            $this->warn('Режим проверки: база данных не изменена.');
            $this->line('Для применения: php artisan menu:configure-org2-top-tv-screens --apply');

            return self::SUCCESS;
        }

        $result = DB::transaction(function () use ($matched): array {
            $organization = Organization::query()
                ->whereKey(self::ORGANIZATION_ID)
                ->lockForUpdate()
                ->firstOrFail();

            $screenConfigs = $organization->tv_screens_config ?? [];
            $createdSections = 0;
            $createdItems = 0;
            $existingItems = 0;

            foreach (self::SCREENS as $screen => $screenData) {
                foreach (['name', 'background_fit', 'rotation_seconds'] as $key) {
                    // Preserve existing screen configuration and background CID.
                    if ($key === 'name') {
                        $screenConfigs[$screen][$key] = $screenData['name'];
                    } elseif (!isset($screenConfigs[$screen][$key])) {
                        $screenConfigs[$screen][$key] = $key === 'background_fit' ? 'cover' : 0;
                    }
                }

                foreach ($screenData['sections'] as $sectionName => $names) {
                    $section = MenuSection::query()
                        ->where('organization_id', self::ORGANIZATION_ID)
                        ->where('tv_screen', $screen)
                        ->where('name', $sectionName)
                        ->first();

                    if (!$section) {
                        $section = $organization->menuSections()->create([
                            'name' => $sectionName,
                            'sort_order' => ((int) $organization->menuSections()->max('sort_order')) + 1,
                            'tv_screen' => $screen,
                            'is_active' => true,
                            'delivery_enabled' => true,
                            'pickup_enabled' => true,
                            'dine_in_enabled' => true,
                        ]);
                        $createdSections++;
                    }

                    foreach ($matched[$screen][$sectionName] as $entry) {
                        $source = MenuItem::query()->findOrFail($entry['source_id']);
                        $alreadyThere = $section->items()
                            ->get()
                            ->first(fn (MenuItem $item) => $this->normalizeName($item->name) === $this->normalizeName($entry['name']));

                        if ($alreadyThere) {
                            $existingItems++;
                            continue;
                        }

                        $copy = $source->replicate();
                        $copy->menu_section_id = $section->id;
                        $copy->sort_order = ((int) $section->items()->max('sort_order')) + 1;
                        $copy->save();
                        $createdItems++;
                    }
                }
            }

            $organization->tv_screens_count = max(
                6,
                (int) ($organization->tv_screens_count ?? 3)
            );
            $organization->tv_screens_config = $screenConfigs;
            $organization->save();

            return compact('createdSections', 'createdItems', 'existingItems');
        });

        $this->newLine();
        $this->info('TV5 и TV6 настроены.');
        $this->line('Создано разделов: ' . $result['createdSections']);
        $this->line('Скопировано позиций: ' . $result['createdItems']);
        $this->line('Уже присутствовало на целевых экранах: ' . $result['existingItems']);
        $this->line('TV5: /organization/2/tv?screen=5');
        $this->line('TV6: /organization/2/tv?screen=6');

        return self::SUCCESS;
    }

    private function normalizeName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', trim(Str::lower($name)));
    }

    private function hasRequiredSchema(): bool
    {
        return Schema::hasTable('organizations')
            && Schema::hasTable('menu_sections')
            && Schema::hasTable('menu_items')
            && Schema::hasColumn('organizations', 'tv_screens_count')
            && Schema::hasColumn('organizations', 'tv_screens_config')
            && Schema::hasColumns('menu_sections', [
                'organization_id', 'name', 'sort_order', 'tv_screen', 'is_active',
                'delivery_enabled', 'pickup_enabled', 'dine_in_enabled',
            ])
            && Schema::hasColumns('menu_items', [
                'menu_section_id', 'name', 'weight', 'unit', 'price', 'description',
                'sort_order', 'is_active', 'image_path', 'image_cid',
                'delivery_enabled', 'pickup_enabled', 'dine_in_enabled',
            ]);
    }
}
