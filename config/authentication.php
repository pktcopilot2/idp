<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Mode
    |--------------------------------------------------------------------------
    |
    | Supported modes:
    | - password: require username and password, then continue through any
    |   configured MFA / 2FA checks.
    | - passwordless: require username only, then authenticate the user via
    |   their enabled 2FA / MFA method.
    |
    */

    'mode' => env('AUTHENTICATION_MODE', 'password'),
];
