<?php

namespace App\Http\Controllers;

use App\Models\IikoConnection;
use App\Models\IikoStopListItem;
use App\Services\Iiko\IikoStopListService;
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
        private readonly IikoStopListService $stopListService
    ) {
    }

    public function index(Organization $organization): View
    {
        return view('organizations.integrations.iiko-menu', [
            'organization' => $organization,
            'connection' => $organization->iikoConnection,
            'externalMenus' => collect(),
            'priceCategories' => collect(),
            'previewItems' => collect(),
            'menuPreview' => null,
            'stopListCount' => $connection ? IikoStopListItem::query()->where('organization_id', $organization->id)->count() : 0,
        ]);
    }

    public function discover(Organization $organization): View|RedirectResponse
    {
        $connection = $this->connectionOrFail($organization);

        try {
            $payload = $this->menuService->externalMenus($connection);
            $externalMenus = collect($payload['externalMenus'] ?? [])
                ->filter(fn ($menu) => is_array($menu))
                ->map(fn ($menu) => [
                    'id' => (string) ($menu['externalMenuId'] ?? $menu['id'] ?? ''),
                    'name' => (string) ($menu['name'] ?? $menu['externalMenuName'] ?? 'Внешнее меню iiko'),
                    'price_categories' => collect($menu['priceCategories'] ?? [])
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

            $priceCategories = collect($selectedMenu['priceCategories'] ?? [])
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

            $connection->update([
                'external_menu_id' => $validated['external_menu_id'],
                'price_category_id' => $validated['price_category_id'] ?? null,
                'last_menu_sync_at' => now(),
                'last_error' => null,
            ]);

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
            ]);
        } catch (Throwable $exception) {
            report($exception);

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

            return redirect()
                ->route('organizations.iiko.menu.index', $organization)
                ->withErrors(['iiko_menu' => 'Не удалось обновить стоп-лист iiko. Предыдущий снимок сохранён без изменений.']);
        }
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
