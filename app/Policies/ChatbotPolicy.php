<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;
use App\Models\Chatbot;
use Illuminate\Auth\Access\Response;

class ChatbotPolicy
{
    private $menu = 'chatbots';
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return Menu::where('route_is', $this->menu)->first()->enabled;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Chatbot $chatbot): bool
    {
        if(!Menu::where('route_is', $this->menu)->first()->enabled)
            return false;
        return $user->id === $chatbot->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if(!Menu::where('route_is', $this->menu)->first()->enabled)
            return false;
        return true;
    }


    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Chatbot $chatbot): bool
    {
        //
        if(!Menu::where('route_is', $this->menu)->first()->enabled)
            return false;
        return $user->id === $chatbot->user_id;
    }
}
