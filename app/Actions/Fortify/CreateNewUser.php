<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            // all_with_bc: Chrome/Edge reportan IDs legacy (p. ej.
            // 'America/Buenos_Aires', el canónico de CLDR para Argentina)
            // que 'timezone:all' rechaza — y el campo es oculto, así que
            // el registro fallaba en silencio.
            'timezone' => ['nullable', 'string', 'timezone:all_with_bc'],
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'timezone' => $input['timezone'] ?? null,
        ]);
    }
}
