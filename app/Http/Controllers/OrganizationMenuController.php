<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Organization;
use App\Services\IPFSService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'tvScreens' => range(1, max(1, (int) ($organization->tv_screens_count ?? 3))),
            'canManageTv' => !Auth::guard('employee')->check(),
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
            'canManageTv' => !Auth::guard('employee')->check(),
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
            'dine_in_enabled' => ['nullable', 'boolean'],
            'tv_screen' => Auth::guard('employee')->check()
                ? ['nullable', 'integer', 'min:1', 'max:' . max(1, (int) ($organization->tv_screens_count ?? 3))]
                : ['required', 'integer', 'min:1', 'max:' . max(1, (int) ($organization->tv_screens_count ?? 3))],
        ]);

        $maxSortOrder = $organization->menuSections()
            ->max('sort_order');

        $organization->menuSections()->create([
            'name' => $validated['name'],
            'sort_order' => ($maxSortOrder ?? 0) + 1,
            'is_active' => true,
            'delivery_enabled' => $request->boolean('delivery_enabled', true),
            'pickup_enabled' => $request->boolean('pickup_enabled', true),
            'dine_in_enabled' => $request->boolean('dine_in_enabled', true),
            'tv_screen' => Auth::guard('employee')->check() ? 1 : $validated['tv_screen'],
        ]);

        return redirect()
            ->route(
                $this->menuIndexRoute(),
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
            'canManageTv' => !Auth::guard('employee')->check(),
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
            'dine_in_enabled' => ['nullable', 'boolean'],
            'tv_screen' => Auth::guard('employee')->check()
                ? ['nullable', 'integer', 'min:1', 'max:' . max(1, (int) ($organization->tv_screens_count ?? 3))]
                : ['required', 'integer', 'min:1', 'max:' . max(1, (int) ($organization->tv_screens_count ?? 3))],
        ]);

        $section->update([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? $section->sort_order,
            'is_active' => $request->boolean('is_active'),
            'delivery_enabled' => $request->boolean('delivery_enabled'),
            'pickup_enabled' => $request->boolean('pickup_enabled'),
            'dine_in_enabled' => $request->boolean('dine_in_enabled'),
            'tv_screen' => Auth::guard('employee')->check() ? $section->tv_screen : $validated['tv_screen'],
        ]);

        return redirect()
            ->route(
                $this->menuIndexRoute(),
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
            'tv_screen' => Auth::guard('employee')->check()
                ? ['nullable', 'integer', 'min:1', 'max:' . max(1, (int) ($organization->tv_screens_count ?? 3))]
                : ['required', 'integer', 'min:1', 'max:' . max(1, (int) ($organization->tv_screens_count ?? 3))],
            'delivery_enabled' => ['nullable', 'boolean'],
            'pickup_enabled' => ['nullable', 'boolean'],
            'dine_in_enabled' => ['nullable', 'boolean'],
        ]);

        $beforeValues = $section->only([
            'name',
            'sort_order',
            'tv_screen',
            'delivery_enabled',
            'pickup_enabled',
            'dine_in_enabled',
        ]);

        $section->update([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'],
            'tv_screen' => Auth::guard('employee')->check() ? $section->tv_screen : $validated['tv_screen'],
            'delivery_enabled' => $request->boolean('delivery_enabled'),
            'pickup_enabled' => $request->boolean('pickup_enabled'),
            'dine_in_enabled' => $request->boolean('dine_in_enabled'),
        ]);

        app(\App\Services\OrganizationActivityLogger::class)->record(
            $organization,
            'menu_section.settings_updated',
            'Изменены настройки раздела «' . $section->name . '»',
            $beforeValues,
            $section->fresh()->only(['name', 'sort_order', 'tv_screen', 'delivery_enabled', 'pickup_enabled', 'dine_in_enabled']),
            'menu_section',
            $section->id
        );

        return redirect()
            ->route(
                $this->menuIndexRoute(), $organization)
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

        $beforeValues = ['sort_order' => $section->sort_order];

        $section->update([
            'sort_order' => $validated['sort_order'],
        ]);

        app(\App\Services\OrganizationActivityLogger::class)->record(
            $organization,
            'menu_section.sorted',
            'Изменён порядок раздела «' . $section->name . '»',
            $beforeValues,
            ['sort_order' => $section->sort_order],
            'menu_section',
            $section->id
        );

        return redirect()
            ->route(
                $this->menuIndexRoute(), $organization)
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

        $beforeActive = (bool) $section->is_active;

        $section->update([
            'is_active' => !$section->is_active,
        ]);

        app(\App\Services\OrganizationActivityLogger::class)->record(
            $organization,
            'menu_section.toggled',
            ($section->is_active ? 'Включён' : 'Выключен') . ' раздел «' . $section->name . '»',
            ['is_active' => $beforeActive],
            ['is_active' => (bool) $section->is_active],
            'menu_section',
            $section->id
        );

        return redirect()
            ->route(
                $this->menuIndexRoute(), $organization)
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

        $deletedSection = [
            'name' => $section->name,
            'items_count' => $section->items()->count(),
            'is_active' => (bool) $section->is_active,
            'tv_screen' => (int) $section->tv_screen,
        ];

        $section->items()->delete();

        $section->delete();

        app(\App\Services\OrganizationActivityLogger::class)->record(
            $organization,
            'menu_section.deleted',
            'Удалён раздел меню «' . $deletedSection['name'] . '»',
            $deletedSection,
            [],
            'menu_section',
            $section->id
        );

        return redirect()
            ->route(
                $this->menuIndexRoute(),
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
            'dine_in_enabled' => ['nullable', 'boolean'],
        ]);

        $maxSortOrder = $section->items()->max('sort_order');
        $imageCid = null;

        if ($request->hasFile('image')) {
            $uploadResult = app(IPFSService::class)->uploadFileAndVerify(
                $request->file('image')
            );

            $imageCid = $uploadResult['cid'];
        }

        $item = $section->items()->create([
            'name' => $validated['name'],
            'weight' => $validated['weight'] ?? null,
            'unit' => $validated['unit'] ?? null,
            'price' => $validated['price'],
            'description' => $validated['description'] ?? null,
            'image_path' => null,
            'image_cid' => $imageCid,
            'sort_order' => ($maxSortOrder ?? 0) + 1,
            'is_active' => $request->boolean('is_active', true),
            'delivery_enabled' => $request->boolean('delivery_enabled', true),
            'pickup_enabled' => $request->boolean('pickup_enabled', true),
            'dine_in_enabled' => $request->boolean('dine_in_enabled', true),
        ]);

        app(\App\Services\OrganizationActivityLogger::class)->record(
            $organization,
            'menu_item.created',
            'Добавлено блюдо «' . $item->name . '»',
            [],
            $item->only(['name', 'weight', 'unit', 'price', 'description', 'is_active', 'delivery_enabled', 'pickup_enabled', 'dine_in_enabled']),
            'menu_item',
            $item->id
        );

        return redirect()
            ->route(
                $this->menuIndexRoute(), $organization)
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
            'dine_in_enabled' => ['nullable', 'boolean'],
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
        $imageCid = $item->image_cid;
        $oldImagePath = $item->image_path;

        if ($request->hasFile('image')) {
            $uploadResult = app(IPFSService::class)->uploadFileAndVerify(
                $request->file('image')
            );

            $imageCid = $uploadResult['cid'];
            $imagePath = null;
        } elseif ($request->boolean('remove_image')) {
            $imagePath = null;
            $imageCid = null;
        }

        $beforeValues = $item->only([
            'menu_section_id',
            'name',
            'weight',
            'unit',
            'price',
            'description',
            'is_active',
            'delivery_enabled',
            'pickup_enabled',
            'dine_in_enabled',
        ]);

        $item->update([
            'menu_section_id' => $targetSection->id,
            'name' => $validated['name'],
            'weight' => $validated['weight'] ?? null,
            'unit' => $validated['unit'] ?? null,
            'price' => $validated['price'],
            'description' => $validated['description'] ?? null,
            'image_path' => $imagePath,
            'image_cid' => $imageCid,
            'sort_order' => $sortOrder,
            'is_active' => $request->boolean('is_active'),
            'delivery_enabled' => $request->boolean('delivery_enabled'),
            'pickup_enabled' => $request->boolean('pickup_enabled'),
            'dine_in_enabled' => $request->boolean('dine_in_enabled'),
        ]);

        if ($oldImagePath && $oldImagePath !== $imagePath) {
            $this->deleteMenuItemImage($oldImagePath, $organization);
        }

        app(\App\Services\OrganizationActivityLogger::class)->record(
            $organization,
            'menu_item.updated',
            'Изменено блюдо «' . $item->name . '»',
            $beforeValues,
            $item->fresh()->only([
                'menu_section_id',
                'name',
                'weight',
                'unit',
                'price',
                'description',
                'is_active',
                'delivery_enabled',
                'pickup_enabled',
            'dine_in_enabled',
            ]),
            'menu_item',
            $item->id
        );

        return redirect()
            ->route(
                $this->menuIndexRoute(), $organization)
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

        $beforeSortOrder = (int) $item->sort_order;

        $item->update([
            'sort_order' => $validated['sort_order'],
        ]);

        app(\App\Services\OrganizationActivityLogger::class)->record(
            $organization,
            'menu_item.sorted',
            'Изменён порядок блюда «' . $item->name . '»',
            ['sort_order' => $beforeSortOrder],
            ['sort_order' => (int) $item->sort_order],
            'menu_item',
            $item->id
        );

        return redirect()
            ->route(
                $this->menuIndexRoute(), $organization)
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

        $beforeActive = (bool) $item->is_active;

        $item->update([
            'is_active' => !$item->is_active,
        ]);

        app(\App\Services\OrganizationActivityLogger::class)->record(
            $organization,
            'menu_item.toggled',
            ($item->is_active ? 'Включено' : 'Выключено') . ' блюдо «' . $item->name . '»',
            ['is_active' => $beforeActive],
            ['is_active' => (bool) $item->is_active],
            'menu_item',
            $item->id
        );

        return redirect()
            ->route(
                $this->menuIndexRoute(), $organization)
            ->with(
                'success',
                $item->is_active
                    ? 'Блюдо «' . $item->name . '» включено.'
                    : 'Блюдо «' . $item->name . '» выключено.'
            );
    }

    /**
     * Отдельное управление стоп-листом без изменения активности блюда.
     * Синхронизирует точные копии позиции внутри одной организации.
     */
    public function toggleItemAvailability(Request $request, Organization $organization, MenuSection $section, MenuItem $item): RedirectResponse
    {
        $this->ensureSectionBelongsToOrganization($section, $organization);
        $this->ensureItemBelongsToSection($item, $section);

        $validated = $request->validate([
            'is_available' => ['required', 'boolean'],
            'unavailable_reason' => ['nullable', 'string', 'max:255'],
        ]);
        $isAvailable = (bool) $validated['is_available'];
        $reason = $isAvailable ? null : (trim((string) ($validated['unavailable_reason'] ?? '')) ?: 'Временно отсутствует');

        $result = DB::transaction(function () use ($organization, $item, $isAvailable, $reason) {
            $locked = MenuItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $copies = MenuItem::query()
                ->where('name', $locked->name)
                ->where(fn ($q) => $locked->weight === null ? $q->whereNull('weight') : $q->where('weight', $locked->weight))
                ->where(fn ($q) => $locked->unit === null ? $q->whereNull('unit') : $q->where('unit', $locked->unit))
                // Цена может отличаться и синхронизируется отдельной командой.
                // Остальные характеристики должны совпадать, чтобы не затронуть
                // самостоятельное блюдо с таким же названием и весом.
                ->where('description', $locked->description)
                ->where('image_path', $locked->image_path)
                ->where('image_cid', $locked->image_cid)
                ->where('delivery_enabled', (bool) $locked->delivery_enabled)
                ->where('pickup_enabled', (bool) $locked->pickup_enabled)
                ->where('dine_in_enabled', (bool) $locked->dine_in_enabled)
                ->whereHas('section', fn ($q) => $q->where('organization_id', $organization->id))
                ->orderBy('id')->lockForUpdate()->get();
            $before = $copies->mapWithKeys(fn (MenuItem $copy) => [$copy->id => [
                'is_available' => (bool) $copy->is_available,
                'unavailable_reason' => $copy->unavailable_reason,
            ]])->all();
            MenuItem::query()->whereIn('id', $copies->modelKeys())->update([
                'is_available' => $isAvailable,
                'unavailable_reason' => $reason,
                'updated_at' => now(),
            ]);
            return ['count' => $copies->count(), 'before' => $before];
        });

        app(\App\Services\OrganizationActivityLogger::class)->record(
            $organization,
            $isAvailable ? 'menu_item.available' : 'menu_item.unavailable',
            ($isAvailable ? 'Возвращено в меню' : 'Добавлено в стоп-лист') . ' блюдо «' . $item->name . '»',
            $result['before'],
            ['is_available' => $isAvailable, 'unavailable_reason' => $reason, 'synchronized_items' => $result['count']],
            'menu_item',
            $item->id
        );

        return redirect()->route($this->menuIndexRoute(), $organization)
            ->with('success', $isAvailable ? 'Блюдо «' . $item->name . '» возвращено в меню.' : 'Блюдо «' . $item->name . '» добавлено в стоп-лист.');
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
        $deletedValues = $item->only([
            'menu_section_id',
            'name',
            'weight',
            'unit',
            'price',
            'description',
            'is_active',
            'delivery_enabled',
            'pickup_enabled',
            'dine_in_enabled',
        ]);

        $item->delete();

        app(\App\Services\OrganizationActivityLogger::class)->record(
            $organization,
            'menu_item.deleted',
            'Удалено блюдо «' . $deletedValues['name'] . '»',
            $deletedValues,
            [],
            'menu_item',
            $item->id
        );

        if ($imagePath) {
            $this->deleteMenuItemImage($imagePath, $organization);
        }

        return redirect()
            ->route(
                $this->menuIndexRoute(),
                $organization
            )
            ->with(
                'success',
                'Блюдо удалено.'
            );
    }

    private function menuIndexRoute(): string
    {
        return Auth::guard('employee')->check()
            ? 'employee.organization.menu.index'
            : 'organizations.menu.index';
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