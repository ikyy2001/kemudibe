<?php

namespace Tests\Unit\Support\Ai;

use App\Models\User;
use App\Support\Ai\AiTierResolver;
use Tests\TestCase;

class AiTierResolverTest extends TestCase
{
    public function test_null_user_resolves_to_guest(): void
    {
        $this->assertEquals(AiTierResolver::TIER_GUEST, AiTierResolver::resolve(null));
    }

    public function test_student_role_resolves_to_student(): void
    {
        $user = new User();
        $user->role = 'student';

        $this->assertEquals(AiTierResolver::TIER_STUDENT, AiTierResolver::resolve($user));
    }

    public function test_teacher_role_resolves_to_teacher(): void
    {
        $user = new User();
        $user->role = 'teacher';

        $this->assertEquals(AiTierResolver::TIER_TEACHER, AiTierResolver::resolve($user));
    }

    public function test_admin_roles_resolve_to_admin(): void
    {
        foreach (['superadmin', 'pic', 'manager', 'admin'] as $role) {
            $user = new User();
            $user->role = $role;
            $this->assertEquals(AiTierResolver::TIER_ADMIN, AiTierResolver::resolve($user), "Role {$role} should resolve to admin tier");
        }
    }

    public function test_unknown_role_resolves_to_strictest_student_tier(): void
    {
        $user = new User();
        $user->role = 'some_unknown_external_role';

        $this->assertEquals(AiTierResolver::TIER_STUDENT, AiTierResolver::resolve($user));
    }
}
