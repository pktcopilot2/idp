<?php

namespace App\Features;

class EmailMfa
{
    public function resolve(mixed $scope): bool
    {
        return (bool) config('features.email_mfa');
    }
}
