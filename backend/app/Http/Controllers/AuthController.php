<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create($data); // password di-hash otomatis oleh cast 'hashed'

        Log::info('auth.registered', [
            'actor' => $user->id,
            'action' => 'register',
            'cause' => 'pengguna baru mendaftar',
        ]);

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('spa')->plainTextToken,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            // Tidak mencatat email atau password, dan tidak membedakan "email tak terdaftar"
            // dari "password salah" supaya log tidak membocorkan akun yang ada.
            Log::warning('auth.login_failed', [
                'actor' => 'anonim',
                'action' => 'login',
                'cause' => 'kombinasi email dan password tidak cocok',
            ]);

            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        Log::info('auth.login_succeeded', [
            'actor' => $user->id,
            'action' => 'login',
            'cause' => 'kredensial valid, token dibuat',
        ]);

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('spa')->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }
}