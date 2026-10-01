<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttempt extends Model
{
    use HasFactory;
    protected $fillable = [
        'student_id',
        'subject_exam_id',
        'total_attempts',
        'is_completed',
        'total_questions',
        'answered_questions',
        'total_points',
        'points_earned',
        'has_passed',
        'completed_at',
        'current_exam_device_token',
        'last_activity_at',
        'violation_count',
        'max_violations',
        'forced_reason',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'has_passed' => 'boolean',
        'completed_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'violation_count' => 'integer',
        'max_violations' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function subjectExam(): BelongsTo
    {
        return $this->belongsTo(SubjectExam::class);
    }

    public function violations()
    {
        return $this->hasMany(ExamViolation::class);
    }

    /**
     * Calculate score percentage
     */
    public function getScorePercentageAttribute(): float
    {
        if ($this->total_points > 0) {
            return round(($this->points_earned / $this->total_points) * 100, 2);
        }
        return 0.0;
    }
}
