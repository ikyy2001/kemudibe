<?php

namespace App\Repositories;

use App\Http\Resources\Api\UserResource;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthRepository
{
    /**
     * Tenant user login (SPA session & token)
     */
    public function login(array $data)
    {
        $institutionCode = strtolower(trim($data['institution_code']));
        $username = strtolower(trim($data['username']));
        $password = $data['password'];
        $remember = (bool) ($data['remember'] ?? false);

        $throttleKey = "login:{$institutionCode}:{$username}:" . request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
                'code' => 'TOO_MANY_ATTEMPTS',
            ], 429);
        }

        // 1. Locate institution by slug
        $institution = Institution::where('slug', $institutionCode)->first();
        if (!$institution) {
            RateLimiter::hit($throttleKey, 60);
            return response()->json([
                'message' => 'Kode lembaga atau kredensial yang dimasukkan tidak cocok.',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        // 2. Locate user within institution (by username or email)
        $user = User::withoutTenant()
            ->where('institution_id', $institution->id)
            ->where(function ($query) use ($username) {
                $query->where('username', $username)
                      ->orWhere('email', $username);
            })
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            return response()->json([
                'message' => 'Kode lembaga atau kredensial yang dimasukkan tidak cocok.',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        // 3. Perform login
        Auth::login($user, $remember);
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('API Token')->plainTextToken;

        AuditLog::log(
            action: 'login',
            entityType: User::class,
            entityId: $user->id,
            newValues: ['username' => $user->username, 'institution' => $institution->slug],
            institutionId: $institution->id,
            userId: $user->id
        );

        return response()->json([
            'message' => 'Login berhasil',
            'token' => $token,
            'institution' => [
                'id' => $institution->id,
                'slug' => $institution->slug,
                'name' => $institution->name,
                'status' => $institution->status,
                'is_read_only' => $institution->isReadOnly(),
            ],
            'user' => new UserResource($user->load(['roles', 'institution'])),
            'must_change_password' => (bool) $user->must_change_password,
        ]);
    }

    /**
     * Token login alias
     */
    public function tokenLogin(array $data)
    {
        return $this->login($data);
    }

    /**
     * Super Admin login (independent from institution)
     */
    public function superAdminLogin(array $data)
    {
        $username = strtolower(trim($data['username']));
        $password = $data['password'];
        $remember = (bool) ($data['remember'] ?? false);

        $throttleKey = "superadmin_login:{$username}:" . request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
                'code' => 'TOO_MANY_ATTEMPTS',
            ], 429);
        }

        // Find platform superadmin (institution_id is NULL)
        $user = User::withoutTenant()
            ->whereNull('institution_id')
            ->where(function ($query) use ($username) {
                $query->where('username', $username)
                      ->orWhere('email', $username);
            })
            ->first();

        if (!$user || !$user->hasRole('superadmin') || !Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            return response()->json([
                'message' => 'Kredensial Super Admin tidak valid.',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, $remember);
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('SuperAdmin Token')->plainTextToken;

        AuditLog::log(
            action: 'superadmin_login',
            entityType: User::class,
            entityId: $user->id,
            newValues: ['username' => $user->username],
            institutionId: null,
            userId: $user->id
        );

        return response()->json([
            'message' => 'Login Super Admin berhasil',
            'token' => $token,
            'user' => new UserResource($user->load(['roles'])),
        ]);
    }

    /**
     * Update user password (mandatory or self-service)
     */
    public function changePassword(User $user, array $data)
    {
        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Password lama yang dimasukkan tidak sesuai.',
                'errors' => [
                    'current_password' => ['Password lama tidak cocok.']
                ]
            ], 422);
        }

        $user->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ]);

        AuditLog::log(
            action: 'change_password',
            entityType: User::class,
            entityId: $user->id,
            institutionId: $user->institution_id,
            userId: $user->id
        );

        return response()->json([
            'message' => 'Password berhasil diperbarui.',
            'must_change_password' => false,
        ]);
    }
}
