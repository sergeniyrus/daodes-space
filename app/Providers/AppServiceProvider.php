<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use App\Models\Notification;
use Illuminate\Support\ServiceProvider;
use App\Rules\Recaptcha; // Убедитесь, что это правило существует
use Illuminate\Support\Facades\Validator;
use App\Services\IpStackService;
use Illuminate\Support\Facades\URL;
use App\Contracts\SmsTransport;
use App\Services\Sms\SmsRuTransport;
use App\Services\Sms\UnconfiguredSmsTransport;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов.
     *
     * @return void
     */
    public function boot()
    {

        if (app()->environment('local')) {
            URL::forceScheme('https'); // Laravel форсит https
        }
        
        // Передача переменной $unreadCount во все шаблоны
        View::composer('*', function ($view) {
            if (auth()->check()) {
                $unreadCount = Notification::where('user_id', auth()->id())
                    ->where('is_read', false)
                    ->count();
                $view->with('unreadCount', $unreadCount);
            } else {
                $view->with('unreadCount', 0);
            }
        });



        // Общая шапка и категории клиентского кабинета.
        View::composer('customer.layouts.workspace', function ($view) {
            $cart = session('customer_order_cart', []);
            $organization = request()->route('organization');
            $order = request()->route('order');

            if ($order instanceof \App\Models\Order) {
                $organization = $order->organization;
            }

            if (!$organization && !empty($cart['organization_id'])) {
                $organization = \App\Models\Organization::query()
                    ->with('orderSettings')
                    ->find((int) $cart['organization_id']);
            }

            if (!$organization && session()->has('customer_workspace_organization_id')) {
                $organization = \App\Models\Organization::query()
                    ->with('orderSettings')
                    ->find((int) session('customer_workspace_organization_id'));
            }

            $customer = auth('customer')->user();
            if (!$organization && $customer) {
                $lastOrder = $customer->orders()->with('organization.orderSettings')->latest()->first();
                $organization = $lastOrder?->organization;
            }

            if (!$organization || !$organization->isActive()) {
                return;
            }

            session(['customer_workspace_organization_id' => $organization->id]);
            $settings = $organization->orderSettings;
            $sections = $organization->menuSections()
                ->where('is_active', true)
                ->with('activeItems')
                ->orderBy('sort_order')
                ->get();

            $cartCount = 0;
            $cartSubtotalCents = 0;
            if (
                (int) ($cart['organization_id'] ?? 0) === (int) $organization->id
                && is_array($cart['items'] ?? null)
            ) {
                $quantities = $cart['items'];
                $cartCount = array_sum(array_map('intval', $quantities));
                if ($quantities !== []) {
                    $cartItems = \App\Models\MenuItem::query()
                        ->whereIn('id', array_map('intval', array_keys($quantities)))
                        ->where('is_active', true)
                        ->whereHas('section', function ($query) use ($organization) {
                            $query->where('organization_id', $organization->id)->where('is_active', true);
                        })
                        ->get()->keyBy('id');

                    foreach ($quantities as $itemId => $quantity) {
                        $item = $cartItems->get((int) $itemId);
                        $quantity = (int) $quantity;
                        if ($item && $quantity > 0 && $quantity <= 99) {
                            $cartSubtotalCents += (int) round((float) $item->price * 100) * $quantity;
                        }
                    }
                }
            }

            $ordersQuery = $customer ? $customer->orders()->where('organization_id', $organization->id) : null;
            $ordersCount = $ordersQuery ? (clone $ordersQuery)->count() : 0;
            $ordersInProgressCount = $ordersQuery
                ? (clone $ordersQuery)->whereNotIn('status', [
                    \App\Models\Order::STATUS_COMPLETED,
                    \App\Models\Order::STATUS_CANCELLED,
                ])->count()
                : 0;

            $view->with('customerWorkspace', [
                'organization' => $organization,
                'settings' => $settings,
                'sections' => $sections,
                'canOrder' => $settings?->canAcceptOrders() ?? false,
                'cartCount' => $cartCount,
                'cartSubtotal' => $cartSubtotalCents / 100,
                'ordersHistoryCount' => $ordersCount - $ordersInProgressCount,
                'ordersInProgressCount' => $ordersInProgressCount,
                'currencySymbol' => $settings?->currency === 'RUB' ? '₽' : ($settings?->currency ?? ''),
            ]);
        });

        // Регистрация кастомного правила валидации reCAPTCHA
        Validator::extend('recaptcha', function ($attribute, $value, $parameters, $validator) {
            return (new Recaptcha)->passes($attribute, $value);
        });
    }

    /**
     * Регистрация сервисов приложения.
     *
     * @return void
     */
    public function register()
    {
        // SMS.RU используется только при наличии API ID в конфигурации.
        // Если ключ не задан, остаётся безопасная реализация без отправки SMS.
        $this->app->bind(SmsTransport::class, function ($app) {
            $apiId = config('services.sms_ru.api_id');

            if (is_string($apiId) && trim($apiId) !== '') {
                return $app->make(SmsRuTransport::class);
            }

            return $app->make(UnconfiguredSmsTransport::class);
        });

        // Регистрация сервиса IpStackService
        $this->app->singleton('ipstack', function () {
            return new IpStackService();
        });

        // Здесь можно зарегистрировать другие привязки контейнера, если необходимо
    }
}
