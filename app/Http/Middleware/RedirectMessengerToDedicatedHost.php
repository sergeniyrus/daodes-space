<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectMessengerToDedicatedHost
{
    /**
     * Keep messenger page traffic on its dedicated host while preserving
     * the existing Laravel routes and authenticated session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        $route = $request->route();
        $routeName = $route?->getName();

        $isMessengerPage = in_array($routeName, [
            'chats.index',
            'chats.create',
            'chats.show',
            'chats.notifications',
            'contacts.index',
        ], true) || $request->is('chats/create-with-user/*');

        if (!$isMessengerPage) {
            return $next($request);
        }

        $host = strtolower((string) config('messenger.host', 'webdeschat.daodes.space'));
        $currentHost = strtolower($request->getHost());

        if ($host === '' || $currentHost === $host) {
            return $next($request);
        }

        $scheme = (string) config('messenger.scheme', 'https');
        $url = $scheme . '://' . $host . $request->getRequestUri();

        return redirect()->away($url, 302);
    }
}
