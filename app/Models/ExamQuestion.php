<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamQuestion extends Model
{
    use HasFactory;
    protected $fillable = [
        'subject_exam_id',
        'name',
        'timer',
        'type',
        'points',
    ];

    protected static function booted()
    {
        static::created(function ($question) {
            $question->updateExamTotalPoints();
        });

        static::updated(function ($question) {
            $question->updateExamTotalPoints();
        });

        static::deleted(function ($question) {
            $question->updateExamTotalPoints();
        });
    }

    public function subjectExam(): BelongsTo
    {
        return $this->belongsTo(SubjectExam::class);
    }

    public function questionOptions(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function questionAnswers(): HasMany
    {
        return $this->hasMany(QuestionAnswer::class);
    }

    /**
     * Update the total points for the associated exam
     */
    public function updateExamTotalPoints(): void
    {
        if ($this->subjectExam) {
            $this->subjectExam->calculateTotalPoints();
        }
    }
}
