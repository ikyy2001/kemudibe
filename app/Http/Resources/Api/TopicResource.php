<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopicResource extends JsonResource
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
            'name' => $this->name,
            'about' => $this->about,
            'photo' => $this->photo,
            'order' => $this->order ?? 1,
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
            'subject_exams' => SubjectExamResource::collection($this->whenLoaded('subjectExams')),
            'subjects_count' => $this->whenCounted('subjects'),
            'subjects' => SubjectResource::collection($this->whenLoaded('subjects')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}