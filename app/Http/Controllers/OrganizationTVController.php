<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
        $currentCount = max(1, (int) ($organization->tv_screens_count ?? 3));

        abort_unless($screen >= 1 && $screen <= $currentCount, 404);

        if ($currentCount <= 1) {
            return redirect()
                ->route('organizations.manage', $organization)
                ->with('error', 'Нельзя удалить последний TV-экран.');
        }

        DB::transaction(function () use ($organization, $screen, $currentCount) {
            $lockedOrganization = Organization::query()
                ->whereKey($organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedCount = max(1, (int) ($lockedOrganization->tv_screens_count ?? 3));

            abort_unless($screen <= $lockedCount, 404);

            $organization->menuSections()
                ->where('tv_screen', $screen)
                ->update(['tv_screen' => 1]);

            $organization->menuSections()
                ->where('tv_screen', '>', $screen)
                ->decrement('tv_screen');

            $lockedOrganization->tv_screens_count = max(1, $lockedCount - 1);
            $lockedOrganization->save();
        });

        $directory = public_path('images/org/' . $organization->id . '/tv-backgrounds');

        // Удаляем фон удаляемого экрана.
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
            $path = $directory . '/tv' . $screen . '.' . $extension;
            if (is_file($path)) {
                File::delete($path);
            }
        }

        // Сдвигаем фоны экранов с большими номерами на одну позицию.
        for ($oldScreen = $screen + 1; $oldScreen <= $currentCount; $oldScreen++) {
            foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
                $source = $directory . '/tv' . $oldScreen . '.' . $extension;
                $destination = $directory . '/tv' . ($oldScreen - 1) . '.' . $extension;

                if (is_file($source)) {
                    if (is_file($destination)) {
                        File::delete($destination);
                    }

                    File::move($source, $destination);
                }
            }
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
