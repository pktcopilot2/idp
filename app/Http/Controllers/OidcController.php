<?php

namespace App\Http\Controllers;

use App\Models\User;
use DateTimeImmutable;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;

class OidcController extends Controller
{
    public function discovery(Request $request): JsonResponse
    {
        $issuer = $this->issuer();

        return response()->json([
            'issuer' => $issuer,
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('oidc.token'),
            'userinfo_endpoint' => route('oidc.userinfo'),
            'jwks_uri' => route('oidc.jwks'),
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'none'],
            'scopes_supported' => ['openid', 'profile', 'email', 'offline_access'],
            'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'auth_time', 'nonce', 'at_hash', 'name', 'preferred_username', 'email', 'email_verified'],
            'code_challenge_methods_supported' => ['S256', 'plain'],
        ]);
    }

    public function jwks(): JsonResponse
    {
        $details = $this->publicKeyDetails();

        return response()->json([
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => $this->keyId(),
                'n' => $this->base64UrlEncode($details['rsa']['n']),
                'e' => $this->base64UrlEncode($details['rsa']['e']),
            ]],
        ]);
    }

    public function token(
        Request $request,
        ServerRequestInterface $psrRequest,
        AccessTokenController $accessTokenController,
    ): JsonResponse|Response {
        $tokenResponse = $accessTokenController->issueToken($psrRequest, new Psr7Response());

        $payload = json_decode($tokenResponse->getContent(), true);

        if (! is_array($payload) || $tokenResponse->getStatusCode() >= 400 || ! isset($payload['access_token'])) {
            return $tokenResponse;
        }

        $scopes = $this->resolveGrantedScopes($payload, $request);

        if (! in_array('openid', $scopes, true)) {
            return response()->json($payload, $tokenResponse->getStatusCode());
        }

        $idToken = $this->buildIdToken(
            (string) $payload['access_token'],
            $scopes,
            $this->resolveNonce($request),
        );

        if ($idToken !== null) {
            $payload['id_token'] = $idToken;
        }

        return response()->json($payload, $tokenResponse->getStatusCode());
    }

    public function userinfo(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $claims = [
            'sub' => (string) $user->getAuthIdentifier(),
        ];

        // if ($user->tokenCan('profile')) {
            $claims['name'] = $user->name;
            $claims['preferred_username'] = $user->username;
        // }

        // if ($user->tokenCan('email')) {
            $claims['email'] = $user->email;
            $claims['email_verified'] = (bool) $user->email_verified_at;
        // }

        return response()->json($claims);
    }

    protected function buildIdToken(string $accessToken, array $scopes, ?string $nonce = null): ?string
    {
        $accessTokenPayload = $this->decodeJwtPayload($accessToken);
        $tokenId = isset($accessTokenPayload['jti']) ? (string) $accessTokenPayload['jti'] : null;

        if (! $tokenId) {
            return null;
        }

        $tokenModelClass = Passport::tokenModel();
        $token = $tokenModelClass::query()->find($tokenId);

        if (! $token || ! $token->user_id) {
            return null;
        }

        $user = User::query()->find($token->user_id);
        if (! $user) {
            return null;
        }

        $issuedAt = new DateTimeImmutable();
        $expiresAt = $issuedAt->modify('+'.(int) config('oidc.id_token_ttl', 600).' seconds');

        $jwtConfig = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->privateKey()),
            InMemory::plainText($this->publicKey()),
        );

        $clientId = (string) $token->client_id;

        $builder = $jwtConfig->builder()
            ->issuedBy($this->issuer())
            ->permittedFor($clientId)
            ->relatedTo((string) $user->getAuthIdentifier())
            ->identifiedBy(Str::uuid()->toString())
            ->issuedAt($issuedAt)
            ->canOnlyBeUsedAfter($issuedAt)
            ->expiresAt($expiresAt)
            ->withHeader('kid', $this->keyId())
            ->withClaim('auth_time', $issuedAt->getTimestamp())
            ->withClaim('at_hash', $this->atHash($accessToken));

        if ($nonce) {
            $builder = $builder->withClaim('nonce', $nonce);
        }

        if (in_array('profile', $scopes, true)) {
            $builder = $builder
                ->withClaim('name', $user->name)
                ->withClaim('preferred_username', $user->username);
        }

        if (in_array('email', $scopes, true)) {
            $builder = $builder
                ->withClaim('email', $user->email)
                ->withClaim('email_verified', (bool) $user->email_verified_at);
        }

        return $builder
            ->getToken($jwtConfig->signer(), $jwtConfig->signingKey())
            ->toString();
    }

    protected function atHash(string $accessToken): string
    {
        $hash = hash('sha256', $accessToken, true);

        return $this->base64UrlEncode(substr($hash, 0, strlen($hash) / 2));
    }

    protected function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) < 2) {
            return [];
        }

        $json = $this->base64UrlDecode($parts[1]);

        return is_string($json) ? (json_decode($json, true) ?: []) : [];
    }

    protected function issuer(): string
    {
        return rtrim((string) config('oidc.issuer', config('app.url')), '/');
    }

    protected function resolveNonce(Request $request): ?string
    {
        if ($request->filled('nonce')) {
            return (string) $request->input('nonce');
        }

        $authorizationCode = (string) $request->input('code', '');
        if ($authorizationCode === '') {
            return null;
        }

        $nonce = Cache::get('oidc_nonce:'.$authorizationCode);

        return is_string($nonce) && $nonce !== '' ? $nonce : null;
    }

    protected function resolveGrantedScopes(array $payload, Request $request): array
    {
        $scopes = [];

        if (isset($payload['scope']) && is_string($payload['scope'])) {
            $scopes = preg_split('/\s+/', trim($payload['scope'])) ?: [];
        } elseif (isset($payload['scope']) && is_array($payload['scope'])) {
            $scopes = $payload['scope'];
        }

        $scopes = array_values(array_filter(array_map('strval', $scopes)));

        if ($scopes !== []) {
            return array_values(array_unique($scopes));
        }

        // Passport may omit the `scope` field in token response for auth-code exchange.
        $accessTokenPayload = $this->decodeJwtPayload((string) ($payload['access_token'] ?? ''));
        $tokenScopes = $accessTokenPayload['scopes'] ?? [];

        if (is_string($tokenScopes)) {
            $tokenScopes = preg_split('/\s+/', trim($tokenScopes)) ?: [];
        }

        if (is_array($tokenScopes) && $tokenScopes !== []) {
            return array_values(array_unique(array_filter(array_map('strval', $tokenScopes))));
        }

        $requestScope = trim((string) $request->input('scope', ''));

        if ($requestScope !== '') {
            return array_values(array_unique(array_filter(preg_split('/\s+/', $requestScope) ?: [])));
        }

        return [];
    }

    protected function keyId(): string
    {
        $details = $this->publicKeyDetails();

        return rtrim(strtr(base64_encode(hash('sha256', $details['rsa']['n'].$details['rsa']['e'], true)), '+/', '-_'), '=');
    }

    protected function publicKeyDetails(): array
    {
        $publicKeyResource = openssl_pkey_get_public($this->publicKey());

        if (! $publicKeyResource) {
            abort(500, 'Unable to load Passport public key for OIDC JWKS.');
        }

        $details = openssl_pkey_get_details($publicKeyResource);

        if (! is_array($details) || ! isset($details['rsa']['n'], $details['rsa']['e'])) {
            abort(500, 'Unable to build JWKS from Passport public key.');
        }

        return $details;
    }

    protected function privateKey(): string
    {
        $configured = config('passport.private_key');

        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        return File::get(Passport::keyPath('oauth-private.key'));
    }

    protected function publicKey(): string
    {
        $configured = config('passport.public_key');

        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        return File::get(Passport::keyPath('oauth-public.key'));
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $value): string|false
    {
        $remainder = strlen($value) % 4;

        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
