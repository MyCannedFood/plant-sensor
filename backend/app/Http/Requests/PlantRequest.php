<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by create and update. The global TrimStrings and
 * ConvertEmptyStringsToNull middleware already turn "  " into null, so a
 * cleared optional field is stored as null without extra handling here.
 */
class PlantRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:60'],
            'species' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'device_token' => ['nullable', 'string', 'size:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give this plant a name.',
            'name.max' => 'Keep the name under 60 characters.',
        ];
    }
}
