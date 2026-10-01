<?php

namespace Database\Factories;

use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subjects = [
            'Advanced Mathematics', 'Linear Algebra', 'Calculus I', 'Calculus II', 'Statistics',
            'Physics I', 'Physics II', 'Quantum Physics', 'Thermodynamics', 'Mechanics',
            'Chemistry I', 'Organic Chemistry', 'Biochemistry', 'Physical Chemistry',
            'Biology I', 'Microbiology', 'Genetics', 'Molecular Biology', 'Anatomy',
            'Computer Programming', 'Data Structures', 'Algorithms', 'Database Systems',
            'Web Development', 'Mobile Development', 'Machine Learning', 'AI Fundamentals',
            'Literature I', 'Creative Writing', 'Poetry Analysis', 'Modern Literature',
            'World History', 'Ancient History', 'Political Science', 'Economics I',
            'Microeconomics', 'Macroeconomics', 'Business Management', 'Marketing',
            'Accounting', 'Finance', 'Psychology I', 'Social Psychology', 'Philosophy'
        ];
        
        $uniqueId = uniqid() . mt_rand(1000, 9999);
        $baseName = $this->faker->randomElement($subjects);
        $name = $baseName . ' ' . $uniqueId;
        
        return [
            'name' => $name,
            'tagline' => $this->faker->sentence(6),
            'photo' => fake()->imageUrl(600, 400, 'education'),
            'content' => 'subjects/content/sample-content-' . $uniqueId . '.pdf',
            'about' => $this->faker->paragraph(4),
            'topic_id' => Topic::factory(),
            'teacher_id' => User::factory(),
        ];
    }
}
