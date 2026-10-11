<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Enums\ThresholdParameter;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['name', 'serial_number', 'api_token', 'firmware_version', 'status', 'last_seen_at'])]
#[Hidden(['api_token'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /**
     * Get the device's most recent reading.
     *
     * @return HasOne<Reading, $this>
     */
    public function latestReading(): HasOne
    {
        return $this->hasOne(Reading::class)->latestOfMany('measured_at');
    }

    /**
     * Get the plant the device is monitoring.
     */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    /**
     * Get the readings recorded by the device.
     *
     * @return HasMany<Reading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    /**
     * Get the thresholds configured for the device.
     *
     * @return HasMany<Threshold, $this>
     */
    public function thresholds(): HasMany
    {
        return $this->hasMany(Threshold::class);
    }

    /**
     * Get the alerts raised for the device.
     *
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Replace the device's API token and return the new plaintext.
     *
     * Only sha256(token) is stored, so this is the one moment the original
     * exists: it must be handed to whoever provisions the device (or written
     * to the local token file for the simulator) and cannot be recovered
     * from the database afterwards.
     */
    public function generateToken(): string
    {
        $token = Str::random(40);

        $this->api_token = hash('sha256', $token);
        $this->save();

        return $token;
    }

    /**
     * Get the parameters this device is physically able to report.
     *
     * @return HasMany<DeviceSensor, $this>
     */
    public function sensors(): HasMany
    {
        return $this->hasMany(DeviceSensor::class);
    }

    /**
     * Determine whether the device carries a sensor for the parameter.
     *
     * This is the check that decides whether a null measurement means "no
     * sensor" or "sensor failed", and it is the reason readings can be
     * stored with a subset of the parameters filled in.
     */
    public function declares(ThresholdParameter $parameter): bool
    {
        if ($this->relationLoaded('sensors')) {
            return $this->sensors->contains('parameter', $parameter);
        }

        return $this->sensors()->where('parameter', $parameter)->exists();
    }

    /**
     * Get the effective threshold bounds for a parameter, including the
     * global default when this device has not overridden it.
     *
     * @return array{0: float|null, 1: float|null}
     */
    public function thresholdsFor(ThresholdParameter $parameter): array
    {
        $threshold = Threshold::query()
            ->applicableTo($this)
            ->where('parameter', $parameter)
            ->first();

        return [
            $threshold?->min_value !== null ? (float) $threshold->min_value : null,
            $threshold?->max_value !== null ? (float) $threshold->max_value : null,
        ];
    }

    /**
     * Classify a measured value against the device's thresholds.
     *
     * Returns "good", "watch" or "alert" — or "offline" when there is no
     * reading for the parameter at all.
     */
    public function statusOf(ThresholdParameter $parameter, float|string|null $value): string
    {
        if ($value === null) {
            return 'offline';
        }

        $value = (float) $value;
        [$min, $max] = $this->thresholdsFor($parameter);

        if ($min === null || $max === null) {
            return 'good';
        }

        if ($value > $max || $value < $min) {
            return 'alert';
        }

        $width = ($max - $min) / 2;

        return $width <= 0 || abs($value - ($min + $width)) / $width <= 0.72
            ? 'good'
            : 'watch';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'last_seen_at' => 'datetime',
        ];
    }
}
