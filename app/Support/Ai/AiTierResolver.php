<?php

namespace App\Support\Ai;

use App\Models\User;

class AiTierResolver
{
    public const TIER_GUEST = 'guest';
    public const TIER_STUDENT = 'student';
    public const TIER_TEACHER = 'teacher';
    public const TIER_ADMIN = 'admin';

    /**
     * Resolve AI rate limit / usage tier from user instance or fallback.
     */
    public static function resolve(?User $user): string
    {
        if (!$user) {
            return self::TIER_GUEST;
        }

        // Get assigned role names
        $roles = method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->toArray()
            : [];

        if (empty($roles) && !empty($user->role)) {
            $roles = [$user->role];
        }

        // Check for admin level roles: superadmin, pic, manager, admin
        foreach (['superadmin', 'pic', 'manager', 'admin'] as $adminRole) {
            if (in_array($adminRole, $roles, true)) {
                return self::TIER_ADMIN;
            }
        }

        // Check for teacher level roles: teacher, guru
        foreach (['teacher', 'guru'] as $teacherRole) {
            if (in_array($teacherRole, $roles, true)) {
                return self::TIER_TEACHER;
            }
        }

        // Check for student level role: student, siswa
        foreach (['student', 'siswa'] as $studentRole) {
            if (in_array($studentRole, $roles, true)) {
                return self::TIER_STUDENT;
            }
        }

        // Fallback for any unknown role: default to the strictest tier (student)
        return self::TIER_STUDENT;
    }
}
