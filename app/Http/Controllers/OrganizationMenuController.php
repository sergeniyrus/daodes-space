<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationMenuController extends Controller
{
    /**
     * =========================================================
     * ГЛАВНАЯ СТРАНИЦА МЕНЮ ОРГАНИЗАЦИИ
     * =========================================================
     */
    public function index(Organization $organization): View
    {
        $sections = $organization->menuSections()
            ->with([
                'items' => function ($query) {
                    $query->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('organizations.menu.index', [
            'organization' => $organization,
            'sections' => $sections,
        ]);
    }

    /**
     * ФОРМА СОЗДАНИЯ КАТЕГОРИИ МЕНЮ
     */
    public function createSection(
        Organization $organization
    ): View {
        return view('organizations.menu.sections.create', [
            'organization' => $organization,
        ]);
    }

    /**
     * СОЗДАНИЕ КАТЕГОРИИ МЕНЮ
     */
    public function storeSection(
        Request $request,
        Organization $organization
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $maxSortOrder = $organization->menuSections()
            ->max('sort_order');

        $organization->menuSections()->create([
            'name' => $validated['name'],
            'sort_order' => ($maxSortOrder ?? 0) + 1,
            'is_active' => true,
        ]);

        return redirect()
            ->route(
                'organizations.menu.index',
                $organization
            )
            ->with(
                'success',
                'Категория меню успешно добавлена.'
            );
    }

    /**
     * ФОРМА РЕДАКТИРОВАНИЯ КАТЕГОРИИ
     */
    public function editSection(
        Organization $organization,
        MenuSection $section
    ): View {
        $this->ensureSectionBelongsToOrganization(
            $section,
            $organization
        );

        return view('organizations.menu.sections.edit', [
            'organization' => $organization,
            'section' => $section,
        ]);
    }

    /**
     * ОБНОВЛЕНИЕ КАТЕГОРИИ
     */
    public function updateSection(
        Request $request,
        Organization $organization,
        MenuSection $section
    ): RedirectResponse {
        $this->ensureSectionBelongsToOrganization(
            $section,
            $organization
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        $section->update([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? $section->sort_order,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route(
                'organizations.menu.index',
                $organization
            )
            ->with(
                'success',
                'Категория меню успешно изменена.'
            );
    }

    /**
     * Быстрое изменение порядка раздела без открытия редактора.
     */
    public function updateSectionSort(
        Request $request,
        Organization $organization,
        MenuSection $section
    ): RedirectResponse {
        $this->ensureSectionBelongsToOrganization($section, $organization);

        $validated = $request->validate([
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $section->update([
            'sort_order' => $validated['sort_order'],
        ]);

        return redirect()
            ->route('organizations.menu.index', $organization)
            ->with('success', 'Порядок раздела «' . $section->name . '» изменён.');
    }

    /**
     * Включение / отключение раздела без открытия редактора.
     */
    public function toggleSection(
        Organization $organization,
        MenuSection $section
    ): RedirectResponse {
        $this->ensureSectionBelongsToOrganization($section, $organization);

        $section->update([
            'is_active' => !$section->is_active,
        ]);

        return redirect()
            ->route('organizations.menu.index', $organization)
            ->with(
                'success',
                $section->is_active
                    ? 'Раздел «' . $section->name . '» включён.'
                    : 'Раздел «' . $section->name . '» выключен.'
            );
    }

    /**
     * УДАЛЕНИЕ КАТЕГОРИИ
     *
     * Вместе с категорией удаляются её блюда.
     */
    public function destroySection(
        Organization $organization,
        MenuSection $section
    ): RedirectResponse {
        $this->ensureSectionBelongsToOrganization(
            $section,
            $organization
        );

        $section->items()->delete();

        $section->delete();

        return redirect()
            ->route(
                'organizations.menu.index',
                $organization
            )
            ->with(
                'success',
                'Категория меню удалена.'
            );
    }

    /**
     * ФОРМА СОЗДАНИЯ БЛЮДА
     */
    public function createItem(
        Organization $organization,
        MenuSection $section
    ): View {
        $this->ensureSectionBelongsToOrganization(
            $section,
            $organization
        );

        return view('organizations.menu.items.create', [
            'organization' => $organization,
            'section' => $section,
        ]);
    }

    /**
     * СОЗДАНИЕ БЛЮДА
     */
    public function storeItem(
        Request $request,
        Organization $organization,
        MenuSection $section
    ): RedirectResponse {
        $this->ensureSectionBelongsToOrganization(
            $section,
            $organization
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'weight' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'unit' => [
                'nullable',
                'string',
                'max:50',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'description' => [
                'nullable',
                'string',
            ],
        ]);

        $maxSortOrder = $section->items()
            ->max('sort_order');

        $section->items()->create([
            'name' => $validated['name'],
            'weight' => $validated['weight'] ?? null,
            'unit' => $validated['unit'] ?? null,
            'price' => $validated['price'],
            'description' => $validated['description'] ?? null,
            'sort_order' => ($maxSortOrder ?? 0) + 1,
            'is_active' => true,
        ]);

        return redirect()
            ->route(
                'organizations.menu.index',
                $organization
            )
            ->with(
                'success',
                'Блюдо успешно добавлено.'
            );
    }

    /**
     * ФОРМА РЕДАКТИРОВАНИЯ БЛЮДА
     */
    public function editItem(
    Organization $organization,
    MenuSection $section,
    MenuItem $item
): View {
    $this->ensureSectionBelongsToOrganization(
        $section,
        $organization
    );

    $this->ensureItemBelongsToSection(
        $item,
        $section
    );

    return view('organizations.menu.items.edit', [
        'organization' => $organization,
        'section' => $section,
        'item' => $item,

        // Все категории меню этой организации
        'sections' => $organization->menuSections()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(),
    ]);
}

    /**
     * ОБНОВЛЕНИЕ БЛЮДА
     */
    public function updateItem(
    Request $request,
    Organization $organization,
    MenuSection $section,
    MenuItem $item
): RedirectResponse {
    $this->ensureSectionBelongsToOrganization(
        $section,
        $organization
    );

    $this->ensureItemBelongsToSection(
        $item,
        $section
    );

    $validated = $request->validate([
        'name' => [
            'required',
            'string',
            'max:255',
        ],

        'menu_section_id' => [
            'required',
            'integer',
            'exists:menu_sections,id',
        ],

        'weight' => [
            'nullable',
            'numeric',
            'min:0',
        ],

        'unit' => [
            'nullable',
            'string',
            'max:50',
        ],

        'price' => [
            'required',
            'numeric',
            'min:0',
        ],

        'description' => [
            'nullable',
            'string',
        ],

        'sort_order' => [
            'nullable',
            'integer',
            'min:0',
        ],

        'is_active' => [
            'nullable',
            'boolean',
        ],
    ]);

    // Получаем целевую категорию только из текущей организации.
    // Категорию другой организации выбрать нельзя.
    $targetSection = $organization->menuSections()
        ->whereKey($validated['menu_section_id'])
        ->firstOrFail();

    // Проверяем, меняется ли категория.
    $sectionChanged = (int) $targetSection->id !== (int) $section->id;

    // При переносе ставим блюдо в конец новой категории.
    // Если категория прежняя — сохраняем ручную сортировку.
    if ($sectionChanged) {
        $sortOrder = ($targetSection->items()->max('sort_order') ?? 0) + 1;
    } else {
        $sortOrder = $validated['sort_order'] ?? $item->sort_order;
    }

    $item->update([
        'menu_section_id' => $targetSection->id,
        'name' => $validated['name'],
        'weight' => $validated['weight'] ?? null,
        'unit' => $validated['unit'] ?? null,
        'price' => $validated['price'],
        'description' => $validated['description'] ?? null,
        'sort_order' => $sortOrder,
        'is_active' => $request->boolean('is_active'),
    ]);

    return redirect()
        ->route(
            'organizations.menu.index',
            $organization
        )
        ->with(
            'success',
            $sectionChanged
                ? 'Блюдо успешно перенесено в категорию «' . $targetSection->name . '».'
                : 'Блюдо успешно изменено.'
        );
}

    /**
     * Быстрое изменение порядка блюда без открытия редактора.
     */
    public function updateItemSort(
        Request $request,
        Organization $organization,
        MenuSection $section,
        MenuItem $item
    ): RedirectResponse {
        $this->ensureSectionBelongsToOrganization($section, $organization);
        $this->ensureItemBelongsToSection($item, $section);

        $validated = $request->validate([
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $item->update([
            'sort_order' => $validated['sort_order'],
        ]);

        return redirect()
            ->route('organizations.menu.index', $organization)
            ->with('success', 'Порядок блюда «' . $item->name . '» изменён.');
    }

    /**
     * Включение / отключение блюда без открытия редактора.
     */
    public function toggleItem(
        Organization $organization,
        MenuSection $section,
        MenuItem $item
    ): RedirectResponse {
        $this->ensureSectionBelongsToOrganization($section, $organization);
        $this->ensureItemBelongsToSection($item, $section);

        $item->update([
            'is_active' => !$item->is_active,
        ]);

        return redirect()
            ->route('organizations.menu.index', $organization)
            ->with(
                'success',
                $item->is_active
                    ? 'Блюдо «' . $item->name . '» включено.'
                    : 'Блюдо «' . $item->name . '» выключено.'
            );
    }

    /**
     * УДАЛЕНИЕ БЛЮДА
     */
    public function destroyItem(
        Organization $organization,
        MenuSection $section,
        MenuItem $item
    ): RedirectResponse {
        $this->ensureSectionBelongsToOrganization(
            $section,
            $organization
        );

        $this->ensureItemBelongsToSection(
            $item,
            $section
        );

        $item->delete();

        return redirect()
            ->route(
                'organizations.menu.index',
                $organization
            )
            ->with(
                'success',
                'Блюдо удалено.'
            );
    }

    /**
     * ПРОВЕРКА КАТЕГОРИИ
     */
    private function ensureSectionBelongsToOrganization(
        MenuSection $section,
        Organization $organization
    ): void {
        abort_unless(
            (int) $section->organization_id === (int) $organization->id,
            404
        );
    }

    /**
     * ПРОВЕРКА БЛЮДА
     */
    private function ensureItemBelongsToSection(
        MenuItem $item,
        MenuSection $section
    ): void {
        abort_unless(
            (int) $item->menu_section_id === (int) $section->id,
            404
        );
    }
}