<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One threshold event. Sent as structured fields rather than a ready-made
 * sentence so the frontend decides wording and language.
 *
 * @mixin \App\Models\Alert
 */
class AlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->statusKey(),
            'severity' => $this->severity?->value,
            'parameter' => $this->parameter->value,
            'label' => $this->parameter->label(),
            'unit' => $this->parameter->unit(),
            'decimals' => $this->parameter->decimals(),
            'direction' => $this->direction->value,
            'value' => $this->value === null ? null : (float) $this->value,
            'triggered_at' => $this->triggered_at->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'device' => $this->whenLoaded('device', fn () => [
                'id' => $this->device->id,
                'name' => $this->device->name,
            ]),
            'plant' => $this->whenLoaded('device', fn () => $this->device->relationLoaded('plant') && $this->device->plant
                ? ['id' => $this->device->plant->id, 'name' => $this->device->plant->name]
                : null),
        ];
    }
}
