<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plant_id' => Plant::factory(),
            'name' => 'Sensor '.fake()->unique()->numberBetween(1, 999999),
            'serial_number' => Str::upper(Str::random(12)),
            'api_token' => hash('sha256', Str::random(40)),
            'firmware_version' => fake()->semver(),
            'status' => DeviceStatus::Active,
            'last_seen_at' => now(),
        ];
    }

    /**
     * Indicate that the device has been taken out of service.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeviceStatus::Inactive,
        ]);
    }

    /**
     * Indicate that the device is currently being serviced.
     */
    public function inMaintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeviceStatus::Maintenance,
        ]);
    }

    /**
     * Indicate that the device has stopped reporting.
     */
    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_seen_at' => now()->subDay(),
        ]);
    }
}
