<?php

namespace App\Models\Scopes;

use App\Models\User;
use App\Services\InstitutionContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class InstitutionScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        /** @var InstitutionContext $context */
        $context = app(InstitutionContext::class);

        // If explicitly bypassed (e.g. seeders, superadmin platform jobs)
        if ($context->isBypassed()) {
            return;
        }

        $institutionId = $context->getId();

        if ($institutionId !== null) {
            $builder->where($model->qualifyColumn('institution_id'), $institutionId);
        } else {
            // For User model: during early Sanctum token / session authentication before
            // route middleware runs, allow resolving the authenticated user.
            // ResolveInstitution middleware will then enforce tenant match (403).
            if ($model instanceof User) {
                return;
            }

            // Strict Fail-closed security architecture for all business models
            // (topics, subjects, exams, classrooms, lessons, questions, etc.):
            // Never leak tenant data if query is executed without tenant context!
            $builder->whereRaw('0 = 1');
        }
    }
}
