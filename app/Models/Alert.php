<?php

namespace App\Models;

use App\Enums\AlertDirection;
use App\Enums\AlertSeverity;
use App\Enums\ThresholdParameter;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reading_id', 'device_id', 'threshold_id', 'parameter', 'direction', 'severity', 'value', 'triggered_at', 'resolved_at'])]
class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory;

    /**
     * Get the reading that triggered the alert.
     */
    public function reading(): BelongsTo
    {
        return $this->belongsTo(Reading::class);
    }

    /**
     * Get the device the alert belongs to.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the threshold that was breached.
     */
    public function threshold(): BelongsTo
    {
        return $this->belongsTo(Threshold::class);
    }

    /**
     * Limit the query to alerts that are still unresolved.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Limit the query to alerts that have been resolved.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function resolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
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
            'direction' => AlertDirection::class,
            'severity' => AlertSeverity::class,
            'value' => 'decimal:2',
            'triggered_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
