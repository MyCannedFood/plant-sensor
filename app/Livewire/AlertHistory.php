<?php

namespace App\Livewire;

use App\Enums\AlertDirection;
use App\Enums\AlertSeverity;
use App\Enums\ThresholdParameter;
use App\Models\Alert;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Alert history — Verdant')]
class AlertHistory extends Component
{
    public string $filter = 'all';

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'alert', 'watch', 'resolved'], true)
            ? $filter
            : 'all';
    }

    public function render()
    {
        return view('livewire.alert-history', $this->viewData());
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(): array
    {
        $query = Alert::query()
            ->with(['device.plant'])
            ->orderByDesc('triggered_at');

        $query = match ($this->filter) {
            'alert' => $query->where('severity', AlertSeverity::Critical),
            'watch' => $query->where('severity', AlertSeverity::Warning),
            'resolved' => $query->resolved(),
            default => $query,
        };

        return [
            'alerts' => $query->limit(100)->get()->map(fn (Alert $alert) => $this->describe($alert))->all(),
            'counts' => [
                'all' => Alert::count(),
                'alert' => Alert::where('severity', AlertSeverity::Critical)->count(),
                'watch' => Alert::where('severity', AlertSeverity::Warning)->count(),
                'resolved' => Alert::resolved()->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Alert $alert): array
    {
        return [
            'severity_key' => $alert->resolved_at ? 'good' : ($alert->severity === AlertSeverity::Critical ? 'alert' : 'watch'),
            'severity' => $alert->resolved_at ? 'Resolved' : ucfirst($alert->severity->value),
            'time' => $alert->triggered_at->format('H:i'),
            'day' => $alert->triggered_at->isToday() ? 'today' : '· '.$alert->triggered_at->format('d M'),
            'room' => $alert->device?->plant?->name ?? 'Unknown room',
            'message' => $this->alertMessage($alert),
            'meta' => $alert->device?->name ?? 'Unknown node',
        ];
    }

    private function alertMessage(Alert $alert): string
    {
        $direction = $alert->direction === AlertDirection::AboveMaximum ? 'above' : 'below';

        return sprintf(
            '%s at %s %s moved %s the %s threshold',
            $alert->parameter->label(),
            $this->format((float) $alert->value, $alert->parameter),
            $alert->parameter->unit(),
            $direction,
            $alert->direction === AlertDirection::BelowMinimum ? 'minimum' : 'maximum',
        );
    }

    private function format(float $value, ThresholdParameter $parameter): string
    {
        return number_format(
            $value,
            $parameter === ThresholdParameter::Temperature ? 1 : 0,
        );
    }
}
