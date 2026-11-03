<?php

namespace Database\Factories;

use App\Enums\AccessMethod;
use App\Enums\AccessRequestStatus;
use App\Models\GbpAccessRequest;
use App\Models\GbpClient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GbpAccessRequest>
 */
class GbpAccessRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gbp_client_id' => GbpClient::factory(),
            'access_method' => AccessMethod::Api,
            'location_resource_name' => 'locations/5550001',
            'requested_account' => 'accounts/999',
            'requested_role' => 'MANAGER',
            'status' => AccessRequestStatus::Submitted,
            'submitted_at' => now(),
        ];
    }

    public function withStatus(AccessRequestStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
