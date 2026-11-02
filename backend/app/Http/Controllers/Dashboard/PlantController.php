<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlantRequest;
use App\Http\Resources\PlantResource;
use App\Http\Resources\PlantSummaryResource;
use App\Models\Plant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PlantController extends Controller
{
    /**
     * The overview: one card per plant the user owns.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $plants = $request->user()
            ->plants()
            ->with($this->monitorRelations())
            ->orderBy('name')
            ->get();

        return PlantSummaryResource::collection($plants);
    }

    public function store(PlantRequest $request): JsonResponse
    {
        $plant = $request->user()->plants()->create($request->validated());

        return (new PlantResource($plant->load($this->monitorRelations())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * The plant monitor page: metrics, devices, recent alerts.
     */
    public function show(Plant $plant): PlantResource
    {
        Gate::authorize('view', $plant);

        return new PlantResource($plant->load($this->monitorRelations()));
    }

    public function update(PlantRequest $request, Plant $plant): PlantResource
    {
        Gate::authorize('update', $plant);

        $plant->update($request->validated());

        return new PlantResource($plant->load($this->monitorRelations()));
    }

    /**
     * Deleting a plant cascades to its devices, readings and alerts
     * (foreign keys are cascadeOnDelete).
     */
    public function destroy(Plant $plant): Response
    {
        Gate::authorize('delete', $plant);

        $plant->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, \Closure>
     */
    private function monitorRelations(): array
    {
        return [
            'devices' => fn ($query) => $query
                ->with(['sensors', 'latestReading'])
                ->orderBy('name'),
        ];
    }
}
