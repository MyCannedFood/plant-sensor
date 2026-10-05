<?php

namespace Database\Factories;

use App\Enums\AlertDirection;
use App\Enums\AlertSeverity;
use App\Enums\ThresholdParameter;
use App\Models\Alert;
use App\Models\Reading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reading_id' => Reading::factory(),
            'device_id' => fn (array $attributes) => Reading::query()
                ->findOrFail($attributes['reading_id'])
                ->device_id,
            'threshold_id' => null,
            'parameter' => ThresholdParameter::Co2,
            'direction' => AlertDirection::AboveMaximum,
            'severity' => AlertSeverity::Warning,
            'value' => 1500,
            'triggered_at' => now(),
            'resolved_at' => null,
        ];
    }

    /**
     * Indicate that the value fell below the configured minimum.
     */
    public function belowMinimum(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => AlertDirection::BelowMinimum,
        ]);
    }

    /**
     * Indicate that the value rose above the configured maximum.
     */
    public function aboveMaximum(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => AlertDirection::AboveMaximum,
        ]);
    }

    /**
     * Indicate that the breach has been rated as critical.
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => AlertSeverity::Critical,
        ]);
    }

    /**
     * Indicate that the value has returned to its acceptable range.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'resolved_at' => now(),
        ]);
    }
}
