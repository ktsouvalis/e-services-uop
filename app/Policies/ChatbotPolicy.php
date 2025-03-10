<?php

namespace App\Policies;

use App\Models\Chatbot;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChatbotPolicy
{

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Chatbot $chatbot): bool
    {
        return $user->id === $chatbot->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }


    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Chatbot $chatbot): bool
    {
        //
        return $user->id === $chatbot->user_id;
    }
}
