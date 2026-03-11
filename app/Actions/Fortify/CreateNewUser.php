<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\SshKeyGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function create(array $input): User
    {
        return DB::transaction(function () use ($input) {
            Validator::make($input, [
                ...$this->profileRules(),
                'password' => $this->passwordRules(),
            ])->validate();

            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $team = $user->team()->create(['name' => $user->name]);

            $keys = app(SshKeyGenerator::class)->generate(
                'team-'.$team->id.'@'.(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost')
            );

            $team->update([
                'ssh_public_key' => $keys['public'],
                'ssh_private_key' => $keys['private'],
            ]);

            return $user;
        });
    }
}
