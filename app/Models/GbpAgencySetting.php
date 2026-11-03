<?php

namespace App\Models;

use App\Enums\ApiApprovalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'organization_id', 'organization_name', 'location_group_account_id',
    'manager_email', 'default_role', 'api_project_number', 'api_approval_status',
])]
class GbpAgencySetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_approval_status' => ApiApprovalStatus::class,
        ];
    }

    /** The application keeps exactly one settings row. */
    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([
            'default_role' => 'MANAGER',
            'api_approval_status' => ApiApprovalStatus::NotRequested->value,
        ]);
    }

    /** Resource name used as the "account" of the invitation, e.g. accounts/123. */
    public function locationGroupResourceName(): ?string
    {
        $id = trim((string) $this->location_group_account_id);

        if ($id === '') {
            return null;
        }

        return str_starts_with($id, 'accounts/') ? $id : 'accounts/'.$id;
    }

    public function hasLocationGroup(): bool
    {
        return $this->locationGroupResourceName() !== null;
    }

    public function requestedRole(): string
    {
        return 'MANAGER';
    }
}
