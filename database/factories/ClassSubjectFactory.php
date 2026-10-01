<?php

namespace Database\Factories;

use App\Models\ClassRoom;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassSubject>
 */
class ClassSubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_room_id' => ClassRoom::factory(),
            'subject_id' => Subject::factory(),
        ];
    }
}
