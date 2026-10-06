<?php

namespace App\Models\Traits;

use App\Models\Institution;
use App\Models\Scopes\InstitutionScope;
use App\Services\InstitutionContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToInstitution
{
    /**
     * Boot the trait and add global tenant scope + auto-fill institution_id.
     */
    public static function bootBelongsToInstitution(): void
    {
        static::addGlobalScope(new InstitutionScope());

        static::creating(function ($model) {
            /** @var InstitutionContext $context */
            $context = app(InstitutionContext::class);

            if (empty($model->institution_id) && $context->hasInstitution()) {
                $model->institution_id = $context->getId();
            }
        });
    }

    /**
     * Relation to parent institution
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Scope a query to bypass the tenant isolation scope
     */
    public function scopeWithoutTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope(InstitutionScope::class);
    }
}
