<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    private static int $nextId = 1;

    public function definition(): array
    {
        return [
            'id' => self::$nextId++,
            'name' => fake()->unique()->company(),
            'city_id' => City::factory(),
            'email' => fake()->unique()->companyEmail(),
        ];
    }
}
