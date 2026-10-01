<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassRoom>
 */
class ClassRoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $classNames = [
            'Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta',
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',
            'Science', 'Arts', 'Commerce', 'Engineering', 'Medical', 'Law',
            'Morning', 'Evening', 'Advanced', 'Basic', 'Honor'
        ];
        
        // Use uniqid() with microtime for guaranteed uniqueness
        $uniqueId = uniqid() . mt_rand(1000, 9999);
        $baseName = $this->faker->randomElement($classNames);
        $name = 'Class ' . $baseName . ' ' . $uniqueId;
        
        return [
            'name' => $name,
            'photo' => fake()->imageUrl(600, 400, 'business'),
            'grade' => $this->faker->numberBetween(1, 12),
        ];
    }
}
