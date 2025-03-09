<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AImodel extends Model
{
    protected $table = 'ai_models';
    protected $guarded = ['id'];

    public function chatbots()
    {
        return $this->hasMany(Chatbot::class);
    }
}
