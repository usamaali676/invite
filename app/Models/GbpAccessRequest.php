<?php

namespace App\Models;

use App\Enums\AccessMethod;
use App\Enums\AccessRequestStatus;
use Database\Factories\GbpAccessRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'gbp_client_id', 'access_method', 'google_connection_id', 'location_resource_name',
    'requested_account', 'invitation_resource_name', 'requested_role', 'status',
    'submitted_at', 'approved_at', 'last_checked_at', 'error_code', 'error_message',
])]
class GbpAccessRequest extends Model
{
    /** @use HasFactory<GbpAccessRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_method' => AccessMethod::class,
            'status' => AccessRequestStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(GbpClient::class, 'gbp_client_id');
    }

    public function googleConnection(): BelongsTo
    {
        return $this->belongsTo(GbpGoogleConnection::class, 'google_connection_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(GbpAccessLog::class);
    }
}
