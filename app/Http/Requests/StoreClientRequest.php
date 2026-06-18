<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'confidential' => ['boolean'],
            'pkce_enabled' => ['boolean'],
            'grant_types' => ['required', 'array', 'min:1'],
            'grant_types.*' => ['required', 'string', 'in:authorization_code,client_credentials,password,implicit,refresh_token'],
            'redirect_uris' => ['array'],
            'redirect_uris.*' => ['required', 'url'],
            'login_uri' => ['required', 'url', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $confidential = $this->boolean('confidential', true);
            $pkceEnabled = $this->boolean('pkce_enabled');

            if (! $confidential && ! $pkceEnabled) {
                $validator->errors()->add('pkce_enabled', 'Public clients must enable PKCE.');
            }

            if (! $pkceEnabled) {
                return;
            }

            $grantTypes = (array) $this->input('grant_types', []);

            if (! in_array('authorization_code', $grantTypes, true)) {
                $validator->errors()->add('grant_types', 'PKCE requires the authorization_code grant type.');
            }

            if (in_array('client_credentials', $grantTypes, true)) {
                $validator->errors()->add('grant_types', 'client_credentials cannot be used when PKCE is enabled.');
            }
        });
    }
}
