<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\UpdateLastSeenAt;
use App\Http\Middleware\CreatorReleaseAccess;
use App\Http\Middleware\AdminMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    apiPrefix: 'api',
)
    ->withBroadcasting(__DIR__.'/../routes/channels.php')
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->web([
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\CheckCookieConsent::class,
            \App\Http\Middleware\RedirectMessengerToDedicatedHost::class,
        ]);

        $middleware->alias([
            'admin' => AdminMiddleware::class,

            'organization.manager' => \App\Http\Middleware\OrganizationManagerMiddleware::class,
            'organization.member' => \App\Http\Middleware\OrganizationMemberMiddleware::class,

            'employee.auth' => \App\Http\Middleware\AuthenticateEmployee::class,
            'employee.organization' => \App\Http\Middleware\EmployeeOrganizationMiddleware::class,
            'employee.role' => \App\Http\Middleware\EmployeeRoleMiddleware::class,

            'creator.release' => CreatorReleaseAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Настройка обработки исключений
    })
    ->create();
