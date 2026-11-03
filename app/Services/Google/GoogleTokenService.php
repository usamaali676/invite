<?php

namespace App\Services\Google;

use App\Enums\ConnectionStatus;
use App\Enums\ConnectionType;
use App\Enums\GoogleErrorType;
use App\Exceptions\GoogleApiException;
use App\Models\GbpClient;
use App\Models\GbpGoogleConnection;

/**
 * Stores, refreshes and revokes OAuth tokens. Each connection is kept separate:
 * agency tokens and client tokens are never interchangeable.
 */
class GoogleTokenService
{
    public function __construct(private GoogleOAuthService $oauth) {}

    /**
     * Persist the result of a code exchange as a new connection.
     * Earlier active connections of the same owner are retired.
     *
     * @param  array<string, mixed>  $tokens
     */
    public function storeConnection(ConnectionType $type, ?GbpClient $client, array $tokens): GbpGoogleConnection
    {
        $query = GbpGoogleConnection::query()
            ->where('connection_type', $type->value)
            ->where('status', ConnectionStatus::Active->value);

        $type === ConnectionType::Client
            ? $query->where('gbp_client_id', $client?->id)
            : $query->whereNull('gbp_client_id');

        $previous = $query->get();

        $connection = GbpGoogleConnection::create([
            'gbp_client_id' => $type === ConnectionType::Client ? $client?->id : null,
            'connection_type' => $type,
            'access_token' => $tokens['access_token'] ?? null,
            'refresh_token' => $tokens['refresh_token'] ?? $previous->first()?->refresh_token,
            'expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3500)),
            'scopes' => isset($tokens['scope']) ? explode(' ', (string) $tokens['scope']) : [$this->oauth->scope()],
            'status' => ConnectionStatus::Active,
        ]);

        $previous->each(fn (GbpGoogleConnection $old) => $old->forceFill([
            'status' => ConnectionStatus::Revoked,
            'access_token' => null,
            'refresh_token' => null,
        ])->save());

        return $connection;
    }

    /**
     * Return a usable access token, refreshing it when needed.
     *
     * @throws GoogleApiException
     */
    public function validAccessToken(GbpGoogleConnection $connection): string
    {
        if (! $connection->isActive()) {
            throw new GoogleApiException(GoogleErrorType::TokenRevoked);
        }

        if ($connection->accessTokenIsFresh()) {
            return $connection->access_token;
        }

        if (blank($connection->refresh_token)) {
            $this->markExpired($connection);

            throw new GoogleApiException(GoogleErrorType::TokenRevoked);
        }

        try {
            $tokens = $this->oauth->refresh($connection->refresh_token);
        } catch (GoogleApiException $e) {
            if ($e->type === GoogleErrorType::TokenRevoked) {
                $this->markExpired($connection);
            }

            throw $e;
        }

        $connection->forceFill([
            'access_token' => $tokens['access_token'] ?? null,
            'expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3500)),
            'refresh_token' => $tokens['refresh_token'] ?? $connection->refresh_token,
        ])->save();

        return $connection->access_token;
    }

    /** Mark a connection as no longer usable, e.g. after Google reported 401 or invalid_grant. */
    public function markExpired(GbpGoogleConnection $connection): void
    {
        $connection->forceFill([
            'status' => ConnectionStatus::Expired,
            'access_token' => null,
        ])->save();
    }

    /** Revoke at Google (best effort) and wipe the stored credentials. */
    public function disconnect(GbpGoogleConnection $connection): void
    {
        $token = $connection->refresh_token ?: $connection->access_token;

        if (filled($token)) {
            $this->oauth->revoke($token);
        }

        $connection->forceFill([
            'status' => ConnectionStatus::Revoked,
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
        ])->save();
    }
}
