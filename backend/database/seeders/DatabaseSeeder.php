<?php

namespace Database\Seeders;

use App\Enums\DeviceStatus;
use App\Enums\ThresholdParameter;
use App\Models\Device;
use App\Models\DeviceSensor;
use App\Models\Threshold;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The accounts to seed. Both use the password "password".
     *
     * @var array<string, string>
     */
    private const USERS = [
        'test@example.com' => 'Test User',
        'second@example.com' => 'Second User',
    ];

    /**
     * Fallback thresholds applied to any parameter a device has not overridden.
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private const GLOBAL_THRESHOLDS = [
        'co2' => [400, 1200],
        'temperature' => [16, 30],
        'humidity' => [35, 75],
        'ph' => [5.5, 7.0],
        'ec' => [800, 2000],
        'water_temperature' => [18, 28],
        'light' => [500, 50000],
        'water_level' => [30, 90],
    ];

    /**
     * Unconnected devices (plant_id = null) with the sensors each carries.
     *
     * @var list<array{name: string, serial_number: string, sensors: list<ThresholdParameter>}>
     */
    private const DEVICES = [
        [
            'name'      => 'Living Room Sensor',
            'serial_number' => 'LIVING001',
            'sensors'   => [
                ThresholdParameter::Co2,
                ThresholdParameter::Temperature,
                ThresholdParameter::Humidity,
            ],
        ],
        [
            'name'      => 'Kitchen Sensor',
            'serial_number' => 'KITCHEN001',
            'sensors'   => [
                ThresholdParameter::Temperature,
                ThresholdParameter::Humidity,
                ThresholdParameter::Light,
            ],
        ],
        [
            'name'      => 'Bedroom Sensor',
            'serial_number' => 'BEDROOM001',
            'sensors'   => [
                ThresholdParameter::Co2,
                ThresholdParameter::Ph,
                ThresholdParameter::WaterLevel,
            ],
        ],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (self::USERS as $email => $name) {
            User::query()->firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password'],
            );
        }

        $this->seedGlobalThresholds();

        $tokens = [];

        foreach (self::DEVICES as $deviceData) {
            $device = Device::query()->firstOrCreate(
                ['serial_number' => $deviceData['serial_number']],
                [
                    'name' => $deviceData['name'],
                    'api_token' => hash('sha256', Str::random(40)),
                    'status' => DeviceStatus::Active->value,
                    'plant_id' => null,
                ],
            );

            $token = $device->generateToken();

            // Declare the sensors this physical node carries.
            foreach ($deviceData['sensors'] as $parameter) {
                DeviceSensor::query()->firstOrCreate([
                    'device_id' => $device->id,
                    'parameter' => $parameter,
                ]);
            }

            $tokens[$deviceData['serial_number']] = [
                'device_id' => $device->id,
                'serial_number' => $device->serial_number,
                'token' => $token,
            ];
        }

        $this->writeDeviceTokens($tokens);
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
     * Write the plaintext device tokens to a local file for the simulator.
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
}
