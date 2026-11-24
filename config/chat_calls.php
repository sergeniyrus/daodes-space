<?php

return [
    // Comma-separated ICE server URLs. STUN is used when TURN is not configured.
    'stun_urls' => env('CHAT_STUN_URLS', 'stun:stun.l.google.com:19302'),

    // Example: turn:turn.daodes.space:3478?transport=udp,turns:turn.daodes.space:5349?transport=tcp
    'turn_urls' => env('CHAT_TURN_URLS', ''),

    // coturn static-auth-secret. Never expose this value to the browser.
    'turn_secret' => env('CHAT_TURN_SECRET', ''),

    // TURN REST credentials lifetime in seconds.
    'turn_ttl' => (int) env('CHAT_TURN_TTL', 3600),
];
