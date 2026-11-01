<?php

namespace Database\Factories;

use App\Models\Plant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plant>
 */
class PlantFactory extends Factory
{
    /**
     * Houseplant species the factory picks from.
     *
     * @var list<string>
     */
    private const SPECIES = [
        'Monstera deliciosa',
        'Epipremnum aureum',
        'Ficus lyrata',
        'Sansevieria trifasciata',
        'Calathea orbifolia',
        'Nephrolepis exaltata',
        'Euphorbia trigona',
    ];

    /**
     * Rooms a plant can be kept in.
     *
     * @var list<string>
     */
    private const LOCATIONS = [
        'Living Room',
        'Bedroom',
        'Kitchen',
        'Study',
        'Bathroom',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'species' => fake()->randomElement(self::SPECIES),
            'location' => fake()->randomElement(self::LOCATIONS),
            'notes' => fake()->optional(0.5)->sentence(),
        ];
    }
}
