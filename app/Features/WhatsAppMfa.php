<?php

namespace App\Features;

use App\Models\User;

class WhatsAppMfa
{
    /**
     * Run an always-in-memory check before the stored value is retrieved.
     */
    // public function before(User $user): mixed
    // {
    //     return (bool) config('features.whatsapp_mfa');
    // }

    /**
     * Resolve the feature's initial value.
     */
    public function resolve(User $user): bool
    {
        if (str_contains($user->email, 'pupukkaltim.com')) {
            return true;
        } else {
            return false;
        }
    }
}
