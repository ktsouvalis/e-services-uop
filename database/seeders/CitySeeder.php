<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\City;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $cities = [
            ['id' => 1, 'name' => 'Τρίπολη'],
            ['id' => 2, 'name' => 'Σπάρτη'],
            ['id' => 3, 'name' => 'Καλαμάτα'],
            ['id' => 4, 'name' => 'Πάτρα'],
            ['id' => 5, 'name' => 'Ναύπλιο'],
            ['id' => 6, 'name' => 'Κόρινθος'],
        ];

        foreach ($cities as $city) {
            City::create($city);
        }
    }
}