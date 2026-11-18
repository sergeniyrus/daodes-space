<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
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

        abort_unless(in_array((string) $screen, ['1', '2', '3'], true), 404);

        $screen = (int) $screen;

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
     * Загрузка фонового изображения отдельного TV-экрана.
     */
    public function uploadBackground(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'screen' => ['required', 'integer', 'in:1,2,3'],
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
            'screen' => ['required', 'integer', 'in:1,2,3'],
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
