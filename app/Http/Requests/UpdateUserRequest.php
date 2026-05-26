<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'active' => ['boolean'],
            'email_mfa_enabled' => ['boolean'],
            'is_need_password_reset' => ['boolean'],
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'password_confirmation' => ['nullable', 'string'],
        ];
    }
}
