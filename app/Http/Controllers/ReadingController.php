<?php

namespace App\Http\Controllers;

use App\Enums\ThresholdParameter;
use App\Http\Requests\StoreReadingRequest;
use App\Http\Resources\ReadingResource;
use App\Models\Reading;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ReadingController extends Controller
{
    /**
     * Store a reading a device reports.
     *
     * createOrFirst turns the (device_id, measured_at) unique key into the
     * stuck-clock guard: a fresh instant inserts and returns 201; a retry
     * of an earlier post the device never saw the answer to lands on the
     * existing row — 200 when the values are identical, 409 when they
     * diverge (the device's clock is producing two snapshots for one
     * instant, so at least one of them is a lie).
     */
    public function store(StoreReadingRequest $request): JsonResponse
    {
        $device = $request->device();
        $validated = $request->validated();

        $reading = Reading::createOrFirst(
            [
                'device_id' => $device->id,
                // Carbon, not the raw ISO string: the collision lookup in
                // createOrFirst queries with these keys and the query grammar
                // formats DateTimeInterface the same way the column casts it,
                // so the retry finds the stored row instead of throwing.
                'measured_at' => Carbon::parse($validated['measured_at']),
            ],
            [
                'co2' => $validated['co2'] ?? null,
                'temperature' => $validated['temperature'] ?? null,
                'humidity' => $validated['humidity'] ?? null,
            ],
        );

        if ($reading->wasRecentlyCreated) {
            return (new ReadingResource($reading))->response()->setStatusCode(201);
        }

        if ($this->storedValuesMatch($reading, $validated)) {
            return (new ReadingResource($reading))->response();
        }

        return response()->json([
            'message' => 'A reading at this exact instant already exists with different values.',
            'reading' => new ReadingResource($reading),
        ], 409);
    }

    /**
     * Whether the retried payload matches what is already stored.
     *
     * Null-sensitive: an absent/null sensor must stay null (a sensor going
     * silent does not reuse the previous value). Numeric values agree when
     * they are equal to two decimal places, matching the decimal:2 columns.
     *
     * @param  array<string, mixed>  $validated
     */
    private function storedValuesMatch(Reading $reading, array $validated): bool
    {
        foreach (ThresholdParameter::cases() as $parameter) {
            $column = $parameter->readingColumn();
            $stored = $reading->getAttribute($column);
            $incoming = $validated[$column] ?? null;

            if ($stored === null || $incoming === null) {
                if ($stored !== $incoming) {
                    return false;
                }

                continue;
            }

            if (abs((float) $stored - (float) $incoming) >= 0.005) {
                return false;
            }
        }

        return true;
    }
}
