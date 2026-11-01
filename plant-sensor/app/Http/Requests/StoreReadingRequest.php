<?php

namespace App\Http\Requests;

use App\Enums\ThresholdParameter;
use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReadingRequest extends FormRequest
{
    /**
     * Authorize the request.
     *
     * AuthenticateDevice has already resolved the device and gated it on
     * status, so there is nothing left for the request itself to decide.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The bounds come from the datasheets: the MH-Z19C reports 400-5000 ppm
     * (up to 10000 in some variants) as whole numbers, and the DHT22 covers
     * -40..80 °C with 0.1 %RH resolution over 0..100. measured_at is
     * required because its column is NOT NULL and the ESP8266 supplies it
     * from NTP; the five-minute tolerance absorbs ordinary clock drift
     * without admitting a clock that never synced.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'co2' => ['nullable', 'integer', 'between:0,10000'],
            'temperature' => ['nullable', 'numeric', 'between:-40,80'],
            'humidity' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,100'],
            'measured_at' => ['required', 'date', 'before_or_equal:now+5 minutes'],
        ];
    }

    /**
     * Get the validation rules that need the whole payload at once.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            // A non-null value for a parameter this device never declared.
            // Rejecting it keeps the capability table honest: silently
            // absorbing the value would turn a firmware mistake into a
            // permanent, unverifiable "capability".
            function (Validator $validator): void {
                foreach (ThresholdParameter::cases() as $parameter) {
                    // Absent and explicit null are the same answer: the
                    // device did not report this parameter this cycle.
                    if ($this->input($parameter->value) === null) {
                        continue;
                    }

                    if (! $this->device()->declares($parameter)) {
                        $validator->errors()->add(
                            $parameter->value,
                            "This device does not report {$parameter->value}.",
                        );
                    }
                }
            },

            // Nothing measured means there is no observation to store, and
            // the capability table already explains every legitimate null.
            // Refusing loudly also keeps a device whose entire sensor load
            // has failed from filling the table with empty rows.
            function (Validator $validator): void {
                $hasMeasurement = collect(ThresholdParameter::cases())
                    ->contains(fn (ThresholdParameter $parameter) => $this->input($parameter->value) !== null);

                if (! $hasMeasurement) {
                    $validator->errors()->add(
                        'measurements',
                        'At least one of co2, temperature or humidity must be present.',
                    );
                }
            },
        ];
    }

    /**
     * The device AuthenticateDevice resolved for this request.
     *
     * The middleware attaches it to the attributes bag before validation
     * runs, and Request::createFrom copies that bag into this FormRequest.
     */
    public function device(): Device
    {
        return $this->attributes->get('device');
    }
}
