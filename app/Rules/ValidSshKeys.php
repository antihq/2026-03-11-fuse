<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidSshKeys implements ValidationRule
{
    protected array $validPrefixes = [
        'ssh-rsa',
        'ssh-ed25519',
        'ecdsa-sha2-nistp256',
        'ecdsa-sha2-nistp384',
        'ecdsa-sha2-nistp521',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $lines = collect(explode("\n", $value))->filter(fn ($line) => ! empty(trim($line)));

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $isValid = collect($this->validPrefixes)->contains(fn ($prefix) => str_starts_with($trimmed, $prefix));

            if (! $isValid) {
                $fail("Invalid SSH key format: {$trimmed}");

                return;
            }
        }
    }
}
