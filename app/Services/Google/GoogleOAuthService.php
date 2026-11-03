<?php

namespace App\Services\Google;

use App\Enums\ConnectionType;
use App\Enums\GoogleErrorType;
use App\Exceptions\GoogleApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Google OAuth 2.0 authorization-code flow, executed entirely on the server.
 * Client secrets and tokens never reach the browser.
 */
class GoogleOAuthService
{
    private const SESSION_KEY = 'gbp.oauth';

    public function isConfigured(): bool
    {
        return filled(config('gbp.google.client_id')) && filled(config('gbp.google.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('gbp.google.redirect_uri') ?: route('google.callback');
    }

    public function scope(): string
    {
        return config('gbp.google.scope');
    }

    /**
     * Create and remember a single-use state value, bound to this browser session.
     *
     * @param  array<string, mixed>  $context  e.g. type, client_id, link_id, return_to
     */
    public function beginAuthorization(Request $request, ConnectionType $type, array $context = []): string
    {
        $state = Str::random(48);

        $request->session()->put(self::SESSION_KEY, [
            'state_hash' => hash('sha256', $state),
            'type' => $type->value,
            'context' => $context,
            'expires_at' => now()->addMinutes((int) config('gbp.google.state_ttl_minutes'))->timestamp,
        ]);

        return $this->authorizationUrl($state);
    }

    /**
     * Validate the returned state and consume it. Returns the stored flow data or null.
     *
     * @return array{type: ConnectionType, context: array<string, mixed>}|null
     */
    public function consumeState(Request $request, ?string $state): ?array
    {
        $stored = $request->session()->pull(self::SESSION_KEY);

        if (! is_array($stored) || ! is_string($state) || $state === '') {
            return null;
        }

        if (($stored['expires_at'] ?? 0) < now()->timestamp) {
            return null;
        }

        if (! hash_equals((string) ($stored['state_hash'] ?? ''), hash('sha256', $state))) {
            return null;
        }

        $type = ConnectionType::tryFrom((string) ($stored['type'] ?? ''));

        if ($type === null) {
            return null;
        }

        return ['type' => $type, 'context' => (array) ($stored['context'] ?? [])];
    }

    public function authorizationUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => config('gbp.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => $this->scope(),
            'state' => $state,
            // Offline access is needed because status verification and failed-request
            // retries run later, without the client being present.
            'access_type' => 'offline',
            'include_granted_scopes' => 'false',
            // Lets the person pick the right Google account and always returns a refresh token.
            'prompt' => 'select_account consent',
        ], '', '&', PHP_QUERY_RFC3986);

        return config('gbp.google.auth_url').'?'.$query;
    }

    /**
     * Exchange an authorization code for tokens.
     *
     * @return array<string, mixed>
     *
     * @throws GoogleApiException
     */
    public function exchangeCode(string $code): array
    {
        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ]);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GoogleApiException
     */
    public function refresh(string $refreshToken): array
    {
        return $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    /** Best effort. Returns true when Google confirmed the revocation. */
    public function revoke(string $token): bool
    {
        try {
            return Http::asForm()
                ->timeout((int) config('gbp.google.http_timeout'))
                ->post(config('gbp.google.revoke_url'), ['token' => $token])
                ->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * @param  array<string, string>  $params
     * @return array<string, mixed>
     */
    private function tokenRequest(array $params): array
    {
        if (! $this->isConfigured()) {
            throw new GoogleApiException(GoogleErrorType::NotConfigured);
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('gbp.google.http_timeout'))
                ->post(config('gbp.google.token_url'), $params + [
                    'client_id' => config('gbp.google.client_id'),
                    'client_secret' => config('gbp.google.client_secret'),
                ]);
        } catch (ConnectionException) {
            throw new GoogleApiException(GoogleErrorType::Timeout);
        }

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $error = (string) $response->json('error', 'unknown');

        Log::warning('Google token request failed', [
            'grant_type' => $params['grant_type'] ?? null,
            'http_status' => $response->status(),
            'error' => $error,
        ]);

        $type = match ($error) {
            'invalid_grant' => GoogleErrorType::TokenRevoked,
            'invalid_client', 'unauthorized_client' => GoogleErrorType::NotConfigured,
            default => GoogleErrorType::Unknown,
        };

        throw new GoogleApiException($type, $response->status(), $error);
    }
}
