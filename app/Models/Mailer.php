<?php

namespace App\Models;

use Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Mailer extends Model
{
    use HasFactory;
    protected $table ="mail_to_departments";
    protected $guarded = [
        'id'
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function getFilesCount(): int
    {
        return is_array($this->files) ? count($this->files) : 0;
    }
    
    protected function files(): Attribute{
        return Attribute::make(
            get: function($value) { 
                return json_decode($value, true);
            },
            set: function($value){
                if (is_null($value)) {
                    return null;
                }
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        );
    }
}
