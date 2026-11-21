<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public const ROLES = ['admin', 'manager', 'staff'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
            'role' => ['required', Rule::in(self::ROLES)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * Administrators keep their own role, so nobody can lock themselves out of this screen.
     */
    protected function prepareForValidation(): void
    {
        $user = $this->route('user');

        if ($user instanceof User && $user->is($this->user())) {
            $this->merge(['role' => $user->getRoleNames()->first()]);
        }
    }
}
