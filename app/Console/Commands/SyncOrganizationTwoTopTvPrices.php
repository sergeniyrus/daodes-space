<?php

namespace App\Console\Commands;

use App\Models\MenuItem;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SyncOrganizationTwoTopTvPrices extends Command
{
    protected $signature = 'menu:sync-org2-top-tv-prices {--apply : Apply price changes to TV5 and TV6}';

    protected $description = 'Safely synchronize TV5 and TV6 prices with the source menu of organization 2';

    private const ORGANIZATION_ID = 2;

    private const SOURCE_SCREENS = [1, 2, 3, 4];

    private const TARGET_SCREENS = [5, 6];

    public function handle(): int
    {
        if (!$this->hasRequiredSchema()) {
            $this->error('Не найдена необходимая схема меню. База данных не изменена.');

            return self::FAILURE;
        }

        if (!Organization::query()->whereKey(self::ORGANIZATION_ID)->exists()) {
            $this->error('Организация с ID 2 не найдена.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        $plan = $apply
            ? DB::transaction(function () {
                Organization::query()
                    ->whereKey(self::ORGANIZATION_ID)
                    ->lockForUpdate()
                    ->firstOrFail();

                MenuSection::query()
                    ->where('organization_id', self::ORGANIZATION_ID)
                    ->whereIn('tv_screen', array_merge(self::SOURCE_SCREENS, self::TARGET_SCREENS))
                    ->lockForUpdate()
                    ->get(['id']);

                $plan = $this->buildPlan(true);

                if ($plan['errors'] === []) {
                    foreach ($plan['updates'] as $update) {
                        MenuItem::query()
                            ->whereKey($update['target_id'])
                            ->update(['price' => $update['new_price']]);
                    }
                }

                return $plan;
            })
            : $this->buildPlan(false);

        if ($plan['errors'] !== []) {
            $this->newLine();
            $this->error('Синхронизация остановлена: найдены неоднозначные или небезопасные позиции.');

            foreach ($plan['errors'] as $error) {
                $this->line(' - ' . $error);
            }

            $this->line('Ни одна цена не изменена.');

            return self::FAILURE;
        }

        if ($plan['updates'] === []) {
            $this->info('TV5 и TV6 уже соответствуют исходному меню. Изменений нет.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info($apply ? 'Синхронизированные цены TV5 и TV6:' : 'План изменения цен TV5 и TV6:');

        foreach ($plan['updates'] as $update) {
            $this->line(sprintf(
                'TV%d | %s | %s ₽ → %s ₽',
                $update['screen'],
                $update['name'],
                $update['old_price'],
                $update['new_price']
            ));
        }

        if (!$apply) {
            $this->newLine();
            $this->warn('Dry-run: база данных не изменена.');
            $this->line('Для применения после проверки: php artisan menu:sync-org2-top-tv-prices --apply');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Цены TV5 и TV6 синхронизированы.');
        $this->line('Обновлено позиций: ' . count($plan['updates']));
        $this->line('Изменялось только поле price. Названия, составы, вес, изображения и настройки доступности не менялись.');

        return self::SUCCESS;
    }

    /**
     * Собирает план без записи. При --apply вызывается внутри транзакции
     * после блокировки организации; проверка и запись идут одним этапом.
     */
    private function buildPlan(bool $forUpdate): array
    {
        $query = MenuItem::query()
            ->whereHas('section', function ($sectionQuery) {
                $sectionQuery
                    ->where('organization_id', self::ORGANIZATION_ID)
                    ->whereIn('tv_screen', array_merge(self::SOURCE_SCREENS, self::TARGET_SCREENS));
            })
            ->with('section')
            ->orderBy('id');

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $items = $query->get();

        $sourceItems = $items->filter(
            fn (MenuItem $item) => in_array((int) $item->section->tv_screen, self::SOURCE_SCREENS, true)
        );

        $targetItems = $items->filter(
            fn (MenuItem $item) => in_array((int) $item->section->tv_screen, self::TARGET_SCREENS, true)
        );

        $errors = [];
        $updates = [];

        if ($targetItems->isEmpty()) {
            return [
                'errors' => ['На TV5 и TV6 пока нет блюд для синхронизации.'],
                'updates' => [],
            ];
        }

        $targetDuplicates = $targetItems->groupBy(
            fn (MenuItem $item) => (int) $item->section->tv_screen . ':' . $this->normalizeName($item->name)
        );

        foreach ($targetDuplicates as $key => $duplicates) {
            if ($duplicates->count() > 1) {
                $errors[] = 'Дубликаты на целевых экранах (' . $key . '): ' . $duplicates->count() . ' записей.';
            }
        }

        foreach ($targetItems as $target) {
            $screen = (int) $target->section->tv_screen;

            if (!$target->section->is_active) {
                $errors[] = 'Раздел назначения выключен: TV' . $screen . ' / ' . $target->section->name . '.';
                continue;
            }

            if (!$target->is_active) {
                $errors[] = 'Целевое блюдо выключено и не будет изменено: TV' . $screen . ' / ' . $target->name . '.';
                continue;
            }

            $namedSources = $sourceItems->filter(
                fn (MenuItem $source) => $this->normalizeName($source->name) === $this->normalizeName($target->name)
            );

            if ($namedSources->isEmpty()) {
                $errors[] = 'Не найдено исходное блюдо на TV1–TV4: ' . $target->name . ' (TV' . $screen . ').';
                continue;
            }

            $activeSources = $namedSources->filter(
                fn (MenuItem $source) => $source->is_active && $source->section->is_active
            );

            if ($activeSources->count() !== 1) {
                $errors[] = $activeSources->isEmpty()
                    ? 'Исходное блюдо неактивно: ' . $target->name . '.'
                    : 'Несколько исходных блюд с таким названием на TV1–TV4: ' . $target->name . '.';
                continue;
            }

            $source = $activeSources->first();

            if (!$this->sameNonPriceData($source, $target)) {
                $errors[] = 'Поля блюда, кроме цены, отличаются; автоматическое сопоставление запрещено: TV' . $screen . ' / ' . $target->name . '.';
                continue;
            }

            $oldPrice = $this->normalizePrice($target->price);
            $newPrice = $this->normalizePrice($source->price);

            if ($oldPrice === $newPrice) {
                continue;
            }

            $updates[] = [
                'target_id' => (int) $target->id,
                'screen' => $screen,
                'name' => $target->name,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
            ];
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'updates' => $updates,
        ];
    }

    private function normalizeName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', trim(Str::lower($name)));
    }

    private function normalizePrice(mixed $price): string
    {
        return number_format((float) $price, 2, '.', '');
    }

    /**
     * Цены сравниваются отдельно; все остальные поля обязаны совпадать.
     */
    private function sameNonPriceData(MenuItem $source, MenuItem $target): bool
    {
        return $this->normalizeName($source->name) === $this->normalizeName($target->name)
            && (string) $source->weight === (string) $target->weight
            && (string) $source->unit === (string) $target->unit
            && (string) $source->description === (string) $target->description
            && (string) $source->image_path === (string) $target->image_path
            && (string) $source->image_cid === (string) $target->image_cid
            && (bool) $source->delivery_enabled === (bool) $target->delivery_enabled
            && (bool) $source->pickup_enabled === (bool) $target->pickup_enabled
            && (bool) $source->dine_in_enabled === (bool) $target->dine_in_enabled;
    }

    private function hasRequiredSchema(): bool
    {
        return Schema::hasTable('organizations')
            && Schema::hasTable('menu_sections')
            && Schema::hasTable('menu_items')
            && Schema::hasColumn('menu_sections', 'organization_id')
            && Schema::hasColumn('menu_sections', 'tv_screen')
            && Schema::hasColumn('menu_sections', 'is_active')
            && Schema::hasColumns('menu_items', [
                'menu_section_id',
                'name',
                'weight',
                'unit',
                'price',
                'description',
                'is_active',
                'image_path',
                'image_cid',
                'delivery_enabled',
                'pickup_enabled',
                'dine_in_enabled',
            ]);
    }
}
