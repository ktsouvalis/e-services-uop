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
        if($user->admin){
            return true;
        }
        return $sheetmailer->user->id===$user->id;
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
        return $this->view($user, $sheetmailer);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Sheetmailer $sheetmailer): bool
    {
        return $this->view($user, $sheetmailer);
    }
}
