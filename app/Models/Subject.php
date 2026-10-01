<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Subject extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'code',
        'passing_grade',
        'tagline',
        'photo',
        'content',
        'drive_url',
        'about',
        'topic_id',
        'teacher_id',
        'status',
    ];

    public function getPhotoAttribute($value)
    {
        if (!$value) {
            return null;
        }

        return url(Storage::url($value));
    }

    public function getContentAttribute($value)
    {
        if (!$value) {
            return null;
        }

        return url(Storage::url($value));
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class, 'subject_id')->orderBy('order', 'asc');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'subject_id')->orderBy('order', 'asc');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function subjectExams(): HasMany
    {
        return $this->hasMany(SubjectExam::class);
    }
}
