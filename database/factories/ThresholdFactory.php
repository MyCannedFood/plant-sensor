<?php

namespace Database\Factories;

use App\Enums\ThresholdParameter;
use App\Models\Device;
use App\Models\Threshold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Threshold>
 */
class ThresholdFactory extends Factory
{
    /**
     * Realistic bounds per parameter, so a generated threshold is always
     * meaningful for the parameter it is attached to.
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private const BOUNDS = [
        'co2' => [400, 1200],
        'temperature' => [16, 30],
        'humidity' => [35, 70],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $parameter = fake()->randomElement(ThresholdParameter::cases());
        [$minValue, $maxValue] = self::BOUNDS[$parameter->value];

        return [
            'device_id' => Device::factory(),
            'parameter' => $parameter,
            'min_value' => $minValue,
            'max_value' => $maxValue,
        ];
    }

    /**
     * Indicate that the threshold applies to every device as a fallback.
     */
    public function globalDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_id' => null,
        ]);
    }

    /**
     * Indicate which parameter the threshold guards, using the realistic bounds
     * for that parameter unless explicit values are given.
     */
    public function forParameter(ThresholdParameter $parameter, ?float $minValue = null, ?float $maxValue = null): static
    {
        [$defaultMin, $defaultMax] = self::BOUNDS[$parameter->value];

        return $this->state(fn (array $attributes) => [
            'parameter' => $parameter,
            'min_value' => $minValue ?? $defaultMin,
            'max_value' => $maxValue ?? $defaultMax,
        ]);
    }

    /**
     * Indicate that only the upper bound is enforced.
     */
    public function withoutMinimum(): static
    {
        return $this->state(fn (array $attributes) => [
            'min_value' => null,
        ]);
    }

    /**
     * Indicate that only the lower bound is enforced.
     */
    public function withoutMaximum(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_value' => null,
        ]);
    }
}
