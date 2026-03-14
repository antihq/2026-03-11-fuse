<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'hostname' => fake()->domainName(),
            'php_version' => '8.4',
            'size' => 'large',
            'repository_url' => 'git@github.com:user/repo.git',
            'repository_branch' => 'main',
            'hook_before_updating_repository' => null,
            'hook_after_updating_repository' => null,
            'status' => 'pending',
        ];
    }
}
