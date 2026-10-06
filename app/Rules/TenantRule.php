<?php

namespace App\Rules;

use App\Services\InstitutionContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class TenantRule
{
    /**
     * Reserved slugs that cannot be used by institutions
     */
    public const RESERVED_SLUGS = [
        'superadmin',
        'admin',
        'api',
        'login',
        'logout',
        'dashboard',
        'exams',
        'about',
        'features',
        'pricing',
        'changelog',
        'cbt-lms',
        'unauthorized',
        'static',
        'assets',
        'storage',
        'public',
        'www',
    ];

    /**
     * Build an exists rule scoped to the active institution
     */
    public static function exists(string $table, string $column = 'id', ?int $institutionId = null): Exists
    {
        $tenantId = $institutionId ?? app(InstitutionContext::class)->getId();

        return Rule::exists($table, $column)->where(function ($query) use ($tenantId) {
            if ($tenantId !== null) {
                $query->where('institution_id', $tenantId);
            } else {
                $query->whereRaw('0 = 1');
            }
        });
    }

    /**
     * Build a unique rule scoped to the active institution
     */
    public static function unique(string $table, string $column = 'name', mixed $ignoreId = null, ?int $institutionId = null): Unique
    {
        $tenantId = $institutionId ?? app(InstitutionContext::class)->getId();

        $rule = Rule::unique($table, $column)->where(function ($query) use ($tenantId) {
            if ($tenantId !== null) {
                $query->where('institution_id', $tenantId);
            } else {
                $query->whereRaw('0 = 1');
            }
        });

        if ($ignoreId !== null) {
            $rule->ignore($ignoreId);
        }

        return $rule;
    }

    /**
     * Check if a slug is in the reserved list
     */
    public static function isReservedSlug(?string $slug): bool
    {
        if (empty($slug)) {
            return false;
        }

        return in_array(strtolower(trim($slug)), self::RESERVED_SLUGS, true);
    }
}
