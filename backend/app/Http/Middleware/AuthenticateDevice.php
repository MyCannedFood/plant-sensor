<?php

namespace App\Http\Middleware;

use App\Enums\DeviceStatus;
use App\Enums\ThresholdParameter;
use App\Models\Device;
use App\Models\DeviceSensor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    /**
     * Resolve the posting device from its API token.
     *
     * The token is hashed before lookup, so the database never compares
     * against a plaintext secret: api_token holds sha256(token) and the
     * device presents the 40-character original in X-Device-Token.
     *
     * The resolved device is attached to the request so the ingest request
     * and controller can read it without a second lookup.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Device-Token');

        if (! is_string($token) || $token === '') {
            abort(401, 'A device token is required.');
        }

        $device = Device::query()
            ->where('api_token', hash('sha256', $token))
            ->first();

        if ($device === null) {
            // Auto-provision from preprovisioned-devices.json so physical
            // devices can authenticate with their static token without a
            // manual seeding step.
            $device = $this->provisionFromJson($token);
        }

        if ($device === null) {
            abort(401, 'The device token is not valid.');
        }

        if ($device->status !== DeviceStatus::Active) {
            abort(403, 'The device is not accepting readings.');
        }

        // Recorded even when the payload later fails validation: a device
        // whose sensors are all reporting junk is still an online device.
        // A refused device (403 above) is deliberately not marked as seen,
        // so an out-of-service node is not mistaken for one still reporting.
        $device->last_seen_at = now();
        $device->save();

        $request->attributes->set('device', $device);

        return $next($request);
    }

    /**
     * Auto-provision a device from preprovisioned-devices.json.
     *
     * Reads the plaintext token from the provisioning file, creates a Device
     * record with the SHA-256 hash, and declares the sensors it carries.
     *
     * @param  string  $token  Plaintext device token
     * @return \App\Models\Device|null
     */
    private function provisionFromJson(string $token): ?Device
    {
        $path = 'preprovisioned-devices.json';

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        $data = json_decode(Storage::disk('local')->get($path), true);

        if (! is_array($data)) {
            return null;
        }

        foreach ($data as $entry) {
            if (($entry['token'] ?? null) !== $token) {
                continue;
            }

            $serial = $entry['serial_number'] ?? null;
            if (! is_string($serial) || $serial === '') {
                return null;
            }

            // Don't steal a device already assigned to another plant.
            $existing = Device::where('serial_number', $serial)->first();
            if ($existing && $existing->plant_id !== null) {
                return null;
            }

            $device = Device::firstOrCreate(
                ['serial_number' => $serial],
                [
                    'name'      => $entry['name'] ?? "Device {$serial}",
                    'api_token' => hash('sha256', $token),
                    'status'    => DeviceStatus::Active->value,
                    'plant_id'  => null,
                ]
            );

            $device->update([
                'api_token' => hash('sha256', $token),
                'status'    => DeviceStatus::Active->value,
            ]);

            // Declare the sensors this physical node carries.
            foreach ($entry['sensors'] ?? [] as $sensorParam) {
                try {
                    $parameter = ThresholdParameter::from($sensorParam);

                    DeviceSensor::query()->firstOrCreate([
                        'device_id' => $device->id,
                        'parameter' => $parameter,
                    ]);
                } catch (\ValueError $e) {
                    // Unknown sensor parameter — skip it.
                }
            }

            return $device;
        }

        return null;
    }
}
