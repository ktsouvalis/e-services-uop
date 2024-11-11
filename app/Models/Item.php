<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Item extends Model
{
    //
    protected $table = 'items';
    protected $guarded = ['id'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    // protected function sourceOfFunding(): Attribute {
    //     return Attribute::make(
    //         get: fn ($value) => match ($value) {
    //             1 => 'Τακτικός Προϋπολογισμός',
    //             2 => 'ΠΔΕ',
    //         },
    //         // set: fn ($value) => match ($value) {
    //         //     'Τακτικός Προϋπολογισμός' => 1,
    //         //     'ΠΔΕ' => 2,
    //         // }
    //     );
    // }

    // protected function status(): Attribute {
    //     return Attribute::make(
    //         get: fn ($value) => match ($value) {
    //             1 => 'Άχρηστο',
    //             2 => 'Μέτρια',
    //             3 => 'Καλή',
    //             4 => 'Άριστη',
    //         },
    //         // set: fn ($value) => match ($value) {
    //         //     'Άχρηστο' => 1,
    //         //     'Μέτρια' => 2,
    //         //     'Καλή' => 3,
    //         //     'Άριστη' => 4,
    //         // }
    //     );
    // }
}
