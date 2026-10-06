<?php

namespace Database\Seeders;

use App\Enums\AlertDirection;
use App\Enums\AlertSeverity;
use App\Enums\ThresholdParameter;
use App\Models\Alert;
use App\Models\Device;
use App\Models\Plant;
use App\Models\Reading;
use App\Models\Threshold;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * @phpstan-type PlantDefinition array{
 *     label: string,
 *     species: string,
 *     location: string,
 *     notes: string,
 *     conditions: array{co2: float, temperature: float, humidity: float},
 *     thresholds: array<string, array{0: float, 1: float}>
 * }
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Minutes between two consecutive readings.
     */
    private const SAMPLE_INTERVAL_MINUTES = 10;

    /**
     * Readings seeded per device, covering seven days at the interval above.
     */
    private const READINGS_PER_DEVICE = 1008;

    /**
     * Fallback thresholds applied to any parameter a device has not overridden.
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private const GLOBAL_THRESHOLDS = [
        'co2' => [400, 1200],
        'temperature' => [16, 30],
        'humidity' => [35, 75],
    ];

    /**
     * The plants to seed, each with the conditions it thrives in and the
     * thresholds that override the global defaults. A parameter missing from
     * `thresholds` deliberately falls back to the global default.
     *
     * @var list<PlantDefinition>
     */
    private const PLANTS = [
        [
            'label' => 'Monstera',
            'species' => 'Monstera deliciosa',
            'location' => 'Living Room',
            'notes' => 'Leaves started curling, check humidity twice a week.',
            'conditions' => ['co2' => 780, 'temperature' => 24.5, 'humidity' => 76],
            'thresholds' => ['humidity' => [60, 90]],
        ],
        [
            'label' => 'Cactus',
            'species' => 'Euphorbia trigona',
            'location' => 'Kitchen',
            'notes' => 'Water once a month in winter, never let it sit in water.',
            'conditions' => ['co2' => 640, 'temperature' => 29, 'humidity' => 32],
            'thresholds' => ['temperature' => [20, 35], 'humidity' => [20, 45]],
        ],
        [
            'label' => 'Fiddle Leaf Fig',
            'species' => 'Ficus lyrata',
            'location' => 'Study',
            'notes' => 'Hates being moved. Rotate a quarter turn each month.',
            'conditions' => ['co2' => 720, 'temperature' => 23, 'humidity' => 61],
            'thresholds' => ['co2' => [400, 1100], 'humidity' => [50, 75]],
        ],
        [
            'label' => 'Calathea',
            'species' => 'Calathea orbifolia',
            'location' => 'Bedroom',
            'notes' => 'Brown edges usually mean dry air, not thirst.',
            'conditions' => ['co2' => 690, 'temperature' => 22, 'humidity' => 68],
            'thresholds' => ['humidity' => [60, 80]],
        ],
        [
            'label' => 'Boston Fern',
            'species' => 'Nephrolepis exaltata',
            'location' => 'Bathroom',
            'notes' => 'Mist daily while the heating is on.',
            'conditions' => ['co2' => 810, 'temperature' => 21, 'humidity' => 81],
            'thresholds' => ['humidity' => [70, 95]],
        ],
    ];

    /**
     * Incidents replayed on top of the generated history so the dashboard has
     * alerts to show before the evaluation service exists.
     *
     * @var list<array{plant: int, parameter: string, direction: string, value: float, severity: string, minutesAgo: int, resolved: bool}>
     */
    private const INCIDENTS = [
        ['plant' => 4, 'parameter' => 'humidity', 'direction' => 'below_min', 'value' => 48, 'severity' => 'critical', 'minutesAgo' => 25, 'resolved' => false],
        ['plant' => 0, 'parameter' => 'co2', 'direction' => 'above_max', 'value' => 1640, 'severity' => 'warning', 'minutesAgo' => 90, 'resolved' => false],
        ['plant' => 2, 'parameter' => 'temperature', 'direction' => 'above_max', 'value' => 33.4, 'severity' => 'warning', 'minutesAgo' => 400, 'resolved' => true],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password'],
        );

        $this->seedGlobalThresholds();

        $devices = [];

        foreach (self::PLANTS as $index => $definition) {
            $plant = Plant::query()->firstOrCreate(
                ['user_id' => $user->id, 'name' => $definition['label']],
                ['species' => $definition['species'], 'location' => $definition['location'], 'notes' => $definition['notes']],
            );

            $device = Device::query()->firstOrCreate(
                ['plant_id' => $plant->id],
                ['name' => "{$definition['label']} Sensor", 'serial_number' => strtoupper(Str::random(12)), 'api_token' => hash('sha256', Str::random(40))],
            );

            $this->seedThresholds($device, $definition['thresholds']);
            $this->seedReadings($device, $definition['conditions']);

            $devices[$index] = ['device' => $device, 'conditions' => $definition['conditions']];
        }

        $this->seedIncidents($devices);
    }

    /**
     * Seed the thresholds that apply to every device.
     */
    private function seedGlobalThresholds(): void
    {
        foreach (self::GLOBAL_THRESHOLDS as $parameter => [$minValue, $maxValue]) {
            Threshold::query()->firstOrCreate(
                ['device_id' => null, 'parameter' => $parameter],
                ['min_value' => $minValue, 'max_value' => $maxValue],
            );
        }
    }

    /**
     * Seed the thresholds a device overrides for the given parameters.
     *
     * @param  array<string, array{0: float, 1: float}>  $thresholds
     */
    private function seedThresholds(Device $device, array $thresholds): void
    {
        foreach ($thresholds as $parameter => [$minValue, $maxValue]) {
            Threshold::query()->firstOrCreate(
                ['device_id' => $device->id, 'parameter' => $parameter],
                ['min_value' => $minValue, 'max_value' => $maxValue],
            );
        }
    }

    /**
     * Seed a week of readings that drift around the conditions a plant likes.
     *
     * @param  array{co2: float, temperature: float, humidity: float}  $conditions
     */
    private function seedReadings(Device $device, array $conditions): void
    {
        if ($device->readings()->exists()) {
            return;
        }

        Reading::factory()
            ->for($device)
            ->count(self::READINGS_PER_DEVICE)
            ->sequence(function (Sequence $sequence) use ($conditions) {
                return [
                    'measured_at' => now()->subMinutes(self::SAMPLE_INTERVAL_MINUTES * (self::READINGS_PER_DEVICE - $sequence->index)),
                    'co2' => (int) round($conditions['co2'] + fake()->numberBetween(-45, 45)),
                    'temperature' => round($conditions['temperature'] + fake()->randomFloat(2, -1.4, 1.4), 2),
                    'humidity' => (int) round($conditions['humidity'] + fake()->numberBetween(-5, 5)),
                ];
            })
            ->create();
    }

    /**
     * Seed out-of-range readings together with the alerts they explain.
     *
     * @param  array<int, array{device: Device, conditions: array{co2: float, temperature: float, humidity: float}}>  $devices
     */
    private function seedIncidents(array $devices): void
    {
        foreach (self::INCIDENTS as $incident) {
            ['device' => $device, 'conditions' => $conditions] = $devices[$incident['plant']];

            $parameter = ThresholdParameter::from($incident['parameter']);

            if (Alert::query()
                ->where('device_id', $device->id)
                ->where('parameter', $parameter)
                ->exists()) {
                continue;
            }

            $measuredAt = now()->subMinutes($incident['minutesAgo']);

            $reading = Reading::query()->create([
                'device_id' => $device->id,
                'measured_at' => $measuredAt,
                // An incident was measured minutes ago and is being replayed
                // now, so the two timestamps deliberately differ. That is the
                // shape of a device uploading a backlog after losing WiFi.
                'received_at' => now(),
                'co2' => $parameter === ThresholdParameter::Co2 ? $incident['value'] : $conditions['co2'],
                'temperature' => $parameter === ThresholdParameter::Temperature ? $incident['value'] : $conditions['temperature'],
                'humidity' => $parameter === ThresholdParameter::Humidity ? $incident['value'] : $conditions['humidity'],
            ]);

            Alert::query()->create([
                'reading_id' => $reading->id,
                'device_id' => $device->id,
                'threshold_id' => Threshold::query()->applicableTo($device)->where('parameter', $parameter)->value('id'),
                'parameter' => $parameter,
                'direction' => AlertDirection::from($incident['direction']),
                'severity' => AlertSeverity::from($incident['severity']),
                'value' => $incident['value'],
                'triggered_at' => $measuredAt,
                'resolved_at' => $incident['resolved'] ? $measuredAt->copy()->addMinutes(30) : null,
            ]);
        }
    }
}
