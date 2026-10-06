<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Institution extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'logo',
        'contact_name',
        'contact_phone',
        'contact_email',
        'status',
        'starts_at',
        'expires_at',
        'max_students',
        'max_storage_mb',
        'storage_used_bytes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'max_students' => 'integer',
        'max_storage_mb' => 'integer',
        'storage_used_bytes' => 'integer',
    ];

    public function getLogoAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) || str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, 'data:')) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(ClassRoom::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isReadOnly(): bool
    {
        return $this->isSuspended() || $this->isExpired();
    }

    public function getActiveStudentsCount(): int
    {
        return User::withoutTenant()
            ->where('institution_id', $this->id)
            ->role('student')
            ->count();
    }

    public function canAddStudents(int $count = 1): bool
    {
        if ($this->max_students <= 0) {
            return true;
        }

        return ($this->getActiveStudentsCount() + $count) <= $this->max_students;
    }

    public function hasReachedStorageQuota(int $incomingBytes = 0): bool
    {
        if ($this->max_storage_mb <= 0) {
            return false;
        }

        $maxBytes = $this->max_storage_mb * 1024 * 1024;
        return ($this->storage_used_bytes + $incomingBytes) > $maxBytes;
    }

    public function recordStorageAdded(int $bytes): void
    {
        if ($bytes > 0) {
            $this->increment('storage_used_bytes', $bytes);
        }
    }

    public function recordStorageFreed(int $bytes): void
    {
        if ($bytes > 0) {
            $current = $this->storage_used_bytes;
            $decrement = min($current, $bytes);
            $this->decrement('storage_used_bytes', $decrement);
        }
    }
}
