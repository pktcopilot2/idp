<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'grant_types' => ['required', 'array', 'min:1'],
            'grant_types.*' => ['required', 'string', 'in:authorization_code,client_credentials,password,implicit,refresh_token'],
            'redirect_uris' => ['array'],
            'redirect_uris.*' => ['required', 'url'],
            'login_uri' => ['required', 'url', 'max:2048'],
        ];
    }
}
