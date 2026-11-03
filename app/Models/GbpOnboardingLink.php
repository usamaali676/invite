<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

#[Fillable([
    'gbp_client_id', 'token_hash', 'token_encrypted', 'expires_at',
    'opened_at', 'used_at', 'revoked_at', 'created_by',
])]
class GbpOnboardingLink extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'opened_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(GbpClient::class, 'gbp_client_id');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Create a fresh link and revoke every earlier one for the client.
     * The URL token itself is stored only as a SHA-256 hash (used for lookups)
     * and as an application-key-encrypted copy so staff can copy the link again.
     */
    public static function issue(GbpClient $client, ?User $by = null): self
    {
        $client->onboardingLinks()->whereNull('revoked_at')->update(['revoked_at' => now()]);

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return $client->onboardingLinks()->create([
            'token_hash' => static::hashToken($token),
            'token_encrypted' => Crypt::encryptString($token),
            'expires_at' => now()->addDays(config('gbp.link_ttl_days')),
            'created_by' => $by?->id,
        ]);
    }

    public static function findByToken(string $token): ?self
    {
        if (strlen($token) < 20 || strlen($token) > 128) {
            return null;
        }

        return static::query()->where('token_hash', static::hashToken($token))->first();
    }

    public function plainToken(): ?string
    {
        if (blank($this->token_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->token_encrypted);
        } catch (DecryptException) {
            return null;
        }
    }

    public function url(): ?string
    {
        $token = $this->plainToken();

        return $token ? route('onboarding.show', ['token' => $token]) : null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }
}
