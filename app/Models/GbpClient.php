<?php

namespace App\Models;

use App\Enums\AccessMethod;
use App\Enums\ActorType;
use App\Enums\ClientStatus;
use App\Enums\ConnectionStatus;
use App\Enums\ConnectionType;
use App\Exceptions\InvalidStatusTransition;
use Database\Factories\GbpClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'business_name', 'client_name', 'client_email', 'google_maps_url',
    'google_account_id', 'google_location_id', 'location_resource_name',
    'google_location_title', 'google_location_address',
    'access_status', 'access_method', 'internal_notes', 'created_by',
])]
class GbpClient extends Model
{
    /** @use HasFactory<GbpClientFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'access_status' => 'NEW',
        'access_method' => 'API',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_status' => ClientStatus::class,
            'access_method' => AccessMethod::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function onboardingLinks(): HasMany
    {
        return $this->hasMany(GbpOnboardingLink::class);
    }

    public function accessRequests(): HasMany
    {
        return $this->hasMany(GbpAccessRequest::class);
    }

    public function latestAccessRequest(): HasOne
    {
        return $this->hasOne(GbpAccessRequest::class)->latestOfMany();
    }

    public function googleConnections(): HasMany
    {
        return $this->hasMany(GbpGoogleConnection::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(GbpAccessLog::class)->latest('id');
    }

    /** The most recent active client-side Google authorization, if any. */
    public function activeClientConnection(): ?GbpGoogleConnection
    {
        return $this->googleConnections()
            ->where('connection_type', ConnectionType::Client->value)
            ->where('status', ConnectionStatus::Active->value)
            ->latest('id')
            ->first();
    }

    public function activeLink(): ?GbpOnboardingLink
    {
        return $this->onboardingLinks()
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();
    }

    /**
     * Move to a new status, enforcing the state machine.
     *
     * @throws InvalidStatusTransition
     */
    public function transitionTo(ClientStatus $next): bool
    {
        $current = $this->access_status;

        if ($current === $next) {
            return false;
        }

        if (! $current->canTransitionTo($next)) {
            throw new InvalidStatusTransition($current, $next);
        }

        $this->forceFill(['access_status' => $next])->save();

        return true;
    }

    /** Like transitionTo(), but silently ignores a move the state machine forbids. */
    public function tryTransitionTo(ClientStatus $next): bool
    {
        try {
            return $this->transitionTo($next);
        } catch (InvalidStatusTransition) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function logEvent(
        string $action,
        ActorType $actor,
        ?string $description = null,
        array $metadata = [],
        ?GbpAccessRequest $request = null,
    ): GbpAccessLog {
        return $this->logs()->create([
            'gbp_access_request_id' => $request?->id,
            'action' => $action,
            'actor_type' => $actor->value,
            'description' => $description,
            'metadata' => $metadata ?: null,
        ]);
    }

    public function hasSelectedLocation(): bool
    {
        return filled($this->location_resource_name);
    }
}
