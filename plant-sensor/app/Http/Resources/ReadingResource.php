<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReadingResource extends JsonResource
{
    /**
     * Transform the stored reading into the shape the firmware sees.
     *
     * server_time lets the device detect clock drift: it is the server's
     * current instant, unrelated to the reading itself.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'measured_at' => $this->measured_at->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'co2' => $this->co2,
            'temperature' => $this->measurement('temperature'),
            'humidity' => $this->measurement('humidity'),
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * The decimal columns come back as strings ("21.40"); emit a number
     * instead unless the sensor simply was not present on this device.
     */
    private function measurement(string $column): ?float
    {
        $value = $this->{$column};

        return $value === null ? null : (float) $value;
    }
}
