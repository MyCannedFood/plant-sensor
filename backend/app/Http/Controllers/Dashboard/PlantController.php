<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\DeviceStatus;
use App\Enums\ThresholdParameter;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlantRequest;
use App\Http\Resources\PlantResource;
use App\Http\Resources\PlantSummaryResource;
use App\Models\Device;
use App\Models\DeviceSensor;
use App\Models\Plant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlantController extends Controller
{
    /**
     * The overview: one card per plant the user owns.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $plants = $request->user()
            ->plants()
            ->with($this->monitorRelations())
            ->orderBy('name')
            ->get();

        return PlantSummaryResource::collection($plants);
    }

    public function store(PlantRequest $request): JsonResponse
    {
        $plant = $request->user()->plants()->create($request->validated());

        $deviceToken = null;

        if ($request->filled('device_token')) {
            $token = $request->input('device_token');
            $tokenHash = hash('sha256', $token);

            // Find device by token hash in the database.
            $device = Device::where('api_token', $tokenHash)
                ->unassigned()
                ->first();

            // If not in the database yet, auto-provision from preprovisioned-devices.json.
            if ($device === null) {
                $device = $this->provisionFromJson($token);
            }

            if ($device === null) {
                abort(404, 'The device token is not valid.');
            }

            // Link device to plant
            $device->plant_id = $plant->id;
            $device->save();

            // Device keeps its original token from the seeder.
            // No rotation: the simulator/device authenticates with the same token.
            $deviceToken = $token;
        }

        return (new PlantResource($plant->load($this->monitorRelations())))
            ->additional(['device_token' => $deviceToken])
            ->response()
            ->setStatusCode(201);
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

            // Don't steal a device that is already assigned to another plant.
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

    /**
     * The plant monitor page: metrics, devices, recent alerts.
     */
    public function show(Plant $plant): PlantResource
    {
        Gate::authorize('view', $plant);

        return new PlantResource($plant->load($this->monitorRelations()));
    }

    public function update(PlantRequest $request, Plant $plant): PlantResource
    {
        Gate::authorize('update', $plant);

        $plant->update($request->validated());

        return new PlantResource($plant->load($this->monitorRelations()));
    }

    /**
     * Deleting a plant cascades to its devices, readings and alerts
     * (foreign keys are cascadeOnDelete).
     */
    public function destroy(Plant $plant): Response
    {
        Gate::authorize('delete', $plant);

        $plant->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, \Closure>
     */
    private function monitorRelations(): array
    {
        return [
            'devices' => fn ($query) => $query
                ->with(['sensors', 'latestReading'])
                ->orderBy('name'),
        ];
    }
}
