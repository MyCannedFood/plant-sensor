<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ThresholdParameter;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateThresholdsRequest;
use App\Models\Device;
use App\Models\Threshold;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Device-specific threshold overrides. Global defaults (device_id = null)
 * still apply to any parameter the device has not overridden.
 */
class DeviceThresholdController extends Controller
{
    public function update(UpdateThresholdsRequest $request, Device $device): JsonResponse
    {
        Gate::authorize('update', $device->plant);

        DB::transaction(function () use ($request, $device): void {
            foreach ($request->validated('thresholds') as $parameter => $bounds) {
                Threshold::query()->updateOrCreate(
                    ['device_id' => $device->id, 'parameter' => ThresholdParameter::from($parameter)],
                    [
                        'min_value' => $bounds['min'] === null ? null : (float) $bounds['min'],
                        'max_value' => $bounds['max'] === null ? null : (float) $bounds['max'],
                    ],
                );
            }
        });

        // Echo back the effective bounds for every parameter the device
        // carries, so the client can refresh its form from the server.
        $thresholds = $device->sensors()->get()
            ->mapWithKeys(function ($sensor) use ($device) {
                [$min, $max] = $device->thresholdsFor($sensor->parameter);

                return [$sensor->parameter->value => ['min' => $min, 'max' => $max]];
            });

        return response()->json(['thresholds' => $thresholds]);
    }
}
