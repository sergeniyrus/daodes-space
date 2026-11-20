<?php

namespace App\Http\Controllers;

use App\Models\IikoConnection;
use App\Models\IikoStopListItem;
use App\Models\IikoMenuMapping;
use App\Models\MenuItem;
use App\Services\Iiko\IikoStopListService;
use App\Services\Iiko\IikoSyncLogger;
use App\Models\Organization;
use App\Services\Iiko\IikoMenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

class OrganizationIikoMenuController extends Controller
{
    public function __construct(
        private readonly IikoMenuService $menuService,
        private readonly IikoStopListService $stopListService,
        private readonly IikoSyncLogger $logger
    ) {
    }

    public function index(Organization $organization): View
    {
        $connection = $organization->iikoConnection;

        return view('organizations.integrations.iiko-menu', [
            'organization' => $organization,
            'connection' => $connection,
            'externalMenus' => collect(),
            'priceCategories' => collect(),
            'previewItems' => collect(),
            'menuPreview' => null,
            'stopListCount' => $connection ? IikoStopListItem::query()->where('organization_id', $organization->id)->count() : 0,
            'localMenuItems' => $this->localMenuItems($organization),
            'menuMappings' => collect(),
        ]);
    }

    public function discover(Organization $organization): View|RedirectResponse
    {
        $connection = $this->connectionOrFail($organization);
        $startedAt = now();

        try {
            $payload = $this->menuService->externalMenus($connection);
            $externalMenus = collect($payload['externalMenus'] ?? [])
                ->filter(fn ($menu) => is_array($menu))
                ->map(fn ($menu) => [
                    'id' => (string) ($menu['externalMenuId'] ?? $menu['id'] ?? ''),
                    'name' => (string) ($menu['name'] ?? $menu['externalMenuName'] ?? 'Внешнее меню iiko'),
                    'price_categories' => collect($menu['priceCategories'] ?? $payload['priceCategories'] ?? [])
                        ->filter(fn ($category) => is_array($category) && !empty($category['id']))
                        ->map(fn ($category) => [
                            'id' => (string) $category['id'],
                            'name' => (string) ($category['name'] ?? 'Ценовая категория'),
                        ])
                        ->values()
                        ->all(),
                ])
                ->filter(fn ($menu) => $menu['id'] !== '')
                ->values();

            if ($externalMenus->isEmpty()) {
                return redirect()
                    ->route('organizations.iiko.menu.index', $organization)
                    ->withErrors(['iiko_menu' => 'В iiko не настроено внешнее меню для выбранной организации. Попросите администратора ресторана настроить внешнее меню в iikoWeb.']);
            }

            $this->logger->record(
                $connection,
                'menu_discovery',
                'success',
                'Список внешних меню iiko получен.',
                $payload['correlationId'] ?? null,
                ['items_count' => $externalMenus->count(), 'endpoint' => '/api/2/menu'],
                $startedAt
            );

            return view('organizations.integrations.iiko-menu', [
                'organization' => $organization,
                'connection' => $connection,
                'externalMenus' => $externalMenus,
                'priceCategories' => collect(),
                'previewItems' => collect(),
                'menuPreview' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->logger->record(
                $connection,
                'menu_discovery',
                'failed',
                'Не удалось получить список внешних меню iiko.',
                null,
                ['endpoint' => '/api/2/menu'],
                $startedAt
            );

            return redirect()
                ->route('organizations.iiko.menu.index', $organization)
                ->withErrors(['iiko_menu' => 'Не удалось получить список внешних меню iiko. Проверьте выбранную организацию и права API Key.']);
        }
    }

    public function preview(Request $request, Organization $organization): View|RedirectResponse
    {
        $validated = $request->validate([
            'external_menu_id' => ['required', 'uuid'],
            'price_category_id' => ['nullable', 'uuid'],
        ]);

        $connection = $this->connectionOrFail($organization);

        try {
            // Re-check the menu against the live iiko response to prevent forged IDs.
            $menusPayload = $this->menuService->externalMenus($connection);
            $availableMenus = collect($menusPayload['externalMenus'] ?? [])
                ->filter(fn ($menu) => is_array($menu));

            $selectedMenu = $availableMenus->first(fn ($menu) =>
                (string) ($menu['externalMenuId'] ?? $menu['id'] ?? '') === $validated['external_menu_id']
            );

            abort_unless($selectedMenu, 422, 'Выбранное внешнее меню больше недоступно.');

            $priceCategories = collect($selectedMenu['priceCategories'] ?? $menusPayload['priceCategories'] ?? [])
                ->filter(fn ($category) => is_array($category) && !empty($category['id']))
                ->map(fn ($category) => [
                    'id' => (string) $category['id'],
                    'name' => (string) ($category['name'] ?? 'Ценовая категория'),
                ])
                ->values();

            if (
                filled($validated['price_category_id']) &&
                !$priceCategories->contains(fn ($category) => $category['id'] === $validated['price_category_id'])
            ) {
                abort(422, 'Выбранная ценовая категория не относится к этому меню.');
            }

            $payload = $this->menuService->externalMenu(
                $connection,
                $validated['external_menu_id'],
                $validated['price_category_id'] ?? null
            );

            $groups = collect($payload['itemsGroups'] ?? $payload['itemGroups'] ?? []);
            $previewItems = $groups->flatMap(function ($group) {
                if (!is_array($group)) {
                    return [];
                }

                return collect($group['items'] ?? [])
                    ->filter(fn ($item) => is_array($item) && !empty($item['productId']))
                    ->map(fn ($item) => [
                        'id' => (string) $item['productId'],
                        'name' => (string) ($item['name'] ?? 'Блюдо iiko'),
                        'price' => $item['price'] ?? ($item['sizePrices'][0]['price'] ?? null),
                        'group' => (string) ($group['name'] ?? 'Без категории'),
                        'has_modifiers' => !empty($item['itemModifierGroups']) || !empty($item['modifierGroups']),
                    ]);
            })->take(250)->values();

            $menuSelectionChanged = (string) $connection->external_menu_id !== $validated['external_menu_id']
                || (string) $connection->price_category_id !== (string) ($validated['price_category_id'] ?? '');

            $connectionUpdates = [
                'external_menu_id' => $validated['external_menu_id'],
                'price_category_id' => $validated['price_category_id'] ?? null,
                'last_menu_sync_at' => now(),
                'last_error' => null,
            ];

            if ($menuSelectionChanged) {
                $connectionUpdates['order_sync_enabled'] = false;
                $connectionUpdates['menu_sync_enabled'] = false;
            }

            $connection->update($connectionUpdates);

            $this->logger->record(
                $connection,
                'menu_preview',
                'success',
                'Внешнее меню iiko загружено для предварительного просмотра.',
                $payload['correlationId'] ?? null,
                ['items_count' => $previewItems->count(), 'external_menu_id' => $validated['external_menu_id'], 'endpoint' => '/api/menu/v3/by_id']
            );

            return view('organizations.integrations.iiko-menu', [
                'organization' => $organization,
                'connection' => $connection->fresh(),
                'externalMenus' => collect(),
                'priceCategories' => $priceCategories,
                'previewItems' => $previewItems,
                'menuPreview' => [
                    'name' => (string) ($selectedMenu['name'] ?? $selectedMenu['externalMenuName'] ?? 'Внешнее меню iiko'),
                    'external_menu_id' => $validated['external_menu_id'],
                    'total_items' => $previewItems->count(),
                    'correlation_id' => $payload['correlationId'] ?? null,
                ],
                'localMenuItems' => $this->localMenuItems($organization),
                'menuMappings' => IikoMenuMapping::query()
                    ->with('menuItem')
                    ->where('organization_id', $organization->id)
                    ->where('iiko_organization_id', $connection->iiko_organization_id)
                    ->where('external_menu_id', $connection->external_menu_id)
                    ->where('price_category_id', $connection->price_category_id)
                    ->get()
                    ->keyBy('iiko_product_id'),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->logger->record(
                $connection,
                'menu_preview',
                'failed',
                'Не удалось загрузить внешнее меню iiko.',
                null,
                ['external_menu_id' => $validated['external_menu_id'], 'endpoint' => '/api/menu/v3/by_id']
            );

            return redirect()
                ->route('organizations.iiko.menu.index', $organization)
                ->withErrors(['iiko_menu' => 'Не удалось загрузить меню iiko. Проверьте внешнее меню, ценовую категорию и права API Key.']);
        }
    }


    public function synchronizeStopList(Organization $organization): RedirectResponse
    {
        $connection = $this->connectionOrFail($organization);

        try {
            $count = $this->stopListService->synchronize($connection);

            return redirect()
                ->route('organizations.iiko.menu.index', $organization)
                ->with('success', 'Стоп-лист iiko обновлён. Временно недоступных позиций: ' . $count . '.');
        } catch (Throwable $exception) {
            report($exception);
            $this->logger->record(
                $connection,
                'stop_list',
                'failed',
                'Не удалось обновить стоп-лист iiko; предыдущий снимок сохранён.',
                null,
                ['endpoint' => '/api/1/stop_lists']
            );

            return redirect()
                ->route('organizations.iiko.menu.index', $organization)
                ->withErrors(['iiko_menu' => 'Не удалось обновить стоп-лист iiko. Предыдущий снимок сохранён без изменений.']);
        }
    }


    public function saveMapping(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'menu_item_id' => ['required', 'integer'],
            'iiko_product_id' => ['required', 'uuid'],
        ]);

        $connection = $this->connectionOrFail($organization);

        $menuItem = MenuItem::query()
            ->whereKey($validated['menu_item_id'])
            ->whereHas('section', fn ($query) => $query->where('organization_id', $organization->id))
            ->firstOrFail();

        try {
            $payload = $this->menuService->externalMenu(
                $connection,
                (string) $connection->external_menu_id,
                $connection->price_category_id
            );

            $matchedProduct = collect($payload['itemsGroups'] ?? $payload['itemGroups'] ?? [])
                ->flatMap(function ($group) {
                    if (!is_array($group)) {
                        return [];
                    }

                    return collect($group['items'] ?? [])
                        ->filter(fn ($item) => is_array($item))
                        ->map(function ($item) use ($group) {
                            $item['_daodes_group_name'] = (string) ($group['name'] ?? 'Без категории');
                            $item['_daodes_group_id'] = (string) ($group['id'] ?? '');

                            return $item;
                        });
                })
                ->first(fn ($item) =>
                    (string) ($item['productId'] ?? '') === $validated['iiko_product_id']
                );

            abort_unless($matchedProduct, 422, 'Блюдо iiko не найдено в выбранном внешнем меню.');

            $matchedPrice = $matchedProduct['price'] ?? ($matchedProduct['sizePrices'][0]['price'] ?? null);

            $alreadyMapped = IikoMenuMapping::query()
                ->where('organization_id', $organization->id)
                ->where('iiko_organization_id', $connection->iiko_organization_id)
                ->where('external_menu_id', $connection->external_menu_id)
                ->where('price_category_id', $connection->price_category_id)
                ->where('iiko_product_id', $validated['iiko_product_id'])
                ->where('menu_item_id', '!=', $menuItem->id)
                ->exists();

            abort_unless(!$alreadyMapped, 422, 'Это блюдо iiko уже сопоставлено с другой позицией DAODES.');

            IikoMenuMapping::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'iiko_organization_id' => $connection->iiko_organization_id,
                    'menu_item_id' => $menuItem->id,
                ],
                [
                    'iiko_product_id' => $validated['iiko_product_id'],
                    'iiko_product_name' => (string) ($matchedProduct['name'] ?? 'Блюдо iiko'),
                    'iiko_group_id' => filled($matchedProduct['_daodes_group_id']) ? $matchedProduct['_daodes_group_id'] : null,
                    'external_menu_id' => $connection->external_menu_id,
                    'price_category_id' => $connection->price_category_id,
                    'last_iiko_price' => is_numeric($matchedPrice) ? $matchedPrice : null,
                    'last_checked_at' => now(),
                    'metadata' => [
                        'iiko_group_name' => $matchedProduct['_daodes_group_name'],
                        'has_modifiers' => !empty($matchedProduct['itemModifierGroups']) || !empty($matchedProduct['modifierGroups']),
                        'has_multiple_sizes' => count($matchedProduct['sizePrices'] ?? []) > 1,
                    ],
                ]
            );

            return redirect()
                ->route('organizations.iiko.menu.index', $organization)
                ->with('success', 'Сопоставление сохранено. Каталог и цены DAODES не изменены.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('organizations.iiko.menu.index', $organization)
                ->withErrors(['iiko_menu' => 'Не удалось сохранить сопоставление. Проверьте, что блюдо присутствует в выбранном меню iiko.']);
        }
    }

    private function localMenuItems(Organization $organization): Collection
    {
        return MenuItem::query()
            ->with('section:id,name')
            ->whereHas('section', fn ($query) => $query->where('organization_id', $organization->id))
            ->orderBy('menu_section_id')
            ->orderBy('sort_order')
            ->get();
    }

    private function connectionOrFail(Organization $organization): IikoConnection
    {
        $connection = $organization->iikoConnection;

        abort_unless(
            $connection &&
            filled($connection->api_key) &&
            filled($connection->iiko_organization_id) &&
            filled($connection->terminal_group_id),
            422,
            'Сначала подключите iiko и выберите организацию с терминальной группой.'
        );

        return $connection;
    }
}
