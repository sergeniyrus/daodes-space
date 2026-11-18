<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Illuminate\View\View;

class OrganizationTVController extends Controller
{
    /**
     * Публичный экран меню конкретной организации.
     *
     * Поддерживает адреса:
     * /organization/2/tv?1
     * /organization/2/tv?2
     * /organization/2/tv?3
     * а также /organization/2/tv?screen=1
     */
    public function show(Request $request, Organization $organization): View
    {
        abort_unless($organization->isActive(), 404);

        $screen = $request->query('screen');

        if ($screen === null) {
            foreach ([1, 2, 3] as $candidate) {
                if ($request->query->has((string) $candidate)) {
                    $screen = $candidate;
                    break;
                }
            }
        }

        abort_unless(is_numeric($screen), 404);

        $screen = (int) $screen;
        abort_unless($screen >= 1 && $screen <= max(1, (int) ($organization->tv_screens_count ?? 3)), 404);

        $sections = $organization->menuSections()
            ->where('is_active', true)
            ->where('tv_screen', $screen)
            ->with([
                'items' => function ($query) {
                    $query->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $backgroundUrl = null;

        foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
            $backgroundPath = 'images/org/' . $organization->id
                . '/tv-backgrounds/tv' . $screen . '.' . $extension;

            if (is_file(public_path($backgroundPath))) {
                $backgroundUrl = asset($backgroundPath);
                break;
            }
        }

        $screenCount = max(1, (int) ($organization->tv_screens_count ?? 3));
        $screenConfigs = $organization->tv_screens_config ?? [];
        $screenConfig = $screenConfigs[$screen] ?? [];
        $backgroundFit = in_array($screenConfig['background_fit'] ?? 'cover', ['cover', 'contain', 'stretch'], true)
            ? $screenConfig['background_fit']
            : 'cover';

        return view('tv.organization', [
            'organization' => $organization,
            'screen' => $screen,
            'screenCount' => $screenCount,
            'screenName' => trim((string) ($screenConfig['name'] ?? '')) ?: 'TV-экран ' . $screen,
            'backgroundFit' => $backgroundFit,
            'rotationSeconds' => max(0, min(3600, (int) ($screenConfig['rotation_seconds'] ?? 0))),
            'sections' => $sections,
            'backgroundUrl' => $backgroundUrl,
        ]);
    }


    /**
     * Создание дополнительного TV-экрана.
     */
    public function createScreen(Request $request, Organization $organization)
    {
        $newCount = DB::transaction(function () use ($organization) {
            $lockedOrganization = Organization::query()
                ->whereKey($organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentCount = max(1, (int) ($lockedOrganization->tv_screens_count ?? 3));

            if ($currentCount >= 20) {
                abort(422, 'Можно создать не более 20 TV-экранов.');
            }

            $newScreen = $currentCount + 1;
            $screenConfigs = $lockedOrganization->tv_screens_config ?? [];
            $screenConfigs[$newScreen] = [
                'name' => 'TV-экран ' . $newScreen,
                'background_fit' => 'cover',
                'rotation_seconds' => 0,
            ];

            $lockedOrganization->tv_screens_count = $newScreen;
            $lockedOrganization->tv_screens_config = $screenConfigs;
            $lockedOrganization->save();

            return $currentCount + 1;
        });

        return redirect()
            ->route('organizations.manage', $organization)
            ->with('success', 'TV-экран ' . $newCount . ' создан.');
    }

    /**
     * Сохранение имени, режима фона и интервала переключения TV-экрана.
     */
    public function updateScreenSettings(Request $request, Organization $organization, int $screen)
    {
        $validated = $request->validate([
            'screen_name' => ['required', 'string', 'max:60'],
            'background_fit' => ['required', 'in:cover,contain,stretch'],
            'rotation_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
        ]);

        DB::transaction(function () use ($organization, $screen, $validated) {
            $lockedOrganization = Organization::query()
                ->whereKey($organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            $screenCount = max(1, (int) ($lockedOrganization->tv_screens_count ?? 3));
            abort_unless($screen >= 1 && $screen <= $screenCount, 404);

            $screenConfigs = $lockedOrganization->tv_screens_config ?? [];
            $screenConfigs[$screen] = [
                'name' => trim($validated['screen_name']),
                'background_fit' => $validated['background_fit'],
                'rotation_seconds' => (int) $validated['rotation_seconds'],
            ];

            $lockedOrganization->tv_screens_config = $screenConfigs;
            $lockedOrganization->save();
        });

        return redirect()
            ->route('organizations.manage', $organization)
            ->with('success', 'Настройки TV-экрана ' . $screen . ' сохранены.');
    }

    /**
     * Удаление TV-экрана с перенумерацией последующих экранов.
     */
    public function deleteScreen(Request $request, Organization $organization, int $screen)
    {
        $validated = $request->validate([
            'expected_screens_count' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $expectedCount = (int) $validated['expected_screens_count'];
        $directory = public_path('images/org/' . $organization->id . '/tv-backgrounds');
        $backupDirectory = $directory . '/.tv-delete-' . (string) Str::uuid();
        $deleteResult = 'stale';
        $originalsCleared = false;

        try {
            $deleteResult = DB::transaction(function () use (
                $organization,
                $screen,
                $expectedCount,
                $directory,
                $backupDirectory,
                &$originalsCleared
            ) {
                // Количество экранов читаем только после блокировки строки организации.
                $lockedOrganization = Organization::query()
                    ->whereKey($organization->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedCount = max(1, (int) ($lockedOrganization->tv_screens_count ?? 3));

                abort_unless($screen >= 1 && $screen <= $lockedCount, 404);

                // Повторный запрос со старой формой не должен удалить уже перенумерованный экран.
                if ($lockedCount !== $expectedCount) {
                    return 'stale';
                }

                if ($lockedCount <= 1) {
                    return 'last';
                }

                File::ensureDirectoryExists($directory);
                File::ensureDirectoryExists($backupDirectory);

                // Сохраняем все затрагиваемые фоны, чтобы восстановить их при ошибке транзакции.
                foreach (range($screen, $lockedCount) as $oldScreen) {
                    foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
                        $source = $directory . '/tv' . $oldScreen . '.' . $extension;

                        if (is_file($source)) {
                            if (!File::copy($source, $backupDirectory . '/tv' . $oldScreen . '.' . $extension)) {
                                throw new RuntimeException('Не удалось создать резервную копию фона TV-экрана.');
                            }
                        }
                    }
                }

                // Оригиналы удаляем только после успешного резервного копирования всех файлов.
                $originalsCleared = true;

                foreach (range($screen, $lockedCount) as $oldScreen) {
                    foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
                        $original = $directory . '/tv' . $oldScreen . '.' . $extension;

                        if (is_file($original)) {
                            File::delete($original);
                        }
                    }
                }

                // Удаляемый экран пропускаем, последующие фоны сдвигаем на одну позицию.
                for ($oldScreen = $screen + 1; $oldScreen <= $lockedCount; $oldScreen++) {
                    foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
                        $source = $backupDirectory . '/tv' . $oldScreen . '.' . $extension;

                        if (is_file($source)) {
                            if (!File::copy($source, $directory . '/tv' . ($oldScreen - 1) . '.' . $extension)) {
                                throw new RuntimeException('Не удалось перенести фон TV-экрана.');
                            }
                        }
                    }
                }

                $lockedOrganization->menuSections()
                    ->where('tv_screen', $screen)
                    ->update(['tv_screen' => 1]);

                $lockedOrganization->menuSections()
                    ->where('tv_screen', '>', $screen)
                    ->decrement('tv_screen');

                $screenConfigs = $lockedOrganization->tv_screens_config ?? [];
                unset($screenConfigs[$screen]);

                $renumberedConfigs = [];

                for ($oldScreen = 1; $oldScreen <= $lockedCount; $oldScreen++) {
                    if ($oldScreen === $screen) {
                        continue;
                    }

                    $newScreen = $oldScreen > $screen ? $oldScreen - 1 : $oldScreen;
                    $screenConfig = $screenConfigs[$oldScreen] ?? [
                        'name' => 'TV-экран ' . $oldScreen,
                        'background_fit' => 'cover',
                        'rotation_seconds' => 0,
                    ];

                    if (($screenConfig['name'] ?? null) === 'TV-экран ' . $oldScreen) {
                        $screenConfig['name'] = 'TV-экран ' . $newScreen;
                    }

                    $renumberedConfigs[$newScreen] = $screenConfig;
                }

                $lockedOrganization->tv_screens_count = $lockedCount - 1;
                $lockedOrganization->tv_screens_config = $renumberedConfigs;
                $lockedOrganization->save();

                return 'deleted';
            });
        } catch (\Throwable $exception) {
            // Компенсируем файловые операции, если БД не смогла зафиксировать изменения.
            if (File::isDirectory($backupDirectory)) {
                if ($originalsCleared) {
                    // Удаляем частично созданные файлы и возвращаем исходные.
                    foreach (range($screen, 20) as $oldScreen) {
                        foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
                            $current = $directory . '/tv' . $oldScreen . '.' . $extension;

                            if (is_file($current)) {
                                File::delete($current);
                            }
                        }
                    }

                    foreach (range($screen, 20) as $oldScreen) {
                        foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
                            $backup = $backupDirectory . '/tv' . $oldScreen . '.' . $extension;

                            if (is_file($backup)) {
                                File::move($backup, $directory . '/tv' . $oldScreen . '.' . $extension);
                            }
                        }
                    }
                }

                File::deleteDirectory($backupDirectory);
            }

            throw $exception;
        }

        if ($deleteResult === 'stale') {
            return redirect()
                ->route('organizations.manage', $organization)
                ->with('error', 'Список TV-экранов уже изменился. Обновите страницу и повторите действие.');
        }

        if ($deleteResult === 'last') {
            return redirect()
                ->route('organizations.manage', $organization)
                ->with('error', 'Нельзя удалить последний TV-экран.');
        }

        if (File::isDirectory($backupDirectory)) {
            File::deleteDirectory($backupDirectory);
        }

        return redirect()
            ->route('organizations.manage', $organization)
            ->with('success', 'TV-экран ' . $screen . ' удалён. Последующие экраны перенумерованы.');
    }


    /**
     * Загрузка фонового изображения отдельного TV-экрана.
     */
    public function uploadBackground(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'screen' => ['required', 'integer', 'min:1', 'max:20'],
            'background' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:10240'],
        ], [
            'background.required' => 'Выберите изображение для загрузки.',
            'background.image' => 'Файл должен быть изображением.',
            'background.mimes' => 'Допустимые форматы: PNG, JPG, JPEG и WEBP.',
            'background.max' => 'Размер изображения не должен превышать 10 МБ.',
        ]);

        $screen = (int) $validated['screen'];
        $directory = public_path('images/org/' . $organization->id . '/tv-backgrounds');
        $backupDirectory = $directory . '/.tv-upload-' . (string) Str::uuid();
        $extension = $request->file('background')->extension();
        $temporaryName = '.tv-upload-' . (string) Str::uuid() . '.tmp';
        $temporaryPath = $directory . '/' . $temporaryName;
        $originalsCleared = false;

        try {
            DB::transaction(function () use (
                $organization,
                $screen,
                $directory,
                $backupDirectory,
                $extension,
                $temporaryName,
                $request,
                &$originalsCleared
            ) {
                $lockedOrganization = Organization::query()
                    ->whereKey($organization->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $screenCount = max(1, (int) ($lockedOrganization->tv_screens_count ?? 3));
                abort_unless($screen <= $screenCount, 404);

                File::ensureDirectoryExists($directory);
                File::ensureDirectoryExists($backupDirectory);

                // Сначала сохраняем новый файл под временным именем.
                $request->file('background')->move($directory, $temporaryName);

                if (!is_file($directory . '/' . $temporaryName)) {
                    throw new RuntimeException('Не удалось временно сохранить загруженное изображение.');
                }

                // Резервируем существующий фон до замены.
                foreach (['png', 'jpg', 'jpeg', 'webp'] as $oldExtension) {
                    $oldPath = $directory . '/tv' . $screen . '.' . $oldExtension;

                    if (is_file($oldPath)) {
                        if (!File::copy(
                            $oldPath,
                            $backupDirectory . '/tv' . $screen . '.' . $oldExtension
                        )) {
                            throw new RuntimeException('Не удалось создать резервную копию старого фона.');
                        }
                    }
                }

                $originalsCleared = true;

                foreach (['png', 'jpg', 'jpeg', 'webp'] as $oldExtension) {
                    $oldPath = $directory . '/tv' . $screen . '.' . $oldExtension;

                    if (is_file($oldPath)) {
                        File::delete($oldPath);
                    }
                }

                $temporaryPath = $directory . '/' . $temporaryName;
                $finalPath = $directory . '/tv' . $screen . '.' . $extension;

                if (!File::move($temporaryPath, $finalPath)) {
                    throw new RuntimeException('Не удалось сохранить новый фон TV-экрана.');
                }
            });
        } catch (\Throwable $exception) {
            if ($originalsCleared) {
                foreach (['png', 'jpg', 'jpeg', 'webp'] as $oldExtension) {
                    $currentPath = $directory . '/tv' . $screen . '.' . $oldExtension;

                    if (is_file($currentPath)) {
                        File::delete($currentPath);
                    }
                }

                foreach (['png', 'jpg', 'jpeg', 'webp'] as $oldExtension) {
                    $backupPath = $backupDirectory . '/tv' . $screen . '.' . $oldExtension;

                    if (is_file($backupPath)) {
                        File::move($backupPath, $directory . '/tv' . $screen . '.' . $oldExtension);
                    }
                }
            }

            if (is_file($temporaryPath)) {
                File::delete($temporaryPath);
            }

            if (File::isDirectory($backupDirectory)) {
                File::deleteDirectory($backupDirectory);
            }

            throw $exception;
        }

        if (is_file($temporaryPath)) {
            File::delete($temporaryPath);
        }

        if (File::isDirectory($backupDirectory)) {
            File::deleteDirectory($backupDirectory);
        }

        return redirect()
            ->route('organizations.manage', $organization)
            ->with('success', 'Фон TV-экрана ' . $screen . ' успешно обновлён.');
    }

    /**
     * Удаление фонового изображения TV-экрана.
     */
    public function deleteBackground(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'screen' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $screen = (int) $validated['screen'];
        $directory = public_path('images/org/' . $organization->id . '/tv-backgrounds');

        DB::transaction(function () use ($organization, $screen, $directory) {
            $lockedOrganization = Organization::query()
                ->whereKey($organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            $screenCount = max(1, (int) ($lockedOrganization->tv_screens_count ?? 3));
            abort_unless($screen <= $screenCount, 404);

            foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
                $path = $directory . '/tv' . $screen . '.' . $extension;

                if (is_file($path)) {
                    File::delete($path);
                }
            }
        });

        return redirect()
            ->route('organizations.manage', $organization)
            ->with('success', 'Фон TV-экрана ' . $screen . ' удалён.');
    }

}
