<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Order;
use App\Models\OrganizationOrderSetting;
use App\Models\OrganizationActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    /**
     * Список организаций текущего пользователя.
     */
    public function index(): View
    {
        $user = Auth::user();

        $organizations = $user->organizations()
            ->withPivot([
                'role',
                'status',
            ])
            ->get();

        return view('organizations.index', compact('organizations'));
    }

    /**
     * Форма создания организации.
     */
    public function create(): View
    {
        return view('organizations.create');
    }

    /**
     * Создание организации.
     *
     * Текущий пользователь автоматически становится
     * менеджером созданной организации.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $user = Auth::user();

        $organization = DB::transaction(function () use ($validated, $user) {

            $organization = Organization::create([
                'name' => $validated['name'],
                'status' => 'active',
            ]);

            $organization->users()->attach($user->id, [
                'role' => 'manager',
                'status' => 'active',
            ]);

            return $organization;
        });

        return redirect()
            ->route('organizations.manage', $organization)
            ->with(
                'success',
                __('organizations.created_successfully')
            );
    }

    /**
     * Панель управления организацией.
     *
     * Доступ проверяется middleware:
     * organization.manager
     */
    public function manage(Request $request, Organization $organization): View
    {
        /*
         * Получаем участников организации
         * вместе с их профилями.
         */
        $members = $organization->users()
            ->with('profile')
            ->withPivot([
                'role',
                'status',
            ])
            ->get();

        /*
         * ID пользователей, которые уже состоят
         * в организации.
         */
        $memberIds = $members->pluck('id');

        /*
         * Пользователи DAODES, которых можно добавить
         * в организацию.
         *
         * Профиль загружаем сразу,
         * чтобы впоследствии можно было использовать avatar_url.
         */
        $userSearch = trim((string) $request->query('user_search', ''));

        $availableUsers = User::query()
            ->with('profile')
            ->whereNotIn('id', $memberIds)
            ->when($userSearch !== '', function ($query) use ($userSearch) {
                $query->where('name', 'like', '%' . addcslashes($userSearch, '%_\\\\') . '%');
            })
            ->orderBy('name')
            ->paginate(30, [
                'id',
                'name',
            ])
            ->withQueryString();

        /*
         * Отдельный персонал организации:
         * повара, курьеры и сотрудники не смешиваются
         * с обычными пользователями DAODES из users.
         */
        $staffMemberships = $organization->employeeMemberships()
            ->with('employee')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'pending' THEN 1 WHEN 'blocked' THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->get();

        /*
         * Операционная статистика онлайн-заказов.
         * Все выборки ограничены текущей организацией.
         */
        $todayStart = Carbon::now('Asia/Krasnoyarsk')->startOfDay()->utc();
        $tomorrowStart = $todayStart->copy()->addDay();

        $orderStats = $organization->orders()
            ->selectRaw(
                'COUNT(CASE WHEN created_at >= ? AND created_at < ? THEN 1 END) as orders_today,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as new_orders,
                 SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as kitchen_orders,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as ready_orders,
                 SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as delivery_orders,
                 SUM(CASE WHEN status = ? AND completed_at >= ? AND completed_at < ? THEN 1 ELSE 0 END) as completed_today,
                 SUM(CASE WHEN status = ? AND cancelled_at >= ? AND cancelled_at < ? THEN 1 ELSE 0 END) as cancelled_today',
                [
                    $todayStart,
                    $tomorrowStart,
                    Order::STATUS_NEW,
                    Order::STATUS_CONFIRMED,
                    Order::STATUS_PREPARING,
                    Order::STATUS_READY,
                    Order::STATUS_HANDED_TO_COURIER,
                    Order::STATUS_IN_TRANSIT,
                    Order::STATUS_COMPLETED,
                    $todayStart,
                    $tomorrowStart,
                    Order::STATUS_CANCELLED,
                    $todayStart,
                    $tomorrowStart,
                ]
            )
            ->first();

        $paidToday = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('orders.organization_id', $organization->id)
            ->where('payments.status', Order::PAYMENT_SUCCEEDED)
            ->whereNotNull('payments.paid_at')
            ->where('payments.paid_at', '>=', $todayStart)
            ->where('payments.paid_at', '<', $tomorrowStart)
            ->sum('payments.amount');

        $courierLoads = DB::table('order_assignments')
            ->join('orders', 'orders.id', '=', 'order_assignments.order_id')
            ->where('orders.organization_id', $organization->id)
            ->where('order_assignments.role', 'courier')
            ->whereNull('order_assignments.unassigned_at')
            ->whereIn('orders.status', [
                Order::STATUS_HANDED_TO_COURIER,
                Order::STATUS_IN_TRANSIT,
            ])
            ->select(
                'order_assignments.employee_id',
                DB::raw('COUNT(DISTINCT orders.id) as active_delivery_orders_count')
            )
            ->groupBy('order_assignments.employee_id')
            ->pluck('active_delivery_orders_count', 'order_assignments.employee_id');

        $activityLogs = OrganizationActivityLog::query()
            ->with('actor:id,name')
            ->where('organization_id', $organization->id)
            ->latest('id')
            ->limit(30)
            ->get();

        $courierMemberships = $staffMemberships
            ->where('role', 'courier')
            ->where('status', 'active')
            ->filter(fn ($membership) => $membership->employee !== null);

        foreach ($courierMemberships as $membership) {
            $membership->employee->setAttribute(
                'active_delivery_orders_count',
                (int) ($courierLoads[$membership->employee_id] ?? 0)
            );
        }

        return view('organizations.manage', [
            'organization' => $organization,
            'orderSettings' => $organization->orderSettings,
            'members' => $members,
            'availableUsers' => $availableUsers,
            'staffMemberships' => $staffMemberships,
            'orderStats' => $orderStats,
            'paidToday' => $paidToday,
            'courierMemberships' => $courierMemberships,
            'tvScreens' => range(1, max(1, (int) ($organization->tv_screens_count ?? 3))),
            'tvScreensConfig' => $organization->tv_screens_config ?? [],
            'activityLogs' => $activityLogs,
        ]);
    }

    /**
     * Добавление сотрудника в организацию.
     *
     * Доступ проверяется middleware:
     * organization.manager
     */
    public function storeEmployee(
        Request $request,
        Organization $organization
    ): RedirectResponse {
        $user = Auth::user();

        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        /*
         * Проверяем, что пользователь ещё не является
         * участником организации.
         */
        $alreadyMember = $organization->users()
            ->where('users.id', $validated['user_id'])
            ->exists();

        if ($alreadyMember) {
            return redirect()
                ->route('organizations.manage', $organization)
                ->with(
                    'error',
                    __('organizations.messages.already_member')
                );
        }

        /*
         * Нельзя добавить самого себя как employee.
         */
        if ((int) $validated['user_id'] === (int) $user->id) {
            return redirect()
                ->route('organizations.manage', $organization)
                ->with(
                    'error',
                    __('organizations.messages.cannot_add_self')
                );
        }

        $organization->users()->attach($validated['user_id'], [
            'role' => 'employee',
            'status' => 'active',
        ]);

        return redirect()
            ->route('organizations.manage', $organization)
            ->with(
                'success',
                __('organizations.messages.employee_added')
            );
    }

    /**
     * Изменение роли сотрудника.
     *
     * Доступ проверяется middleware:
     * organization.manager
     */
    public function updateEmployeeRole(
        Request $request,
        Organization $organization,
        User $user
    ): RedirectResponse {
        $manager = Auth::user();

        /*
         * Проверяем, что пользователь действительно
         * является участником этой организации.
         */
        $member = $organization->users()
            ->where('users.id', $user->id)
            ->first();

        abort_unless($member, 404);

        /*
         * Проверяем допустимую роль.
         */
        $validated = $request->validate([
            'role' => [
                'required',
                'in:manager,employee',
            ],
        ]);

        /*
         * Нельзя снять роль с самого себя.
         *
         * Иначе менеджер может случайно оставить
         * организацию без управляющего.
         */
        if ((int) $manager->id === (int) $user->id) {
            return redirect()
                ->route('organizations.manage', $organization)
                ->with(
                    'error',
                    __('organizations.messages.cannot_change_own_role')
                );
        }

        /*
         * Нельзя убрать последнего активного менеджера.
         */
        if (
            $member->pivot->role === 'manager' &&
            $validated['role'] === 'employee'
        ) {
            $activeManagersCount = $organization->users()
                ->wherePivot('role', 'manager')
                ->wherePivot('status', 'active')
                ->count();

            if ($activeManagersCount <= 1) {
                return redirect()
                    ->route('organizations.manage', $organization)
                    ->with(
                        'error',
                        __('organizations.messages.last_manager')
                    );
            }
        }

        $organization->users()->updateExistingPivot(
            $user->id,
            [
                'role' => $validated['role'],
            ]
        );

        return redirect()
            ->route('organizations.manage', $organization)
            ->with(
                'success',
                __('organizations.messages.role_updated')
            );
    }

    /**
     * Изменение статуса сотрудника.
     *
     * Доступ проверяется middleware:
     * organization.manager
     */
    public function updateEmployeeStatus(
        Request $request,
        Organization $organization,
        User $user
    ): RedirectResponse {
        $manager = Auth::user();

        /*
         * Проверяем участие пользователя.
         */
        $member = $organization->users()
            ->where('users.id', $user->id)
            ->first();

        abort_unless($member, 404);

        $validated = $request->validate([
            'status' => [
                'required',
                'in:active,inactive,blocked',
            ],
        ]);

        /*
         * Нельзя изменить собственный статус.
         */
        if ((int) $manager->id === (int) $user->id) {
            return redirect()
                ->route('organizations.manage', $organization)
                ->with(
                    'error',
                    __('organizations.messages.cannot_change_own_status')
                );
        }

        /*
         * Нельзя деактивировать/заблокировать
         * последнего активного менеджера.
         */
        if (
            $member->pivot->role === 'manager' &&
            $member->pivot->status === 'active' &&
            $validated['status'] !== 'active'
        ) {
            $activeManagersCount = $organization->users()
                ->wherePivot('role', 'manager')
                ->wherePivot('status', 'active')
                ->count();

            if ($activeManagersCount <= 1) {
                return redirect()
                    ->route('organizations.manage', $organization)
                    ->with(
                        'error',
                        __('organizations.messages.last_manager')
                    );
            }
        }

        $organization->users()->updateExistingPivot(
            $user->id,
            [
                'status' => $validated['status'],
            ]
        );

        return redirect()
            ->route('organizations.manage', $organization)
            ->with(
                'success',
                __('organizations.messages.status_updated')
            );
    }

    /**
     * Удаление сотрудника из организации.
     *
     * Доступ проверяется middleware:
     * organization.manager
     */
    public function destroyEmployee(
        Organization $organization,
        User $user
    ): RedirectResponse {
        $manager = Auth::user();

        /*
         * Проверяем, что пользователь действительно
         * является участником организации.
         */
        $member = $organization->users()
            ->where('users.id', $user->id)
            ->first();

        abort_unless($member, 404);

        /*
         * Нельзя удалить самого себя.
         */
        if ((int) $manager->id === (int) $user->id) {
            return redirect()
                ->route('organizations.manage', $organization)
                ->with(
                    'error',
                    __('organizations.messages.cannot_remove_self')
                );
        }

        /*
         * Нельзя удалить последнего активного менеджера.
         */
        if (
            $member->pivot->role === 'manager' &&
            $member->pivot->status === 'active'
        ) {
            $activeManagersCount = $organization->users()
                ->wherePivot('role', 'manager')
                ->wherePivot('status', 'active')
                ->count();

            if ($activeManagersCount <= 1) {
                return redirect()
                    ->route('organizations.manage', $organization)
                    ->with(
                        'error',
                        __('organizations.messages.last_manager')
                    );
            }
        }

        $organization->users()->detach($user->id);

        return redirect()
            ->route('organizations.manage', $organization)
            ->with(
                'success',
                __('organizations.messages.employee_removed')
            );
    }

    /**
     * Сохранение настроек онлайн-заказов организации.
     *
     * Доступ проверяется middleware organization.manager.
     */
    public function updateOrderSettings(
        Request $request,
        Organization $organization
    ): RedirectResponse {
        $booleanFields = [
            'orders_enabled',
            'delivery_enabled',
            'pickup_enabled',
            'dine_in_enabled',
            'online_payment_enabled',
            'cash_payment_enabled',
            'card_payment_enabled',
        ];

        foreach ($booleanFields as $field) {
            $request->merge([
                $field => $request->boolean($field),
            ]);
        }

        $currency = strtoupper(trim((string) $request->input('currency')));

        $request->merge([
            'currency' => $currency !== '' ? $currency : null,
        ]);

        $validated = $request->validate([
            'currency' => [
                'nullable',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],
            'delivery_fee' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
            'minimum_order' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
            'orders_enabled' => ['required', 'boolean'],
            'delivery_enabled' => ['required', 'boolean'],
            'pickup_enabled' => ['required', 'boolean'],
            'dine_in_enabled' => ['required', 'boolean'],
            'online_payment_enabled' => ['required', 'boolean'],
            'cash_payment_enabled' => ['required', 'boolean'],
            'card_payment_enabled' => ['required', 'boolean'],
            'pickup_min_minutes' => [
                'nullable',
                'integer',
                'min:0',
                'max:1440',
            ],
        ]);

        if ($validated['orders_enabled']) {
            if (empty($validated['currency'])) {
                return back()
                    ->withErrors(['currency' => 'Укажите трёхбуквенный код валюты, прежде чем включать заказы.'])
                    ->withInput();
            }

            if (
                !$validated['delivery_enabled']
                && !$validated['pickup_enabled']
                && !$validated['dine_in_enabled']
            ) {
                return back()
                    ->withErrors(['delivery_enabled' => 'Включите хотя бы один способ получения заказа.'])
                    ->withInput();
            }

            if (
                !$validated['cash_payment_enabled']
                && !$validated['card_payment_enabled']
            ) {
                return back()
                    ->withErrors(['cash_payment_enabled' => 'До подключения платёжного провайдера включите наличные или оплату терминалом при получении.'])
                    ->withInput();
            }
        }

        $settings = $organization->orderSettings;
        $trackedFields = array_keys($validated);
        $beforeValues = $settings?->only($trackedFields) ?? [];

        $settings = OrganizationOrderSetting::updateOrCreate(
            ['organization_id' => $organization->id],
            $validated
        );

        $afterValues = $settings->only($trackedFields);

        if ($beforeValues !== $afterValues) {
            app(\App\Services\OrganizationActivityLogger::class)->record(
                $organization,
                'order_settings.updated',
                'Изменены настройки онлайн-заказов',
                $beforeValues,
                $afterValues,
                'order_settings',
                $settings->id
            );
        }

        return redirect()
            ->route('organizations.manage', $organization)
            ->with('success', 'Настройки онлайн-заказов сохранены.');
    }

}
