<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Item>
 */
class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'description' => fake()->sentence(4),
            's_n' => fake()->bothify('SN-########'),
            'brand_model' => fake()->word(),
            'status' => 'Καλή',
            'year_of_purchase' => fake()->numberBetween(2010, 2026),
            'value' => fake()->randomFloat(2, 10, 5000),
            'source_of_funding' => 'Τακτικός Προϋπολογισμός',
            'comments' => null,
            'file_path' => null,
            'given_away' => false,
        ];
    }
}
