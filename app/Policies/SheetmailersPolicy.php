<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;
use App\Models\Sheetmailer;
use Illuminate\Auth\Access\Response;

class SheetmailersPolicy
{
    /**
     * The menu identifier for this policy.
     */
    private $menu = 'sheetmailers';
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        //
        return Menu::where('route_is', $this->menu)->first()->enabled && auth()->check();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Sheetmailer $sheetmailer): bool
    {
        if(!Menu::where('route_is', $this->menu)->first()->enabled)
            return false;
        // if($user->admin){
        //     return true;
        // }
        // Public sheetmailers visible to all authenticated users when enabled
        if ($sheetmailer->is_public) {
            return auth()->check();
        }
        // Private: only creator
        return $sheetmailer->user && $sheetmailer->user->id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if(!Menu::where('route_is', $this->menu)->first()->enabled)
            return false;
        return auth()->check(); 
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Sheetmailer $sheetmailer): bool
    {
        // Admin or creator can always update
        // if ($user->admin) return true;
        if ($sheetmailer->user && $sheetmailer->user->id === $user->id) return true;
        // If public, allow authenticated users to update (creator-only rules for sensitive fields handled elsewhere)
        if ($sheetmailer->is_public) return true;
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Sheetmailer $sheetmailer): bool
    {
        // Only creator can delete, even if admin
        return $sheetmailer->user && $sheetmailer->user->id === $user->id;
    }
}
