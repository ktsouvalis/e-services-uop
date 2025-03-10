<?php

namespace App\Policies;

use App\Models\AImodel;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AImodelPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        //
        return $user->admin;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AImodel $aImodel): bool
    {
        //
        return $user->admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AImodel $aImodel): bool
    {
        //
        return $user->admin;
    }
}
