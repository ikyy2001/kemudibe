<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\SubjectExam;

class SubjectExamObserver
{
    public function created(SubjectExam $exam): void
    {
        AuditLog::log(
            action: 'create_exam',
            entityType: SubjectExam::class,
            entityId: $exam->id,
            newValues: [
                'name' => $exam->name,
                'subject_id' => $exam->subject_id,
                'duration_minutes' => $exam->duration_minutes,
            ],
            institutionId: $exam->institution_id
        );
    }

    public function updated(SubjectExam $exam): void
    {
        $dirty = $exam->getDirty();
        unset($dirty['updated_at']);

        if (!empty($dirty)) {
            AuditLog::log(
                action: 'update_exam',
                entityType: SubjectExam::class,
                entityId: $exam->id,
                oldValues: array_intersect_key($exam->getOriginal(), $dirty),
                newValues: $dirty,
                institutionId: $exam->institution_id
            );
        }
    }

    public function deleted(SubjectExam $exam): void
    {
        AuditLog::log(
            action: 'delete_exam',
            entityType: SubjectExam::class,
            entityId: $exam->id,
            oldValues: ['name' => $exam->name],
            institutionId: $exam->institution_id
        );
    }
}
