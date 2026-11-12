<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    // Форма регистрации
    public function showRegister(): View
    {
        return view('auth.register');
    }

    // Сохранение нового пользователя
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect('/login');
    }

    // Форма входа
    public function showLogin(): View
    {
        return view('auth.login');
    }

    // Обработка входа
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'Неверный email или пароль']);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $token = $user->createToken('auth-token')->plainTextToken;
        $request->session()->put('auth_token', $token);

        return redirect()->intended('/');
    }

    // Выход
    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            $user->tokens()->delete(); // удаляем все токены пользователя
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
