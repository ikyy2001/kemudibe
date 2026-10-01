<?php

namespace Database\Factories;

use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Topic>
 */
class TopicFactory extends Factory
{
    protected $model = Topic::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $topicNames = [
            'Mathematics', 'Physics', 'Chemistry', 'Biology', 'Computer Science',
            'Literature', 'History', 'Geography', 'Economics', 'Psychology',
            'Philosophy', 'Art', 'Music', 'Sports', 'Engineering',
            'Medicine', 'Law', 'Business', 'Marketing', 'Finance',
            'Statistics', 'Calculus', 'Algebra', 'Geometry', 'Trigonometry',
            'Programming', 'Databases', 'Networks', 'Security', 'AI',
            'Machine Learning', 'Data Science', 'Web Development', 'Mobile Development',
            'Game Development', 'Robotics', 'Electronics', 'Mechanics'
        ];

        $uniqueId = uniqid() . mt_rand(1000, 9999);
        $baseName = $this->faker->randomElement($topicNames);
        $name = $baseName . ' ' . $uniqueId;
        
        return [
            'name' => $name,
            'about' => $this->faker->paragraph(3),
            'photo' => fake()->imageUrl(600, 400, 'education'),
        ];
    }

    /**
     * Create a child topic with parent_id
     */
    public function withParent($parentId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
        ]);
    }
}
