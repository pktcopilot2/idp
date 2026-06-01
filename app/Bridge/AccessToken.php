<?php

namespace App\Bridge;

use App\Models\User;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Laravel\Passport\Bridge\AccessToken as BaseAccessToken;
use League\OAuth2\Server\CryptKeyInterface;

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
     * Build a JWT string that includes the user's roles and permissions as claims.
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

        $userId = $this->getUserIdentifier();
        $user   = $userId ? User::find($userId) : null;

        $builder = $jwtConfig->builder()
            ->permittedFor($this->getClient()->getIdentifier())
            ->identifiedBy($this->getIdentifier())
            ->issuedAt(new DateTimeImmutable())
            ->canOnlyBeUsedAfter(new DateTimeImmutable())
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo($userId ?? $this->getClient()->getIdentifier())
            ->withClaim('scopes', $this->getScopes());

        if ($user) {
            $builder = $builder
                ->withClaim('roles', $user->getRoleNames()->values()->toArray())
                ->withClaim('permissions', $user->getAllPermissions()->pluck('name')->values()->toArray());
        }

        return $builder
            ->getToken($jwtConfig->signer(), $jwtConfig->signingKey())
            ->toString();
    }
}
