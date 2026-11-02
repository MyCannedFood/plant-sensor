<?php

namespace Database\Factories;

use App\Enums\ThresholdParameter;
use App\Models\Device;
use App\Models\DeviceSensor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceSensor>
 */
class DeviceSensorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'parameter' => fake()->randomElement(ThresholdParameter::cases()),
        ];
    }

    /**
     * Indicate which parameter the device reports.
     *
     * Declaring several parameters on one device must go through this rather
     * than leaving the choice to chance: (device_id, parameter) is unique, so
     * independent random picks collide.
     */
    public function forParameter(ThresholdParameter $parameter): static
    {
        return $this->state(fn (array $attributes) => [
            'parameter' => $parameter,
        ]);
    }
}
