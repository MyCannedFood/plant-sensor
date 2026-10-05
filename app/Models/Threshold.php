<?php

namespace App\Models;

use App\Enums\ThresholdParameter;
use Database\Factories\ThresholdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parameter', 'min_value', 'max_value'])]
class Threshold extends Model
{
    /** @use HasFactory<ThresholdFactory> */
    use HasFactory;

    /**
     * Get the device the threshold is scoped to.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the alerts triggered by the threshold.
     *
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Limit the query to thresholds that apply to every device.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function globalDefaults(Builder $query): Builder
    {
        return $query->whereNull('device_id');
    }

    /**
     * Limit the query to thresholds that apply to the given device, including
     * the global defaults that act as its fallback.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function applicableTo(Builder $query, Device $device): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where('device_id', $device->getKey())
            ->orWhereNull('device_id'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parameter' => ThresholdParameter::class,
            'min_value' => 'decimal:2',
            'max_value' => 'decimal:2',
        ];
    }
}
