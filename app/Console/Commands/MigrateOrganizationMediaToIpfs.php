<?php

namespace App\\Console\\Commands;

use App\\Models\\MenuItem;
use App\\Models\\Organization;
use App\\Services\\IPFSService;
use Illuminate\\Console\\Command;
use Illuminate\\Http\\UploadedFile;

class MigrateOrganizationMediaToIpfs extends Command
{
    protected $signature = 'organizations:media-to-ipfs
                            {--organization= : ID организации для переноса}
                            {--dry-run : Только показать файлы, не загружая их}';

    protected $description = 'Переносит фотографии блюд и фоны TV-экранов организаций в IPFS';

    public function handle(IPFSService $ipfs): int
    {
        $organizationOption = $this->option('organization');
        $organizationId = $organizationOption !== null && $organizationOption !== ''
            ? (int) $organizationOption
            : null;

        if ($organizationOption !== null && $organizationOption !== '' && $organizationId < 1) {
            $this->error('Параметр --organization должен содержать положительный ID.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $uploaded = 0;
        $skipped = 0;
        $failed = 0;

        if ($dryRun) {
            $this->warn('DRY RUN: файлы не будут загружены, база данных не изменится.');
        }

        $this->info('Перенос фотографий блюд...');

        $itemsQuery = MenuItem::query()
            ->whereNull('image_cid')
            ->whereNotNull('image_path')
            ->with('section')
            ->when($organizationId, function ($query) use ($organizationId) {
                $query->whereHas('section', function ($sectionQuery) use ($organizationId) {
                    $sectionQuery->where('organization_id', $organizationId);
                });
            })
            ->orderBy('id');

        $itemsQuery->chunkById(50, function ($items) use (
            $ipfs,
            $dryRun,
            &$uploaded,
            &$skipped,
            &$failed
        ) {
            foreach ($items as $item) {
                $organizationId = (int) ($item->section?->organization_id ?? 0);
                $expectedPrefix = 'images/org/' . $organizationId . '/menu/';
                $relativePath = ltrim((string) $item->image_path, '/');

                if (
                    $organizationId < 1
                    || !str_starts_with($relativePath, $expectedPrefix)
                    || str_contains($relativePath, '..')
                ) {
                    $this->error('Блюдо #' . $item->id . ': небезопасный или неизвестный путь изображения.');
                    $failed++;
                    continue;
                }

                $fullPath = public_path($relativePath);

                if (!is_file($fullPath) || !is_readable($fullPath)) {
                    $this->error('Блюдо #' . $item->id . ': локальный файл не найден: ' . $relativePath);
                    $failed++;
                    continue;
                }

                if ($dryRun) {
                    $this->line('[DRY RUN] Блюдо #' . $item->id . ': ' . $relativePath);
                    $skipped++;
                    continue;
                }

                try {
                    $cid = $this->uploadAndVerify($fullPath, $ipfs);

                    // После проверки CID сохраняем его и очищаем legacy-путь в БД.
                    $item->image_cid = $cid;
                    $item->image_path = null;
                    $item->save();

                    $this->info('Блюдо #' . $item->id . ' перенесено: ' . $cid);
                    $uploaded++;
                } catch (\\Throwable $exception) {
                    $this->error('Блюдо #' . $item->id . ': ' . $exception->getMessage());
                    $failed++;
                }
            }
        });

        $this->info('Перенос фонов TV-экранов...');

        Organization::query()
            ->when($organizationId, function ($query) use ($organizationId) {
                $query->whereKey($organizationId);
            })
            ->orderBy('id')
            ->chunkById(25, function ($organizations) use (
                $ipfs,
                $dryRun,
                &$uploaded,
                &$skipped,
                &$failed
            ) {
                foreach ($organizations as $organization) {
                    $screenCount = max(1, (int) ($organization->tv_screens_count ?? 3));
                    $screenConfigs = $organization->tv_screens_config ?? [];

                    for ($screen = 1; $screen <= $screenCount; $screen++) {
                        $screenConfig = $screenConfigs[$screen] ?? [];

                        if (!empty($screenConfig['background_cid'])) {
                            continue;
                        }

                        $relativePath = null;

                        foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
                            $candidate = 'images/org/' . $organization->id
                                . '/tv-backgrounds/tv' . $screen . '.' . $extension;

                            if (is_file(public_path($candidate))) {
                                $relativePath = $candidate;
                                break;
                            }
                        }

                        if ($relativePath === null) {
                            continue;
                        }

                        $fullPath = public_path($relativePath);

                        if (!is_readable($fullPath)) {
                            $this->error('Организация #' . $organization->id
                                . ', экран ' . $screen . ': файл недоступен для чтения.');
                            $failed++;
                            continue;
                        }

                        if ($dryRun) {
                            $this->line('[DRY RUN] Организация #' . $organization->id
                                . ', TV ' . $screen . ': ' . $relativePath);
                            $skipped++;
                            continue;
                        }

                        try {
                            $cid = $this->uploadAndVerify($fullPath, $ipfs);

                            $screenConfig['background_cid'] = $cid;
                            $screenConfigs[$screen] = $screenConfig;

                            $organization->tv_screens_config = $screenConfigs;
                            $organization->save();

                            $this->info('Организация #' . $organization->id
                                . ', TV ' . $screen . ' перенесён: ' . $cid);
                            $uploaded++;
                        } catch (\\Throwable $exception) {
                            $this->error('Организация #' . $organization->id
                                . ', TV ' . $screen . ': ' . $exception->getMessage());
                            $failed++;
                        }
                    }
                }
            });

        $this->newLine();
        $this->info('Загружено и проверено: ' . $uploaded);
        $this->info('Пропущено: ' . $skipped);
        $this->info('Ошибок: ' . $failed);
        $this->comment('Локальные исходные файлы команда не удаляет.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function uploadAndVerify(string $path, IPFSService $ipfs): string
    {
        $mimeType = mime_content_type($path) ?: 'application/octet-stream';

        $uploadedFile = new UploadedFile(
            $path,
            basename($path),
            $mimeType,
            null,
            true
        );

        $result = $ipfs->uploadFileAndVerify($uploadedFile);

        return $result['cid'];
    }
}
