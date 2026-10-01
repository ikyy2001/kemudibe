<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassroomActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_room_id',
        'type',
        'title',
        'description',
        'difficulty',
        'points',
        'status',
        'due_date',
        'attachment',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'points' => 'integer',
    ];

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
