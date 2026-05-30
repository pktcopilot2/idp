<?php

namespace App\Features;

class TwoFactorAuthentication
{
    public function resolve(mixed $scope): bool
    {
        return (bool) config('features.two_factor_authentication');
    }
}
