<?php

namespace Database\Factories;

use App\Models\PangolinNewtAgent;
use App\Models\PangolinNewtConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PangolinNewtConnection>
 */
class PangolinNewtConnectionFactory extends Factory
{
    protected $model = PangolinNewtConnection::class;

    public function definition(): array
    {
        return [
            'newt_agent_id' => PangolinNewtAgent::factory(),
            'agent_name' => fake()->word(),
            'agent_ip' => fake()->ipv4(),
            'session_id' => fake()->unique()->uuid(),
            'resource_id' => fake()->numberBetween(1, 100),
            'proto' => 'tcp',
            'src_ip' => fake()->ipv4(),
            'src_port' => (string) fake()->numberBetween(1024, 65535),
            'dst_ip' => fake()->ipv4(),
            'dst_port' => '22',
            'started_at' => now(),
            'ended_at' => now()->addMinutes(5),
        ];
    }
}
