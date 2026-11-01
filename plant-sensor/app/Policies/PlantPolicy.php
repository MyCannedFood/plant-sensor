<?php

namespace App\Policies;

use App\Models\Plant;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A plant, and everything hanging off it (devices, readings, thresholds,
 * alerts), belongs to exactly one user.
 *
 * Someone else's plant answers 404 rather than 403, so the API does not
 * confirm that a given plant id exists.
 */
class PlantPolicy
{
    public function view(User $user, Plant $plant): Response
    {
        return $this->owns($user, $plant);
    }

    public function update(User $user, Plant $plant): Response
    {
        return $this->owns($user, $plant);
    }

    public function delete(User $user, Plant $plant): Response
    {
        return $this->owns($user, $plant);
    }

    private function owns(User $user, Plant $plant): Response
    {
        return $user->id === $plant->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
