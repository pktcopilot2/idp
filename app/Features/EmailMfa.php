<?php

namespace App\Features;

use App\Models\User;

class EmailMfa
{
    /**
     * Run an always-in-memory check before the stored value is retrieved.
     */
    // public function before(User $user): mixed
    // {
    //     return (bool) config('features.email_mfa');
    // }

    /**
     * Resolve the feature's initial value.
     */
    public function resolve(?User $user): bool
    {
        if (str_contains($user?->email, 'pupukkaltim.com') || is_null($user)) {
            return true;
        } else {
            return false;
        }
    }
}
