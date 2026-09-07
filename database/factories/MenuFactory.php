<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Menu>
 */
class MenuFactory extends Factory
{
    public function definition(): array
    {
        $slug = fake()->unique()->word();

        return [
            'title' => ucfirst($slug),
            // navigation.blade.php calls route($menu->route) for every Menu row on every
            // authenticated page, so this must always be a real, permanently-registered
            // route name rather than a fabricated one.
            'route' => 'dashboard',
            'route_is' => $slug,
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }
}
