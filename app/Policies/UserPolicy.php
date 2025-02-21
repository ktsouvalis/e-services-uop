<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function create(User $user)
    {
        return $user->admin;
    }

    public function viewAny(User $user)
    {
        return $user->admin;
    }

    public function update(User $user, User $model)
    {
        return $user->admin;
    }

    public function delete(User $user, User $model)
    {
        return $user->admin;
    }
}
?>
