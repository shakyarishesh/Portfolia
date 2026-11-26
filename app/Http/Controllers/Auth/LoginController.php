<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('admin.auth.login');
    }

    /** Admins and portfolio owners. "login" is an email, a portfolio username or a phone number. */
    public function login(Request $request)
    {
        $request->merge(['login' => trim((string) $request->input('login', $request->input('email')))]);
        $data = $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->findUser($data['login']);
        if (! $user || ! Hash::check($data['password'], $user->password) || Gate::forUser($user)->denies('portal')) {
            throw ValidationException::withMessages(['login' => 'These details do not match an account.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    private function findUser(string $login): ?User
    {
        if (str_contains($login, '@')) {
            return User::query()->where('email', $login)->first();
        }
        if (preg_match('/^\+?[\d\s().-]{6,}$/', $login)) {
            return User::query()->where('phone', User::normalizePhone($login))->first();
        }

        return Portfolio::query()->where('username', mb_strtolower($login))->first()?->user;
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
