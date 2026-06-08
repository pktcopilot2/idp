<?php

namespace App\Actions\Fortify;

use App\Helpers\LdapHelper;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ConfirmPassword
{
    public function __invoke(User $user, string $input): bool
    {
        if (LdapHelper::authAttempt($user->username, $input)) {
            return true;
        }

        if (Hash::check($input, $user->password)) {
            return true;
        }

        return false;
    }
}
