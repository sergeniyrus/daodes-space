<?php

namespace App\Http\Controllers;

use App\Exceptions\IikoApiException;
use App\Models\IikoConnection;
use App\Models\Organization;
use App\Services\Iiko\IikoCloudClient;
use App\Services\Iiko\IikoSyncLogger;
use App\Services\Iiko\IikoWebhookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Throwable;

class OrganizationIikoController extends Controller
{
    public function __construct(
        private readonly IikoCloudClient $iiko,
        private readonly IikoSyncLogger $logger
    ) {
    }

    public function index(Organization $organization): View
    {
        $connection = $organization->iikoConnection;

        return view('organizations.integrations.iiko', [
            'organization' => $organization,
            'connection' => $connection,
            'syncLogs' => $organization->iikoSyncLogs()->latest('id')->limit(10)->get(),
            'iikoOrganizations' => [],
            'terminalGroups' => [],
        ]);
    }

    public function saveCredentials(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'api_key' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $connection = $organization->iikoConnection()->firstOrNew();
        $keyChanged = !$connection->exists || !hash_equals(
            (string) $connection->api_key,
            (string) $validated['api_key']
        );

        $connection->api_key = $validated['api_key'];

        if (!filled($connection->webhook_secret)) {
            $connection->webhook_secret = Str::random(64);
        }

        if ($keyChanged) {
            $connection->iiko_organization_id = null;
            $connection->iiko_organization_name = null;
            $connection->terminal_group_id = null;
            $connection->terminal_group_name = null;
            $connection->is_enabled = false;
            $connection->connection_status = 'pending';
            $connection->last_connected_at = null;
            $connection->last_error = null;
        }

        $connection->save();

        return redirect()
            ->route('organizations.iiko.index', $organization)
            ->with('success', 'API Key сохранён в зашифрованном виде. Теперь проверьте подключение.');
    }

    public function discoverOrganizations(Organization $organization): View|RedirectResponse
    {
        $connection = $this->connectionOrFail($organization);

        $startedAt = now();

        try {
            $payload = $this->iiko->request($connection, 'POST', '/api/1/organizations');
            $organizations = collect($payload['organizations'] ?? [])
                ->filter(fn ($item) => is_array($item) && !empty($item['id']))
                ->map(fn ($item) => [
                    'id' => (string) $item['id'],
                    'name' => (string) ($item['name'] ?? 'Организация iiko'),
                ])
                ->values();

            if ($organizations->isEmpty()) {
                $connection->update([
                    'connection_status' => 'error',
                    'last_error' => 'API Key не вернул доступных организаций.',
                ]);

                return redirect()
                    ->route('organizations.iiko.index', $organization)
                    ->withErrors(['iiko' => 'Подключение выполнено, но iiko не вернула доступных организаций. Проверьте права API Key в iikoWeb.']);
            }

            $connection->update([
                'connection_status' => 'connected',
                'last_connected_at' => now(),
                'last_error' => null,
            ]);

            $this->logger->record(
                $connection,
                'connection',
                'success',
                'Подключение iiko проверено.',
                $payload['correlationId'] ?? null,
                ['organizations_count' => $organizations->count(), 'endpoint' => '/api/1/organizations'],
                $startedAt
            );

            return view('organizations.integrations.iiko', [
                'organization' => $organization,
                'connection' => $connection->fresh(),
                'iikoOrganizations' => $organizations,
                'terminalGroups' => [],
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->logger->record(
                $connection,
                'connection',
                'failed',
                $this->safeErrorMessage($exception),
                null,
                ['endpoint' => '/api/1/organizations'],
                $startedAt
            );

            $connection->update([
                'connection_status' => 'error',
                'last_error' => $this->safeErrorMessage($exception),
            ]);

            return redirect()
                ->route('organizations.iiko.index', $organization)
                ->withErrors(['iiko' => $this->safeErrorMessage($exception)]);
        }
    }

    public function discoverTerminalGroups(Request $request, Organization $organization): View|RedirectResponse
    {
        $validated = $request->validate([
            'iiko_organization_id' => ['required', 'uuid'],
        ]);

        $connection = $this->connectionOrFail($organization);

        try {
            $organizationsPayload = $this->iiko->request($connection, 'POST', '/api/1/organizations');
            $organizations = collect($organizationsPayload['organizations'] ?? [])
                ->filter(fn ($item) => is_array($item) && !empty($item['id']))
                ->map(fn ($item) => [
                    'id' => (string) $item['id'],
                    'name' => (string) ($item['name'] ?? 'Организация iiko'),
                ])
                ->values();

            $selectedOrganization = $organizations->firstWhere('id', $validated['iiko_organization_id']);

            abort_unless($selectedOrganization, 422, 'Выбранная организация недоступна для этого API Key.');

            $groupsPayload = $this->iiko->request($connection, 'POST', '/api/1/terminal_groups', [
                'organizationIds' => [$validated['iiko_organization_id']],
            ]);

            $terminalGroups = collect($groupsPayload['terminalGroups'] ?? [])
                ->flatMap(function ($group) use ($validated) {
                    if (!is_array($group)) {
                        return [];
                    }

                    $organizationId = (string) ($group['organizationId'] ?? $validated['iiko_organization_id']);

                    return collect($group['items'] ?? [])
                        ->filter(fn ($item) => is_array($item) && !empty($item['id']))
                        ->map(fn ($item) => [
                            'id' => (string) $item['id'],
                            'name' => (string) ($item['name'] ?? 'Терминальная группа'),
                            'organization_id' => $organizationId,
                        ]);
                })
                ->values();

            if ($terminalGroups->isEmpty()) {
                return redirect()
                    ->route('organizations.iiko.index', $organization)
                    ->withErrors(['iiko' => 'Для выбранной организации iiko не вернула терминальные группы.']);
            }

            return view('organizations.integrations.iiko', [
                'organization' => $organization,
                'connection' => $connection,
                'iikoOrganizations' => $organizations,
                'terminalGroups' => $terminalGroups,
                'selectedIikoOrganization' => $selectedOrganization,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('organizations.iiko.index', $organization)
                ->withErrors(['iiko' => $this->safeErrorMessage($exception)]);
        }
    }

    public function selectTerminalGroup(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'iiko_organization_id' => ['required', 'uuid'],
            'iiko_organization_name' => ['required', 'string', 'max:255'],
            'terminal_group_id' => ['required', 'uuid'],
            'terminal_group_name' => ['required', 'string', 'max:255'],
        ]);

        $connection = $this->connectionOrFail($organization);

        try {
            $organizationsPayload = $this->iiko->request($connection, 'POST', '/api/1/organizations');
            $availableOrganization = collect($organizationsPayload['organizations'] ?? [])
                ->first(fn ($item) => is_array($item) && ($item['id'] ?? null) === $validated['iiko_organization_id']);

            abort_unless($availableOrganization, 422, 'Организация iiko больше недоступна для этого API Key.');

            $groupsPayload = $this->iiko->request($connection, 'POST', '/api/1/terminal_groups', [
                'organizationIds' => [$validated['iiko_organization_id']],
            ]);

            $availableGroup = collect($groupsPayload['terminalGroups'] ?? [])
                ->flatMap(fn ($group) => is_array($group) ? ($group['items'] ?? []) : [])
                ->first(fn ($item) => is_array($item) && ($item['id'] ?? null) === $validated['terminal_group_id']);

            abort_unless($availableGroup, 422, 'Терминальная группа больше недоступна для выбранной организации.');

            // Organization settings are useful for address formatting, but a missing
            // permission must not prevent the manager from saving the restaurant/terminal.
            $addressFormatType = null;

            try {
                $organizationSettingsPayload = $this->iiko->request(
                    $connection,
                    'POST',
                    '/api/1/organizations/settings',
                    [
                        'organizationIds' => [$validated['iiko_organization_id']],
                        'parameters' => ['AddressFormatType', 'CurrencyIsoName'],
                    ]
                );

                $organizationSettings = collect($organizationSettingsPayload['organizations'] ?? [])
                    ->first(fn ($settings) =>
                        is_array($settings) &&
                        (string) ($settings['organizationId'] ?? '') === $validated['iiko_organization_id']
                    );

                $addressFormatType = $organizationSettings['addressFormatType'] ?? null;
            } catch (Throwable $settingsException) {
                report($settingsException);
            }

            $connection->update([
                'iiko_organization_id' => $validated['iiko_organization_id'],
                'iiko_organization_name' => (string) ($availableOrganization['name'] ?? $validated['iiko_organization_name']),
                'terminal_group_id' => $validated['terminal_group_id'],
                'terminal_group_name' => (string) ($availableGroup['name'] ?? $validated['terminal_group_name']),
                'address_format_type' => is_string($addressFormatType) ? $addressFormatType : null,
                'connection_status' => 'connected',
                'last_connected_at' => now(),
                'last_error' => null,
            ]);

            return redirect()
                ->route('organizations.iiko.index', $organization)
                ->with('success', 'Организация iiko и терминальная группа успешно выбраны.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('organizations.iiko.index', $organization)
                ->withErrors(['iiko' => $this->safeErrorMessage($exception)]);
        }
    }


    public function configureWebhook(
        Organization $organization,
        IikoWebhookService $webhookService
    ): RedirectResponse {
        $connection = $this->connectionOrFail($organization);

        try {
            $response = $webhookService->configure($connection);

            $connection->update([
                'webhook_enabled' => true,
                'last_error' => null,
            ]);

            $this->logger->record(
                $connection,
                'webhook_setup',
                'success',
                'Вебхук iiko настроен.',
                $response['correlationId'] ?? null,
                ['endpoint' => '/api/1/webhooks/update_settings']
            );

            return redirect()
                ->route('organizations.iiko.index', $organization)
                ->with('success', 'Вебхук iiko настроен. DAODES будет получать изменения заказов и стоп-листов.');
        } catch (Throwable $exception) {
            report($exception);

            $this->logger->record(
                $connection,
                'webhook_setup',
                'failed',
                'Не удалось настроить вебхук iiko.',
                null,
                ['endpoint' => '/api/1/webhooks/update_settings']
            );

            return redirect()
                ->route('organizations.iiko.index', $organization)
                ->withErrors(['iiko' => 'Не удалось настроить вебхук iiko. Проверьте доступ API Key к настройкам вебхуков и публичный HTTPS URL.']);
        }
    }

    public function checkWebhook(
        Organization $organization,
        IikoWebhookService $webhookService
    ): RedirectResponse {
        $connection = $this->connectionOrFail($organization);

        try {
            $response = $webhookService->settings($connection);

            $this->logger->record(
                $connection,
                'webhook_check',
                'success',
                'Настройки вебхука iiko проверены.',
                $response['correlationId'] ?? null,
                ['endpoint' => '/api/1/webhooks/settings']
            );

            return redirect()
                ->route('organizations.iiko.index', $organization)
                ->with('success', 'Настройки вебхука iiko успешно прочитаны.');
        } catch (Throwable $exception) {
            report($exception);

            $this->logger->record(
                $connection,
                'webhook_check',
                'failed',
                'Не удалось прочитать настройки вебхука iiko.',
                null,
                ['endpoint' => '/api/1/webhooks/settings']
            );

            return redirect()
                ->route('organizations.iiko.index', $organization)
                ->withErrors(['iiko' => 'Не удалось проверить настройки вебхука iiko.']);
        }
    }

    public function disconnect(Organization $organization): RedirectResponse
    {
        $organization->iikoConnection()->delete();

        return redirect()
            ->route('organizations.iiko.index', $organization)
            ->with('success', 'Подключение iiko удалено. Заказы и меню DAODES не затронуты.');
    }

    private function connectionOrFail(Organization $organization): IikoConnection
    {
        $connection = $organization->iikoConnection;

        abort_unless($connection && filled($connection->api_key), 422, 'Сначала сохраните API Key ресторана.');

        return $connection;
    }

    private function safeErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof IikoApiException) {
            return match ($exception->statusCode) {
                401 => 'iiko отклонила авторизацию. Проверьте appId, clientSecret и API Key.',
                403 => 'У API Key нет прав на запрошенный метод или организацию.',
                423 => 'Доступ к организации заблокирован в iiko. Проверьте подписку и настройки.',
                429 => 'iiko ограничила частоту запросов. Повторите попытку немного позже.',
                default => 'Не удалось выполнить запрос к iikoCloud. Проверьте настройки и журнал приложения.',
            };
        }

        return 'Не удалось подключиться к iikoCloud. Проверьте настройки интеграции и журнал приложения.';
    }
}
