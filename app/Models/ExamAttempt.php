<?php

namespace App\Models;

use App\Models\Traits\BelongsToInstitution;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttempt extends Model
{
    use HasFactory, BelongsToInstitution;

    protected $fillable = [
        'institution_id',
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
        'violation_score',
        'max_violation_score',
        'is_frozen',
        'frozen_at',
        'freeze_count',
        'last_heartbeat_at',
        'offline_gaps_count',
        'total_offline_seconds',
        'forced_reason',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'has_passed' => 'boolean',
        'is_frozen' => 'boolean',
        'completed_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'frozen_at' => 'datetime',
        'violation_count' => 'integer',
        'max_violations' => 'integer',
        'violation_score' => 'float',
        'max_violation_score' => 'float',
        'freeze_count' => 'integer',
        'offline_gaps_count' => 'integer',
        'total_offline_seconds' => 'integer',
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

    public function questionAnswers()
    {
        return $this->hasMany(QuestionAnswer::class);
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
