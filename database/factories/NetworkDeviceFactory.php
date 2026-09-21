<?php

namespace Database\Factories;

use App\Models\NetworkDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NetworkDevice>
 */
class NetworkDeviceFactory extends Factory
{
    protected $model = NetworkDevice::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().'_SW_1',
            'mgmt_ip' => fake()->unique()->ipv4(),
            'vendor' => 'huawei',
            'role' => 'l2',
            'enabled' => true,
        ];
    }

    public function core(): static
    {
        return $this->state(fn () => ['role' => 'core']);
    }

    public function cisco(): static
    {
        return $this->state(fn () => ['vendor' => 'cisco']);
    }

    public function telnet(): static
    {
        return $this->state(fn () => ['protocol' => 'telnet']);
    }
}
