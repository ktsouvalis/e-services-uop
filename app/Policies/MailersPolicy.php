<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Mailer;
use Illuminate\Auth\Access\Response;

class MailersPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Mailer $mailer): bool
    {
        //
        // dd($mailer);
        if($user->admin){
            return true;
        }
        return $mailer->user->id===$user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return auth()->check();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Mailer $mailer): bool
    {
        return $this->view($user, $mailer);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Mailer $mailer): bool
    {
        return $this->view($user, $mailer);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Mailer $mailer): bool
    {
        return $this->view($user, $mailer);
    }
}
