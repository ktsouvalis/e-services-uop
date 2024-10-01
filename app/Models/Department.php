<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;
    
    protected $table="departments";
    protected $guarded = [];

    public function city(){
        return $this->belongsTo(City::class, 'city_id');
    }
}
