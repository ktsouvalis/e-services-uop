<?php

namespace Database\Factories;

use App\Models\PangolinNewtAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PangolinNewtAgent>
 */
class PangolinNewtAgentFactory extends Factory
{
    protected $model = PangolinNewtAgent::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'ip' => fake()->unique()->ipv4(),
        ];
    }
}
