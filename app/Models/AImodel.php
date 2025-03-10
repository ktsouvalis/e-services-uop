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

    public function properties()
    {
        return [
                'accepts_chat'=>$this->accepts_chat,
                'accepts_audio'=>$this->accepts_audio,
                'accepts_developer_messages'=>$this->accepts_developer_messages,
                'accepts_system_messages'=>$this->accepts_system_messages,
                'reasoning_effort'=>$this->reasoning_effort,    
        ];
    }
}
