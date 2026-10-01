<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject_id' => $this->subject_id,
            'topic_id' => $this->topic_id,
            'title' => $this->title,
            'content_type' => $this->content_type,
            'text_content' => $this->text_content,
            'attachment' => $this->attachment,
            'drive_url' => $this->drive_url,
            'video_url' => $this->video_url,
            'order' => $this->order,
            'topic' => new TopicResource($this->whenLoaded('topic')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
