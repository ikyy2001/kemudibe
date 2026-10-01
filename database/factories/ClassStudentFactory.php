<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\ClassRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassStudent>
 */
class ClassStudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => User::factory(),
            'class_room_id' => ClassRoom::factory(),
            'has_passed' => $this->faker->boolean(75),
            'rapport' => $this->faker->randomElement([
                'Excellent performance',
                'Good progress',
                'Average performance', 
                'Needs improvement',
                'Outstanding student',
                'Consistent effort',
                'Shows potential',
                'Regular attendance'
            ]),
        ];
    }
}
