<?php

namespace Database\Factories;

use App\Enums\AccessMethod;
use App\Enums\ClientStatus;
use App\Models\GbpClient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GbpClient>
 */
class GbpClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_name' => fake()->company(),
            'client_name' => fake()->name(),
            'client_email' => fake()->unique()->safeEmail(),
            'access_status' => ClientStatus::New,
            'access_method' => AccessMethod::Api,
        ];
    }

    public function withStatus(ClientStatus $status): static
    {
        return $this->state(fn () => ['access_status' => $status]);
    }

    public function manual(): static
    {
        return $this->state(fn () => ['access_method' => AccessMethod::AgencyManual]);
    }

    public function withLocation(string $id = '5550001'): static
    {
        return $this->state(fn () => [
            'google_account_id' => 'accounts/100',
            'google_location_id' => $id,
            'location_resource_name' => 'locations/'.$id,
            'google_location_title' => 'Test Cafe',
            'google_location_address' => '1 Main St, Austin, TX 78701',
        ]);
    }
}
