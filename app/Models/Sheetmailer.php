<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sheetmailer extends Model
{
    use HasFactory;

    protected $table ="sheetmailers";
    protected $guarded = [
        'id'
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
