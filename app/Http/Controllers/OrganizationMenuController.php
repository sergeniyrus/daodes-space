<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

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
            'tvScreens' => range(1, max(1, (int) ($organization->tv_screens_count ?? 3))),
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
            'tvScreens' => range(1, max(1, (int) ($organization->tv_screens_count ?? 3))),
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
            'delivery_enabled' => ['nullable', 'boolean'],
            'pickup_enabled' => ['nullable', 'boolean'],
            'tv_screen' => ['required', 'integer', 'min:1', 'max:' . max(1, (int) ($organization->tv_screens_count ?? 3))],
        ]);

        $maxSortOrder = $organization->menuSections()
            ->max('sort_order');

        $organization->menuSections()->create([
            'name' => $validated['name'],
            'sort_order' => ($maxSortOrder ?? 0) + 1,
            'is_active' => true,
            'delivery_enabled' => $request->boolean('delivery_enabled', true),
            'pickup_enabled' => $request->boolean('pickup_enabled', true),
            'tv_screen' => $validated['tv_screen'],
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
            'tvScreens' => range(1, max(1, (int) ($organization->tv_screens_count ?? 3))),
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
            'delivery_enabled' => ['nullable', 'boolean'],
            'pickup_enabled' => ['nullable', 'boolean'],
            'tv_screen' => ['required', 'integer', 'min:1', 'max:' . max(1, (int) ($organization->tv_screens_count ?? 3))],
        ]);

        $section->update([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? $section->sort_order,
            'is_active' => $request->boolean('is_active'),
            'delivery_enabled' => $request->boolean('delivery_enabled'),
            'pickup_enabled' => $request->boolean('pickup_enabled'),
            'tv_screen' => $validated['tv_screen'],
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
     * Быстрое управление настройками раздела прямо из списка меню.
     */
    public function updateSectionQuickSettings(
        Request $request,
        Organization $organization,
        MenuSection $section
    ): RedirectResponse {
        $this->ensureSectionBelongsToOrganization($section, $organization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'tv_screen' => ['required', 'integer', 'min:1', 'max:' . max(1, (int) ($organization->tv_screens_count ?? 3))],
            'delivery_enabled' => ['nullable', 'boolean'],
            'pickup_enabled' => ['nullable', 'boolean'],
        ]);

        $section->update([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'],
            'tv_screen' => $validated['tv_screen'],
            'delivery_enabled' => $request->boolean('delivery_enabled'),
            'pickup_enabled' => $request->boolean('pickup_enabled'),
        ]);

        return redirect()
            ->route('organizations.menu.index', $organization)
            ->withFragment('menu-section-' . $section->id)
            ->with('success', 'Настройки раздела «' . $section->name . '» сохранены.');
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

        $section->items()->get()->each(function (MenuItem $item) use ($organization) {
            if ($item->image_path) {
                $this->deleteMenuItemImage($item->image_path, $organization);
            }
        });

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
        $this->ensureSectionBelongsToOrganization($section, $organization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'is_active' => ['nullable', 'boolean'],
            'delivery_enabled' => ['nullable', 'boolean'],
            'pickup_enabled' => ['nullable', 'boolean'],
        ]);

        $maxSortOrder = $section->items()->max('sort_order');
        $imagePath = null;

        if ($request->hasFile('image')) {
            $directory = public_path('images/org/' . $organization->id . '/menu');
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $filename = Str::uuid()->toString() . '.' . $request->file('image')->extension();
            $request->file('image')->move($directory, $filename);
            $imagePath = 'images/org/' . $organization->id . '/menu/' . $filename;
        }

        $section->items()->create([
            'name' => $validated['name'],
            'weight' => $validated['weight'] ?? null,
            'unit' => $validated['unit'] ?? null,
            'price' => $validated['price'],
            'description' => $validated['description'] ?? null,
            'image_path' => $imagePath,
            'sort_order' => ($maxSortOrder ?? 0) + 1,
            'is_active' => $request->boolean('is_active', true),
            'delivery_enabled' => $request->boolean('delivery_enabled', true),
            'pickup_enabled' => $request->boolean('pickup_enabled', true),
        ]);

        return redirect()
            ->route('organizations.menu.index', $organization)
            ->with('success', 'Блюдо успешно добавлено.');
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
        $this->ensureSectionBelongsToOrganization($section, $organization);
        $this->ensureItemBelongsToSection($item, $section);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'menu_section_id' => ['required', 'integer', 'exists:menu_sections,id'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'delivery_enabled' => ['nullable', 'boolean'],
            'pickup_enabled' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        $targetSection = $organization->menuSections()
            ->whereKey($validated['menu_section_id'])
            ->firstOrFail();

        $sectionChanged = (int) $targetSection->id !== (int) $section->id;
        $sortOrder = $sectionChanged
            ? ($targetSection->items()->max('sort_order') ?? 0) + 1
            : ($validated['sort_order'] ?? $item->sort_order);

        $imagePath = $item->image_path;
        $oldImagePath = $item->image_path;

        if ($request->hasFile('image')) {
            $directory = public_path('images/org/' . $organization->id . '/menu');
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $filename = Str::uuid()->toString() . '.' . $request->file('image')->extension();
            $request->file('image')->move($directory, $filename);
            $imagePath = 'images/org/' . $organization->id . '/menu/' . $filename;
        } elseif ($request->boolean('remove_image')) {
            $imagePath = null;
        }

        $item->update([
            'menu_section_id' => $targetSection->id,
            'name' => $validated['name'],
            'weight' => $validated['weight'] ?? null,
            'unit' => $validated['unit'] ?? null,
            'price' => $validated['price'],
            'description' => $validated['description'] ?? null,
            'image_path' => $imagePath,
            'sort_order' => $sortOrder,
            'is_active' => $request->boolean('is_active'),
            'delivery_enabled' => $request->boolean('delivery_enabled'),
            'pickup_enabled' => $request->boolean('pickup_enabled'),
        ]);

        if ($oldImagePath && $oldImagePath !== $imagePath) {
            $this->deleteMenuItemImage($oldImagePath, $organization);
        }

        return redirect()
            ->route('organizations.menu.index', $organization)
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

        $imagePath = $item->image_path;

        $item->delete();

        if ($imagePath) {
            $this->deleteMenuItemImage($imagePath, $organization);
        }

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

    private function deleteMenuItemImage(string $imagePath, Organization $organization): void
    {
        $expectedPrefix = 'images/org/' . $organization->id . '/menu/';
        if (!str_starts_with($imagePath, $expectedPrefix)) {
            return;
        }

        $fullPath = public_path($imagePath);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
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