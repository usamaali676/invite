<?php

namespace App\Models;

use App\Enums\ConnectionStatus;
use App\Enums\ConnectionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'gbp_client_id', 'connection_type', 'google_email', 'google_account_id', 'google_account_name',
    'access_token', 'refresh_token', 'expires_at', 'scopes', 'status',
])]
#[Hidden(['access_token', 'refresh_token'])]
class GbpGoogleConnection extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'connection_type' => ConnectionType::class,
            'status' => ConnectionStatus::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'scopes' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(GbpClient::class, 'gbp_client_id');
    }

    public function isActive(): bool
    {
        return $this->status === ConnectionStatus::Active;
    }

    public function accessTokenIsFresh(): bool
    {
        return filled($this->access_token)
            && $this->expires_at !== null
            && $this->expires_at->isAfter(now()->addSeconds(60));
    }

    public static function activeAgency(): ?self
    {
        return static::query()
            ->where('connection_type', ConnectionType::Agency->value)
            ->where('status', ConnectionStatus::Active->value)
            ->latest('id')
            ->first();
    }
}
