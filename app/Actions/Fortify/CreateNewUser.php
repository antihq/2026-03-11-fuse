<?php

namespace App\Actions\Fortify;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\SshKeyGenerator;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use ProfileValidationRules;

    public function create(array $input): User
    {
        Validator::make($input, [
            'email' => $this->emailRules(),
        ])->validate();

        $keys = app(SshKeyGenerator::class)->generate(
            'user@'.(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost')
        );

        return User::create([
            'email' => $input['email'],
            'ssh_public_key' => $keys['public'],
            'ssh_private_key' => $keys['private'],
        ]);
    }
}
