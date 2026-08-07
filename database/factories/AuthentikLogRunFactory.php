<?php

namespace Database\Factories;

use App\Models\AuthentikLogRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuthentikLogRun>
 */
class AuthentikLogRunFactory extends Factory
{
    protected $model = AuthentikLogRun::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'queued',
            'options' => [],
        ];
    }
}
