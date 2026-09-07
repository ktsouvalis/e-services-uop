<?php

namespace Database\Factories;

use App\Models\PangolinRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PangolinRun>
 */
class PangolinRunFactory extends Factory
{
    protected $model = PangolinRun::class;

    public function definition(): array
    {
        return [
            'type' => 'logs',
            'user_id' => User::factory(),
            'status' => 'queued',
            'options' => [],
        ];
    }
}
