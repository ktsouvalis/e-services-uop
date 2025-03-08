<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AImodel extends Model
{
    protected $table = 'ai_models';
    protected $fillable = ['name', 'description'];

    public function chatbots()
    {
        return $this->hasMany(Chatbot::class);
    }
}
