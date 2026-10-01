<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ClassRoom extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'photo',
        'grade',
    ];

    public function getPhotoAttribute($value)
    {
        if (!$value) {
            return null;
        }

        return url(Storage::url($value));
    }

    public function classStudents(): HasMany
    {
        return $this->hasMany(ClassStudent::class);
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function classroomActivities(): HasMany
    {
        return $this->hasMany(ClassroomActivity::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
