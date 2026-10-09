<?php

namespace Database\Seeders;

use App\Enums\AlertDirection;
use App\Enums\AlertSeverity;
use App\Enums\ThresholdParameter;
use App\Models\Alert;
use App\Models\Device;
use App\Models\DeviceSensor;
use App\Models\Plant;
use App\Models\Reading;
use App\Models\Threshold;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
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
     * The sensors each device carries, keyed by plant label. A node reports a
     * parameter only when it physically has the sensor for it: a DHT22 covers
     * temperature and humidity, an MH-Z19C covers co2, and the two may be
     * mixed across nodes. Readings stop at the declared set, which is what
     * makes a null measurement mean "no sensor" instead of a gap.
     *
     * @var array<string, list<ThresholdParameter>>
     */
    private const DEVICE_SENSORS = [
        'Monstera' => [ThresholdParameter::Co2, ThresholdParameter::Temperature, ThresholdParameter::Humidity],
        'Cactus' => [ThresholdParameter::Temperature, ThresholdParameter::Humidity],
        'Fiddle Leaf Fig' => [ThresholdParameter::Temperature, ThresholdParameter::Humidity],
        'Calathea' => [ThresholdParameter::Co2],
        'Boston Fern' => [ThresholdParameter::Co2, ThresholdParameter::Temperature, ThresholdParameter::Humidity],
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
        $tokens = [];

        foreach (self::PLANTS as $index => $definition) {
            $plant = Plant::query()->firstOrCreate(
                ['user_id' => $user->id, 'name' => $definition['label']],
                ['species' => $definition['species'], 'location' => $definition['location'], 'notes' => $definition['notes']],
            );

            // Hash at rest: the database stores sha256(token), never the
            // plaintext. generateToken() returns the 40-character secret
            // exactly once; this run keeps it for the local token file the
            // simulator reads, so the plaintext never sits in the database.
            // The api_token value here is only a NOT NULL placeholder for the
            // first insert; generateToken() replaces it with a real hash.
            $device = Device::query()->firstOrCreate(
                ['plant_id' => $plant->id],
                ['name' => "{$definition['label']} Sensor", 'serial_number' => strtoupper(Str::random(12)), 'api_token' => hash('sha256', Str::random(40))],
            );

            $token = $device->generateToken();

            $this->seedThresholds($device, $definition['thresholds']);

            $parameters = self::DEVICE_SENSORS[$definition['label']];

            $this->seedDeviceSensors($device, $parameters);
            $this->seedReadings($device, $definition['conditions'], $parameters);

            $devices[$index] = ['device' => $device, 'conditions' => $definition['conditions'], 'parameters' => $parameters];

            $tokens[$definition['label']] = [
                'device_id' => $device->id,
                'serial_number' => $device->serial_number,
                'token' => $token,
            ];
        }

        $this->seedIncidents($devices);
        $this->writeDeviceTokens($tokens);
    }

    /**
     * Write the plaintext device tokens to a local file for the simulator.
     *
     * storage/app/device-tokens.json is gitignored: it exists so the artisan
     * simulator (and firmware during development) can authenticate without a
     * token surviving in the database. The file is intentionally written
     * last, after every seeding step has had a chance to fail.
     *
     * @param  array<string, array{device_id: int, serial_number: string, token: string}>  $tokens
     */
    private function writeDeviceTokens(array $tokens): void
    {
        Storage::disk('local')->put(
            'device-tokens.json',
            json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
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
     * Declare the parameters the device physically reports on.
     *
     * firstOrCreate keeps re-seeding idempotent: the unique key on
     * (device_id, parameter) would otherwise reject a second run.
     *
     * @param  list<ThresholdParameter>  $parameters
     */
    private function seedDeviceSensors(Device $device, array $parameters): void
    {
        foreach ($parameters as $parameter) {
            DeviceSensor::query()->firstOrCreate([
                'device_id' => $device->id,
                'parameter' => $parameter,
            ]);
        }
    }

    /**
     * Seed a week of readings that drift around the conditions a plant likes.
     *
     * @param  array{co2: float, temperature: float, humidity: float}  $conditions
     * @param  list<ThresholdParameter>  $parameters
     */
    private function seedReadings(Device $device, array $conditions, array $parameters): void
    {
        if ($device->readings()->exists()) {
            return;
        }

        Reading::factory()
            ->for($device)
            ->count(self::READINGS_PER_DEVICE)
            ->sequence(function (Sequence $sequence) use ($conditions, $parameters) {
                return [
                    'measured_at' => now()->subMinutes(self::SAMPLE_INTERVAL_MINUTES * (self::READINGS_PER_DEVICE - $sequence->index)),
                    // A parameter the device does not carry stays null in
                    // every row: the capability table says no sensor exists,
                    // so the absence must not read as a failed measurement.
                    'co2' => in_array(ThresholdParameter::Co2, $parameters, true)
                        ? (int) round($conditions['co2'] + fake()->numberBetween(-45, 45))
                        : null,
                    'temperature' => in_array(ThresholdParameter::Temperature, $parameters, true)
                        ? round($conditions['temperature'] + fake()->randomFloat(2, -1.4, 1.4), 2)
                        : null,
                    'humidity' => in_array(ThresholdParameter::Humidity, $parameters, true)
                        ? (int) round($conditions['humidity'] + fake()->numberBetween(-5, 5))
                        : null,
                ];
            })
            ->create();
    }

    /**
     * Seed out-of-range readings together with the alerts they explain.
     *
     * @param  array<int, array{device: Device, conditions: array{co2: float, temperature: float, humidity: float}, parameters: list<ThresholdParameter>}>  $devices
     */
    private function seedIncidents(array $devices): void
    {
        foreach (self::INCIDENTS as $incident) {
            ['device' => $device, 'conditions' => $conditions, 'parameters' => $parameters] = $devices[$incident['plant']];

            $parameter = ThresholdParameter::from($incident['parameter']);

            if (Alert::query()
                ->where('device_id', $device->id)
                ->where('parameter', $parameter)
                ->exists()) {
                continue;
            }

            $measuredAt = now()->subMinutes($incident['minutesAgo']);

            // The field the incident is about carries the incident value, the
            // others carry the plant's usual condition, and a parameter the
            // device does not report stays null even in an incident reading.
            $field = fn (ThresholdParameter $target): int|float|null => match (true) {
                $target === $parameter => $incident['value'],
                in_array($target, $parameters, true) => $conditions[$target->value],
                default => null,
            };

            $reading = Reading::query()->create([
                'device_id' => $device->id,
                'measured_at' => $measuredAt,
                // An incident was measured minutes ago and is being replayed
                // now, so the two timestamps deliberately differ. That is the
                // shape of a device uploading a backlog after losing WiFi.
                'received_at' => now(),
                'co2' => $field(ThresholdParameter::Co2),
                'temperature' => $field(ThresholdParameter::Temperature),
                'humidity' => $field(ThresholdParameter::Humidity),
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
