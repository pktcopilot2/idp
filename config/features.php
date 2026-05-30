<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Feature Flags
    |--------------------------------------------------------------------------
    |
    | These values control which MFA / authentication features are available
    | to users. They serve as the initial resolved value for the corresponding
    | Laravel Pennant feature classes. Once a feature has been evaluated and
    | stored in Pennant's store, the stored value takes precedence. Use
    | `php artisan pennant:purge` to reset and re-evaluate from these defaults.
    |
    */

    'two_factor_authentication' => env('FEATURE_2FA', true),

    'email_mfa' => env('FEATURE_EMAIL_MFA', true),

    'whatsapp_mfa' => env('FEATURE_WHATSAPP_MFA', true),

];
