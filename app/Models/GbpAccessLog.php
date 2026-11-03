<?php

namespace App\Models;

use App\Enums\ActorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['gbp_client_id', 'gbp_access_request_id', 'action', 'actor_type', 'description', 'metadata'])]
class GbpAccessLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(GbpClient::class, 'gbp_client_id');
    }
}
