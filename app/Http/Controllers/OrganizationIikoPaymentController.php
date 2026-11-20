<?php

namespace App\Http\Controllers;

use App\Models\IikoConnection;
use App\Models\IikoPaymentMapping;
use App\Models\Organization;
use App\Services\Iiko\IikoPaymentService;
use App\Services\Iiko\IikoSyncLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

class OrganizationIikoPaymentController extends Controller
{
    public function __construct(
        private readonly IikoPaymentService $paymentService,
        private readonly IikoSyncLogger $logger
    ) {
    }

    public function discover(Organization $organization): View|RedirectResponse
    {
        $connection = $this->connectionOrFail($organization);
        $startedAt = now();

        try {
            $payload = $this->paymentService->paymentTypes($connection);
            $paymentTypes = collect($payload['paymentTypes'] ?? [])
                ->filter(fn ($item) => is_array($item) && !empty($item['id']))
                ->map(fn ($item) => [
                    'id' => (string) $item['id'],
                    'name' => (string) ($item['name'] ?? 'Способ оплаты iiko'),
                    'kind' => (string) ($item['kind'] ?? $item['paymentTypeKind'] ?? ''),
                    'is_default' => (bool) ($item['isDefault'] ?? false),
                ])
                ->values();

            $this->logger->record(
                $connection,
                'payment_types',
                'success',
                'Справочник способов оплаты iiko получен.',
                $payload['correlationId'] ?? null,
                ['items_count' => $paymentTypes->count(), 'endpoint' => '/api/1/payment_types'],
                $startedAt
            );

            return view('organizations.integrations.iiko-payments', [
                'organization' => $organization,
                'connection' => $connection,
                'paymentTypes' => $paymentTypes,
                'paymentMappings' => IikoPaymentMapping::query()
                    ->where('organization_id', $organization->id)
                    ->get()
                    ->keyBy('daodes_payment_method'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $this->logger->record(
                $connection,
                'payment_types',
                'failed',
                'Не удалось получить справочник способов оплаты iiko.',
                null,
                ['endpoint' => '/api/1/payment_types'],
                $startedAt
            );

            return redirect()
                ->route('organizations.iiko.index', $organization)
                ->withErrors(['iiko' => 'Не удалось загрузить способы оплаты iiko. Проверьте права API Key.']);
        }
    }

    public function save(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'daodes_payment_method' => ['required', 'in:cash_on_receipt,card_on_receipt,online,guest_account'],
            'iiko_payment_type_id' => ['required', 'uuid'],
        ]);

        $connection = $this->connectionOrFail($organization);

        try {
            $payload = $this->paymentService->paymentTypes($connection);
            $selected = collect($payload['paymentTypes'] ?? [])
                ->first(fn ($item) =>
                    is_array($item) &&
                    (string) ($item['id'] ?? '') === $validated['iiko_payment_type_id']
                );

            abort_unless($selected, 422, 'Выбранный способ оплаты больше не доступен в iiko.');

            IikoPaymentMapping::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'daodes_payment_method' => $validated['daodes_payment_method'],
                ],
                [
                    'iiko_payment_type_id' => $validated['iiko_payment_type_id'],
                    'iiko_payment_type_name' => (string) ($selected['name'] ?? 'Способ оплаты iiko'),
                    'payment_type_kind' => (string) ($selected['kind'] ?? $selected['paymentTypeKind'] ?? ''),
                    'is_enabled' => true,
                    'metadata' => [
                        'is_default' => (bool) ($selected['isDefault'] ?? false),
                    ],
                ]
            );

            return redirect()
                ->route('organizations.iiko.index', $organization)
                ->with('success', 'Способ оплаты сопоставлен. Передача фактических платежей включается отдельно после согласования финансовой схемы.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('organizations.iiko.index', $organization)
                ->withErrors(['iiko' => 'Не удалось сохранить сопоставление способа оплаты. Обновите справочник iiko.']);
        }
    }

    private function connectionOrFail(Organization $organization): IikoConnection
    {
        $connection = $organization->iikoConnection;

        abort_unless(
            $connection &&
            filled($connection->api_key) &&
            filled($connection->iiko_organization_id),
            422,
            'Сначала подключите iiko и выберите организацию.'
        );

        return $connection;
    }
}
