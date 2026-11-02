<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Reading;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Reading>
 */
class ReadingFactory extends Factory
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
            'measured_at' => now(),
            // The device's own clock and the moment this server accepted the
            // reading are separate questions, so the factory answers them
            // separately. A small positive gap is what a real board produces
            // between measuring and the request landing.
            'received_at' => fn (array $attributes) => isset($attributes['measured_at'])
                ? Carbon::parse($attributes['measured_at'])->addSeconds(fake()->numberBetween(1, 20))
                : now(),
            'co2' => fake()->numberBetween(450, 1200),
            'temperature' => fake()->randomFloat(2, 18, 28),
            'humidity' => fake()->randomFloat(1, 40, 70),
        ];
    }

    /**
     * Indicate that every measured value sits outside a typical indoor range.
     */
    public function outOfRange(): static
    {
        return $this->state(fn (array $attributes) => [
            'co2' => fake()->numberBetween(1500, 3000),
            'temperature' => fake()->randomFloat(2, 35, 45),
            'humidity' => fake()->numberBetween(5, 20),
        ]);
    }

    /**
     * Indicate that only the CO2 level breaches its maximum.
     */
    public function withHighCo2(): static
    {
        return $this->state(fn (array $attributes) => [
            'co2' => fake()->numberBetween(1500, 3000),
        ]);
    }

    /**
     * Indicate that only the CO2 level breaches its minimum.
     */
    public function withLowCo2(): static
    {
        return $this->state(fn (array $attributes) => [
            'co2' => fake()->numberBetween(100, 350),
        ]);
    }

    /**
     * Indicate that only the temperature breaches its maximum.
     */
    public function withHighTemperature(): static
    {
        return $this->state(fn (array $attributes) => [
            'temperature' => fake()->randomFloat(2, 35, 45),
        ]);
    }

    /**
     * Indicate that only the humidity breaches its minimum.
     */
    public function withLowHumidity(): static
    {
        return $this->state(fn (array $attributes) => [
            'humidity' => fake()->numberBetween(5, 20),
        ]);
    }
}
