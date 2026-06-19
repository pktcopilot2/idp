<?php

return [
    // External IdP clients should use this issuer value.
    'issuer' => env('OIDC_ISSUER', env('APP_URL')),

    // ID token lifetime in seconds.
    'id_token_ttl' => (int) env('OIDC_ID_TOKEN_TTL', 600),
];
