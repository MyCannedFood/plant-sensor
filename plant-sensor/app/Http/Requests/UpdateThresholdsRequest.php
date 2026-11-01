<?php

namespace App\Http\Requests;

use App\Enums\ThresholdParameter;
use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Body shape:
 *
 *     { "thresholds": { "co2": { "min": 400, "max": 1500 }, "temperature": { "min": null, "max": 30 } } }
 *
 * Only parameters the device physically carries are accepted, and a null
 * bound means "no limit on that side".
 */
class UpdateThresholdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is checked by PlantPolicy in the controller.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'thresholds' => ['required', 'array:'.implode(',', array_column(ThresholdParameter::cases(), 'value'))],
            'thresholds.*' => ['array:min,max'],
            'thresholds.*.min' => ['present', 'nullable', 'numeric'],
            'thresholds.*.max' => ['present', 'nullable', 'numeric'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach ($this->input('thresholds', []) as $parameter => $bounds) {
                    if (! $this->device()->declares(ThresholdParameter::from($parameter))) {
                        $validator->errors()->add(
                            "thresholds.{$parameter}",
                            "This device does not report {$parameter}.",
                        );

                        continue;
                    }

                    $min = $bounds['min'] ?? null;
                    $max = $bounds['max'] ?? null;

                    if ($min !== null && $max !== null && (float) $min >= (float) $max) {
                        $validator->errors()->add(
                            "thresholds.{$parameter}.min",
                            'The minimum must be lower than the maximum.',
                        );
                    }
                }
            },
        ];
    }

    public function device(): Device
    {
        return $this->route('device');
    }
}
