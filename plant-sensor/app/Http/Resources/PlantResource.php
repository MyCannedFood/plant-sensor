<?php

namespace App\Http\Resources;

use App\Models\Alert;
use App\Models\DeviceSensor;
use App\Models\Reading;
use App\Support\MetricSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the plant monitor page shows apart from the chart series,
 * which has its own endpoint so switching metric or range stays cheap.
 *
 * Expects devices.sensors and devices.latestReading to be eager loaded.
 *
 * @mixin \App\Models\Plant
 */
class PlantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // A plant may own several nodes; the monitor focuses on the first
        // one (its metrics, chart and thresholds) and lists all of them.
        $device = $this->devices->first();
        $deviceIds = $this->devices->pluck('id');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'species' => $this->species,
            'location' => $this->location,
            'notes' => $this->notes,
            'primary_device_id' => $device?->id,
            'metrics' => $device
                ? $device->sensors
                    ->map(fn (DeviceSensor $sensor) => MetricSnapshot::for($device, $sensor->parameter))
                    ->values()
                    ->all()
                : [],
            'devices' => DeviceResource::collection($this->devices)->resolve($request),
            'recent_alerts' => AlertResource::collection(
                Alert::query()
                    ->with('device')
                    ->whereIn('device_id', $deviceIds)
                    ->orderByDesc('triggered_at')
                    ->limit(3)
                    ->get()
            )->resolve($request),
            // What a delete would take with it, for the confirmation dialog.
            'counts' => [
                'devices' => $deviceIds->count(),
                'readings' => Reading::query()->whereIn('device_id', $deviceIds)->count(),
                'alerts' => Alert::query()->whereIn('device_id', $deviceIds)->count(),
            ],
        ];
    }
}
