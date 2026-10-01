<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'topic_id',
        'title',
        'content_type',
        'text_content',
        'attachment',
        'drive_url',
        'video_url',
        'order',
    ];

    public function getAttachmentAttribute($value)
    {
        if (!$value) {
            return null;
        }

        return url(Storage::url($value));
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
