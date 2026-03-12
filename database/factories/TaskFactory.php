<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'server_id' => Server::factory(),
            'ssh_user' => 'root',
            'script' => 'echo "test"',
            'status' => 'pending',
            'exit_code' => null,
            'output' => null,
            'timeout' => 60,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'finished',
            'exit_code' => 0,
            'output' => 'Success',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'finished',
            'exit_code' => 1,
            'output' => 'Error: command failed',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);
    }

    public function timedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'timeout',
            'exit_code' => 124,
            'output' => '',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);
    }
}
