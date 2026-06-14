<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordResetRequest;
use App\Http\Requests\Auth\ForgotPasswordVerifyRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

    // =============================
    // LOGIN
    // =============================
    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|number', 
            'password' => 'required|string',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Kredensial tidak valid.'],
            ]);
        }

        $deviceName = $request->header('User-Agent', 'unknown-device');
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('auth.login'),
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

    // =============================
    // LOGOUT
    // =============================

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => __('auth.logout'),
        ]);
    }

    // =============================
    // Reset Password (sudah login)
    // =============================

    public function resetPassword(ResetPasswordRequest $request)
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Hapus semua token kecuali yang sedang dipakai
        // agar device lain logout otomatis
        $currentToken = $user->currentAccessToken();
        // Hanya hapus token jika bukan TransientToken (dari actingAs)
        if ($currentToken && isset($currentToken->id)) {
            $user->tokens()->where('id', '!=', $currentToken->id)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => __('auth.password_reset_success'),
        ]);
    }

    // =============================
    // Forgot Password — Step 1: Verify Username
    // =============================

    public function forgotPasswordVerify(ForgotPasswordVerifyRequest $request)
    {
        $user = User::where('username', $request->username)->firstOrFail();

        // Hapus token reset lama jika ada
        $user->tokens()->where('name', 'password-reset')->delete();

        // Buat token Sanctum dengan ability terbatas, expire 15 menit
        $token = $user->createToken(
            'password-reset',
            ['password:reset'],
            now()->addMinutes(15)
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('auth.forgot_password_verified'),
            'data'    => [
                'reset_token' => $token,
                'expires_in'  => '15 minutes',
            ],
        ]);
    }

    // =============================
    // Forgot Password — Step 2: Reset Password
    // =============================

    public function forgotPasswordReset(ForgotPasswordResetRequest $request)
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        // Pastikan token punya ability password:reset
        // Cek bahwa token memiliki ability dengan tepat
        $abilities = $currentToken->abilities ?? [];
        if (!in_array('password:reset', $abilities)) {
            return response()->json([
                'success' => false,
                'message' => __('auth.invalid_reset_token'),
                'code'    => 403,
            ], 403);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Hapus semua token (reset + login lama)
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => __('auth.password_reset_success'),
        ]);
    }
}