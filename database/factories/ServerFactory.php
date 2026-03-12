<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Production', 'Staging', 'Development']).' Server',
            'ip_address' => fake()->ipv4(),
            'ram_mb' => fake()->randomElement([1024, 2048, 4096, 8192]),
            'sites_user' => 'deploy',
            'authorized_keys' => null,
            'provision_token' => str()->random(64),
            'mysql_root_password' => str()->password(32, letters: true, numbers: true, symbols: false),
            'deploy_user_password' => str()->password(32, letters: true, numbers: true, symbols: false),
            'provisioned_at' => now(),
        ];
    }

    public function unprovisioned(): static
    {
        return $this->state(fn (array $attributes) => [
            'provisioned_at' => null,
        ]);
    }
}
