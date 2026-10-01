<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassStudentResource extends JsonResource
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
            'student_id' => $this->student_id,
            'class_room_id' => $this->class_room_id,
            'has_passed' => $this->has_passed,
            'rapport' => $this->rapport,
            'student' => new UserResource($this->whenLoaded('student')),
            'class_room' => new ClassRoomResource($this->whenLoaded('classRoom')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}