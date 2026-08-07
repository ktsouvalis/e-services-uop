<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Chatbot extends Model
{
    use HasFactory;

    protected $table = 'chatbots';
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function aiModel()
    {
        return $this->belongsTo(AImodel::class);
    }
}
