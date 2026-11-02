<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\AlertSeverity;
use App\Http\Controllers\Controller;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Alert history across every plant the user owns.
 *
 * The filters partition the history: "alert" and "watch" are the open
 * critical and warning events, "resolved" is everything closed, so the
 * three counts add up to "all".
 */
class AlertController extends Controller
{
    private const FILTERS = ['all', 'alert', 'watch', 'resolved'];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filter' => ['sometimes', Rule::in(self::FILTERS)],
            'plant' => ['sometimes', 'integer'],
            'limit' => ['sometimes', 'integer', 'between:1,200'],
        ]);

        $filter = $validated['filter'] ?? 'all';

        $base = fn (): Builder => Alert::query()
            ->ownedBy($request->user())
            ->when(
                isset($validated['plant']),
                fn (Builder $query) => $query->whereHas('device', fn (Builder $devices) => $devices->where('plant_id', $validated['plant'])),
            );

        $alerts = $this->applyFilter($base(), $filter)
            ->with('device.plant')
            ->orderByDesc('triggered_at')
            ->limit($validated['limit'] ?? 100)
            ->get();

        return response()->json([
            'filter' => $filter,
            'counts' => [
                'all' => $base()->count(),
                'alert' => $this->applyFilter($base(), 'alert')->count(),
                'watch' => $this->applyFilter($base(), 'watch')->count(),
                'resolved' => $this->applyFilter($base(), 'resolved')->count(),
            ],
            'alerts' => AlertResource::collection($alerts)->resolve($request),
        ]);
    }

    /**
     * The number behind the bell in the top bar. Kept separate so the
     * header can poll it on every page without pulling the whole list.
     */
    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'active' => Alert::query()->ownedBy($request->user())->active()->count(),
        ]);
    }

    /**
     * @param  Builder<Alert>  $query
     * @return Builder<Alert>
     */
    private function applyFilter(Builder $query, string $filter): Builder
    {
        return match ($filter) {
            'alert' => $query->active()->where('severity', AlertSeverity::Critical),
            'watch' => $query->active()->where(fn (Builder $q) => $q
                ->where('severity', AlertSeverity::Warning)
                ->orWhereNull('severity')),
            'resolved' => $query->resolved(),
            default => $query,
        };
    }
}
