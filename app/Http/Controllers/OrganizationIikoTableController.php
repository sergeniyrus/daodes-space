<?php

namespace App\Http\Controllers;

use App\Models\IikoConnection;
use App\Models\IikoTableMapping;
use App\Models\Organization;
use App\Services\Iiko\IikoTableService;
use App\Services\Iiko\IikoSyncLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

class OrganizationIikoTableController extends Controller
{
    public function __construct(
        private readonly IikoTableService $tableService,
        private readonly IikoSyncLogger $logger
    ) {
    }

    public function index(Organization $organization): View
    {
        return view('organizations.integrations.iiko-tables', [
            'organization' => $organization,
            'connection' => $organization->iikoConnection,
            'restaurantTables' => collect(),
            'tableMappings' => IikoTableMapping::query()
                ->where('organization_id', $organization->id)
                ->orderBy('daodes_table_number')
                ->get()
                ->keyBy('daodes_table_number'),
        ]);
    }

    public function discover(Organization $organization): View|RedirectResponse
    {
        $connection = $this->connectionOrFail($organization);
        $startedAt = now();

        try {
            $payload = $this->tableService->restaurantSections($connection);

            $restaurantTables = collect($payload['restaurantSections'] ?? [])
                ->flatMap(function ($section) {
                    if (!is_array($section)) {
                        return [];
                    }

                    return collect($section['tables'] ?? [])
                        ->filter(fn ($table) => is_array($table) && !empty($table['id']))
                        ->map(fn ($table) => [
                            'id' => (string) $table['id'],
                            'name' => (string) ($table['name'] ?? 'Стол iiko'),
                            'section_id' => (string) ($section['id'] ?? ''),
                            'section_name' => (string) ($section['name'] ?? 'Зал'),
                        ]);
                })
                ->values();

            $this->logger->record(
                $connection,
                'table_discovery',
                'success',
                'Список столов iiko получен.',
                $payload['correlationId'] ?? null,
                ['items_count' => $restaurantTables->count(), 'endpoint' => '/api/1/reserve/available_restaurant_sections'],
                $startedAt
            );

            return view('organizations.integrations.iiko-tables', [
                'organization' => $organization,
                'connection' => $connection,
                'restaurantTables' => $restaurantTables,
                'tableMappings' => IikoTableMapping::query()
                    ->where('organization_id', $organization->id)
                    ->get()
                    ->keyBy('daodes_table_number'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $this->logger->record(
                $connection,
                'table_discovery',
                'failed',
                'Не удалось получить список столов iiko.',
                null,
                ['endpoint' => '/api/1/reserve/available_restaurant_sections'],
                $startedAt
            );

            return redirect()
                ->route('organizations.iiko.tables.index', $organization)
                ->withErrors(['iiko_tables' => 'Не удалось получить столы iiko. Проверьте доступ к залам и терминальной группе.']);
        }
    }

    public function save(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'daodes_table_number' => ['required', 'string', 'max:64'],
            'iiko_table_id' => ['required', 'uuid'],
        ]);

        $connection = $this->connectionOrFail($organization);

        try {
            $payload = $this->tableService->restaurantSections($connection);

            $selected = collect($payload['restaurantSections'] ?? [])
                ->flatMap(function ($section) {
                    if (!is_array($section)) {
                        return [];
                    }

                    return collect($section['tables'] ?? [])
                        ->filter(fn ($table) => is_array($table))
                        ->map(fn ($table) => [
                            'id' => (string) ($table['id'] ?? ''),
                            'name' => (string) ($table['name'] ?? 'Стол iiko'),
                            'section_id' => (string) ($section['id'] ?? ''),
                            'section_name' => (string) ($section['name'] ?? 'Зал'),
                        ]);
                })
                ->first(fn ($table) => $table['id'] === $validated['iiko_table_id']);

            abort_unless($selected, 422, 'Выбранный стол больше не доступен в iiko.');

            $alreadyMapped = IikoTableMapping::query()
                ->where('organization_id', $organization->id)
                ->where('iiko_table_id', $validated['iiko_table_id'])
                ->where('daodes_table_number', '!=', trim($validated['daodes_table_number']))
                ->exists();

            abort_unless(!$alreadyMapped, 422, 'Этот стол iiko уже сопоставлен с другим номером DAODES.');

            IikoTableMapping::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'daodes_table_number' => trim($validated['daodes_table_number']),
                ],
                [
                    'iiko_table_id' => $selected['id'],
                    'iiko_table_name' => $selected['name'],
                    'restaurant_section_id' => $selected['section_id'] ?: null,
                    'restaurant_section_name' => $selected['section_name'],
                ]
            );

            return redirect()
                ->route('organizations.iiko.tables.index', $organization)
                ->with('success', 'Стол DAODES сопоставлен со столом iiko.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('organizations.iiko.tables.index', $organization)
                ->withErrors(['iiko_tables' => 'Не удалось сохранить сопоставление стола. Обновите список столов iiko.']);
        }
    }

    private function connectionOrFail(Organization $organization): IikoConnection
    {
        $connection = $organization->iikoConnection;

        abort_unless(
            $connection &&
            filled($connection->api_key) &&
            filled($connection->terminal_group_id),
            422,
            'Сначала подключите iiko и выберите терминальную группу.'
        );

        return $connection;
    }
}
