<?php

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class AuthService
{
    public function attemptLogin(array $credentials, bool $remember = false): bool
    {
        return Auth::attempt($credentials, $remember);
    }

    public function register(array $data): User
    {
        /** Public sign-up is always a standard user — admin roles are never granted here. */
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $roleName = 'user';
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user->assignRole($roleName);

        return $user;
    }

    public function loginUser(User $user): void
    {
        Auth::login($user);
    }

    public function logout(): void
    {
        Auth::logout();
    }
}
