<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
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
            'phone' => 'required|string', // ← ganti 'number' ke 'string'
            'password' => 'required|string',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['Kredensial tidak valid.'], // ← ganti 'username' ke 'phone'
            ]);
        }

        $blockedRoles = [Role::MARKETING, Role::MARKETING_LEAD];

        if (in_array($user->role, $blockedRoles)) {
            throw ValidationException::withMessages([
                'phone' => ['Akun Anda tidak memiliki akses login.'],
            ]);
        }

        // Cek user aktif
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'phone' => ['Akun tidak aktif. Hubungi administrator.'],
            ]);
        }

        $deviceName = $request->header('User-Agent', 'unknown-device');
        $user->tokens()->where('name', $deviceName)->delete();

        if (in_array($user->role, [Role::MANDOR, Role::KEPALA_MANDOR])) {
            $user->load(['subCompanies' => fn ($query) => $query->where('is_active', true)->orderBy('name')]);
        }

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

        $currentToken = $user->currentAccessToken();
        if ($currentToken && isset($currentToken->id)) {
            $user->tokens()->where('id', '!=', $currentToken->id)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => __('auth.password_reset_success'),
        ]);
    }

    // =============================
    // Forgot Password — Step 1: Verify Phone
    // =============================

    public function forgotPasswordVerify(ForgotPasswordVerifyRequest $request)
    {
        $user = User::where('phone', $request->phone)->firstOrFail(); // ← ganti username ke phone

        $user->tokens()->where('name', 'password-reset')->delete();

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

        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => __('auth.password_reset_success'),
        ]);
    }
}