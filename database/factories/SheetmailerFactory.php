<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sheetmailer>
 */
class SheetmailerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'is_public' => false,
            'name' => fake()->sentence(3),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'signature' => fake()->name(),
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }
}
