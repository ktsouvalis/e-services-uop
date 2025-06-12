<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ItemPolicy
{
    /**
     * The menu identifier for this policy.
     */
    private $menu = 'items';
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return Menu::where('route_is', $this->menu)->first()->enabled && auth()->check();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Item $item): bool
    {
        if(!$item->user) return true;
        return $item->user == $user;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return Menu::where('route_is', $this->menu)->first()->enabled && auth()->check();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Item $item): bool
    {
        return $this->view($user, $item);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Item $item): bool
    {
        return $this->view($user, $item);
    }
}
