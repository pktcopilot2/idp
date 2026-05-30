<?php

namespace App\Features;

class WhatsAppMfa
{
    public function resolve(mixed $scope): bool
    {
        return (bool) config('features.whatsapp_mfa');
    }
}
