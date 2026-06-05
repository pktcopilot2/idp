<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'keycloak' => [
        'client_id' => env('KEYCLOAK_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect' => env('KEYCLOAK_REDIRECT_URI'),
        'base_url' => env('KEYCLOAK_BASE_URL'),
        'realms' => env('KEYCLOAK_REALM'),
        'userinfo_endpoint' => env('KEYCLOAK_BASE_URL').'/realms/'.env('KEYCLOAK_REALM').'/protocol/openid-connect/userinfo',
        'account_endpoint' => env('KEYCLOAK_BASE_URL').'/realms/'.env('KEYCLOAK_REALM').'/account',
    ],

    'fusionauth' => [
        'client_id' => env('FUSIONAUTH_CLIENT_ID'),
        'client_secret' => env('FUSIONAUTH_CLIENT_SECRET'),
        'redirect' => env('FUSIONAUTH_REDIRECT_URI'),
        'base_url' => env('FUSIONAUTH_BASE_URL'),
        'tenant_id' => env('FUSIONAUTH_TENANT_ID'),
        'logout_url' => env('FUSIONAUTH_BASE_URL').'/oauth2/logout?client_id='.env('FUSIONAUTH_CLIENT_ID').'&tenantId='.env('FUSIONAUTH_TENANT_ID').'&post_logout_redirect_uri='.env('APP_URL').'/login',
        'userinfo_endpoint' => env('FUSIONAUTH_BASE_URL').'/oauth2/userinfo?tenantId='.env('FUSIONAUTH_TENANT_ID'),
        'account_endpoint' => env('FUSIONAUTH_BASE_URL').'/app/'.env('FUSIONAUTH_TENANT_ID').'/user/settings',
    ],

    'whatsapp_mfa' => [
        'endpoint' => env('WHATSAPP_MFA_ENDPOINT'),
        'api_key' => env('WHATSAPP_MFA_API_KEY'),
        'platform_id' => env('WHATSAPP_MFA_PLATFORM_ID'),
        'external_id' => 'idp-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
        'template_id' => env('WHATSAPP_MFA_TEMPLATE_ID'),
    ],

];
