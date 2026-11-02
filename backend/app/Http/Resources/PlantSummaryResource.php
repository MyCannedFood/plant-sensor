<?php

namespace App\Http\Resources;

use App\Models\Alert;
use App\Models\DeviceSensor;
use App\Support\MetricSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A plant as one card on the overview: who it is, how its primary node is
 * doing and the latest value of every parameter that node carries.
 *
 * Expects devices.sensors and devices.latestReading to be eager loaded.
 *
 * @mixin \App\Models\Plant
 */
class PlantSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $device = $this->devices->first();

        // The reading before the latest one, to tell whether a value is
        // rising or falling.
        $previous = $device?->readings()->orderByDesc('measured_at')->skip(1)->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'species' => $this->species,
            'location' => $this->location,
            'online' => $device?->isOnline() ?? false,
            'last_seen_at' => $device?->last_seen_at?->toIso8601String(),
            'readings_today' => $device?->readings()->whereDate('measured_at', today())->count() ?? 0,
            'active_alerts' => $device
                ? Alert::query()->where('device_id', $device->id)->active()->count()
                : 0,
            'metrics' => $device
                ? $device->sensors
                    ->map(fn (DeviceSensor $sensor) => MetricSnapshot::for($device, $sensor->parameter, $previous))
                    ->values()
                    ->all()
                : [],
        ];
    }
}
