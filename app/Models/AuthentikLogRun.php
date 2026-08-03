<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthentikLogRun extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
