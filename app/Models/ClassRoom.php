<?php

namespace App\Models;

use App\Models\Traits\BelongsToInstitution;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ClassRoom extends Model
{
    use HasFactory, BelongsToInstitution;

    protected $fillable = [
        'institution_id',
        'name',
        'photo',
        'grade',
    ];

    public function getPhotoAttribute($value)
    {
        if (!$value) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) || str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, 'data:')) {
            return $value;
        }

        return Storage::disk('public')->url($value);
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
