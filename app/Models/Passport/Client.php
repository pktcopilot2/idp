<?php

namespace App\Models\Passport;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        return true;

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

    public function roles(): HasMany
    {
        return $this->hasMany(\Spatie\Permission\Models\Role::class, 'client_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(\Spatie\Permission\Models\Permission::class, 'client_id');
    }
}
