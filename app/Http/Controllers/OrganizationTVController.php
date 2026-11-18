<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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

        return view('tv.organization', [
            'organization' => $organization,
            'screen' => $screen,
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

            $lockedOrganization->tv_screens_count = $currentCount + 1;
            $lockedOrganization->save();

            return $currentCount + 1;
        });

        return redirect()
            ->route('organizations.manage', $organization)
            ->with('success', 'TV-экран ' . $newCount . ' создан.');
    }

    /**
     * Удаление TV-экрана с перенумерацией последующих экранов.
     */
    public function deleteScreen(Request $request, Organization $organization, int $screen)
    {
        $directory = public_path('images/org/' . $organization->id . '/tv-backgrounds');
        $backupDirectory = $directory . '/.tv-delete-' . (string) Str::uuid();
        $deletedScreen = false;

        try {
            $deletedScreen = DB::transaction(function () use (
                $organization,
                $screen,
                $directory,
                $backupDirectory
            ) {
                // Количество экранов читаем только после блокировки строки организации.
                $lockedOrganization = Organization::query()
                    ->whereKey($organization->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedCount = max(1, (int) ($lockedOrganization->tv_screens_count ?? 3));

                abort_unless($screen >= 1 && $screen <= $lockedCount, 404);

                if ($lockedCount <= 1) {
                    return false;
                }

                File::ensureDirectoryExists($directory);
                File::ensureDirectoryExists($backupDirectory);

                // Сохраняем все затрагиваемые фоны, чтобы восстановить их при ошибке транзакции.
                foreach (range($screen, $lockedCount) as $oldScreen) {
                    foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
                        $source = $directory . '/tv' . $oldScreen . '.' . $extension;

                        if (is_file($source)) {
                            File::move($source, $backupDirectory . '/tv' . $oldScreen . '.' . $extension);
                        }
                    }
                }

                // Удаляемый экран пропускаем, последующие фоны сдвигаем на одну позицию.
                for ($oldScreen = $screen + 1; $oldScreen <= $lockedCount; $oldScreen++) {
                    foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
                        $source = $backupDirectory . '/tv' . $oldScreen . '.' . $extension;

                        if (is_file($source)) {
                            File::copy($source, $directory . '/tv' . ($oldScreen - 1) . '.' . $extension);
                        }
                    }
                }

                $lockedOrganization->menuSections()
                    ->where('tv_screen', $screen)
                    ->update(['tv_screen' => 1]);

                $lockedOrganization->menuSections()
                    ->where('tv_screen', '>', $screen)
                    ->decrement('tv_screen');

                $lockedOrganization->tv_screens_count = $lockedCount - 1;
                $lockedOrganization->save();

                return true;
            });
        } catch (\\Throwable $exception) {
            // Компенсируем файловые операции, если БД не смогла зафиксировать изменения.
            if (File::isDirectory($backupDirectory)) {
                foreach (range($screen, 20) as $oldScreen) {
                    foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
                        $backup = $backupDirectory . '/tv' . $oldScreen . '.' . $extension;

                        if (is_file($backup)) {
                            $original = $directory . '/tv' . $oldScreen . '.' . $extension;

                            if (is_file($original)) {
                                File::delete($original);
                            }

                            File::move($backup, $original);
                        }
                    }
                }

                File::deleteDirectory($backupDirectory);
            }

            throw $exception;
        }

        if (!$deletedScreen) {
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
            'screen' => ['required', 'integer', 'min:1', 'max:' . max(1, (int) ($organization->tv_screens_count ?? 3))],
            'background' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:10240'],
        ], [
            'background.required' => 'Выберите изображение для загрузки.',
            'background.image' => 'Файл должен быть изображением.',
            'background.mimes' => 'Допустимые форматы: PNG, JPG, JPEG и WEBP.',
            'background.max' => 'Размер изображения не должен превышать 10 МБ.',
        ]);

        $screen = (int) $validated['screen'];
        $directory = public_path('images/org/' . $organization->id . '/tv-backgrounds');

        File::ensureDirectoryExists($directory);

        foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
            $oldPath = $directory . '/tv' . $screen . '.' . $extension;

            if (is_file($oldPath)) {
                File::delete($oldPath);
            }
        }

        $extension = $request->file('background')->extension();
        $request->file('background')->move($directory, 'tv' . $screen . '.' . $extension);

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
            'screen' => ['required', 'integer', 'min:1', 'max:' . max(1, (int) ($organization->tv_screens_count ?? 3))],
        ]);

        $screen = (int) $validated['screen'];
        $directory = public_path('images/org/' . $organization->id . '/tv-backgrounds');

        foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
            $path = $directory . '/tv' . $screen . '.' . $extension;

            if (is_file($path)) {
                File::delete($path);
            }
        }

        return redirect()
            ->route('organizations.manage', $organization)
            ->with('success', 'Фон TV-экрана ' . $screen . ' удалён.');
    }

}
