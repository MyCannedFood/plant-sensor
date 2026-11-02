<?php

namespace App\Http\Resources;

use App\Models\DeviceSensor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A node as the dashboard lists it. The API token is never part of this
 * shape (it is also hidden on the model).
 *
 * @mixin \App\Models\Device
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'serial_number' => $this->serial_number,
            'firmware_version' => $this->firmware_version,
            'status' => $this->status?->value,
            'online' => $this->isOnline(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'readings_today' => $this->readings()->whereDate('measured_at', today())->count(),
            'sensors' => $this->sensors->map(fn (DeviceSensor $sensor) => [
                'parameter' => $sensor->parameter->value,
                'label' => $sensor->parameter->label(),
                'model' => $sensor->parameter->sensorModel(),
            ])->values()->all(),
        ];
    }
}
