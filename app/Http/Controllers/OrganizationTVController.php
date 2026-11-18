<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
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

        $backgroundPath = 'images/org/' . $organization->id
            . '/tv-backgrounds/tv' . $screen . '.png';

        $backgroundUrl = is_file(public_path($backgroundPath))
            ? asset($backgroundPath)
            : null;

        return view('tv.organization', [
            'organization' => $organization,
            'screen' => $screen,
            'sections' => $sections,
            'backgroundUrl' => $backgroundUrl,
        ]);
    }
}
