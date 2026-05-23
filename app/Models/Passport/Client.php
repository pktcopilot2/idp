<?php

namespace App\Models\Passport;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Passport\Client as BaseClient;

class Client extends BaseClient
{
    /**
     * Determine if the client should skip the authorization prompt.
     *
     * @param  \Laravel\Passport\Scope[]  $scopes
     */
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return $this->assignedUsers()
            ->where('users.id', $user->getAuthIdentifier())
            ->exists();
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'client_user',
            'client_id',
            'user_id',
        );
    }
}
