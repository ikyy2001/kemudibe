<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        AuditLog::log(
            action: 'create_user',
            entityType: User::class,
            entityId: $user->id,
            newValues: [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
            ],
            institutionId: $user->institution_id
        );
    }

    public function updated(User $user): void
    {
        $dirty = $user->getDirty();
        unset($dirty['password'], $dirty['remember_token'], $dirty['updated_at']);

        if (!empty($dirty)) {
            AuditLog::log(
                action: 'update_user',
                entityType: User::class,
                entityId: $user->id,
                oldValues: array_intersect_key($user->getOriginal(), $dirty),
                newValues: $dirty,
                institutionId: $user->institution_id
            );
        }
    }

    public function deleted(User $user): void
    {
        $actorId = auth()->id();
        if ($actorId === $user->id) {
            $actorId = null;
        }

        AuditLog::log(
            action: 'delete_user',
            entityType: User::class,
            entityId: $user->id,
            oldValues: [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
            ],
            institutionId: $user->institution_id,
            userId: $actorId
        );
    }
}
