<?php

namespace App\Bridge;

use App\Models\User;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Laravel\Passport\Bridge\AccessToken as BaseAccessToken;
use League\OAuth2\Server\CryptKeyInterface;
use Spatie\Permission\PermissionRegistrar;

class AccessToken extends BaseAccessToken
{
    private CryptKeyInterface $cryptKey;

    /**
     * Intercept the private key so we can use it when building the JWT.
     */
    public function setPrivateKey(CryptKeyInterface $privateKey): void
    {
        $this->cryptKey = $privateKey;
        parent::setPrivateKey($privateKey);
    }

    /**
     * Build a JWT string that includes the user's client-scoped roles and permissions.
     */
    public function toString(): string
    {
        $jwtConfig = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText(
                $this->cryptKey->getKeyContents(),
                $this->cryptKey->getPassPhrase() ?? ''
            ),
            InMemory::plainText('empty', 'empty')
        );

        $userId   = $this->getUserIdentifier();
        $clientId = $this->getClient()->getIdentifier();
        $user     = $userId ? User::find($userId) : null;

        $builder = $jwtConfig->builder()
            ->permittedFor($clientId)
            ->identifiedBy($this->getIdentifier())
            ->issuedAt(new DateTimeImmutable())
            ->canOnlyBeUsedAfter(new DateTimeImmutable())
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo($userId ?? $clientId)
            ->withClaim('scopes', $this->getScopes());

        if ($user) {
            // Scope roles/permissions to the requesting client only.
            $registrar = app(PermissionRegistrar::class);
            $previousTeamId = $registrar->getPermissionsTeamId();

            $registrar->setPermissionsTeamId($clientId);
            $roles       = $user->getRoleNames()->values()->toArray();
            $permissions = $user->getAllPermissions()->pluck('name')->values()->toArray();
            $registrar->setPermissionsTeamId($previousTeamId);

            $builder = $builder
                ->withClaim('roles', $roles)
                ->withClaim('permissions', $permissions);
        }

        return $builder
            ->getToken($jwtConfig->signer(), $jwtConfig->signingKey())
            ->toString();
    }
}

