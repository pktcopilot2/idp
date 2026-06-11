<?php

namespace App\Http\Requests\Auth;

use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class LoginRequest extends FortifyLoginRequest
{
    public function rules(): array
    {
        return [
            Fortify::username() => 'required|string',
            'password' => config('authentication.mode') === 'passwordless' ? 'nullable|string' : 'required|string',
            'remember' => 'sometimes',
        ];
    }
}
