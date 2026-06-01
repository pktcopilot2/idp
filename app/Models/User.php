<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'email_mfa_enabled', 'whatsapp_mfa_enabled', 'whatsapp_number', 'is_need_password_reset', 'failed_login_attempts', 'locked_at', 'active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasApiTokens, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'email_mfa_enabled' => 'boolean',
            'whatsapp_mfa_enabled' => 'boolean',
            'is_need_password_reset' => 'boolean',
            'locked_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    /**
     * Get the accessors to append to the model's array form.
     *
     * @return array<int, string>
     */
    protected $appends = [
        'aliases',
    ];

    // get from ldaphelper
    public function getAliasesAttribute(): array
    {
        if (config('app.ldap.enabled') === false) {
            return [];
        }

        $ldap = new \App\Helpers\LdapHelper();
        return $ldap->getUserAliasesOrNull($this->username);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function assignedClients(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\Passport\Client::class,
            'client_user',
            'user_id',
            'client_id',
        );
    }
}
