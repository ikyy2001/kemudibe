<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassRoomResource extends JsonResource
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
            'name' => $this->name,
            'photo' => $this->photo,
            'grade' => $this->grade,
            'class_students_count' => $this->whenCounted('classStudents'),
            'class_subjects_count' => $this->whenCounted('classSubjects'),
            'class_students' => ClassStudentResource::collection($this->whenLoaded('classStudents')),
            'class_subjects' => ClassSubjectResource::collection($this->whenLoaded('classSubjects')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}