<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Jawara CBT Database Seeding (Basic Mode)...');

        // Run seeders in dependency order
        $this->call([
            RolePermissionSeeder::class,    // 1. Create roles and permissions first
            // UserSeeder::class,           // 2. Commented out - Creates 1000+ users (too much data)
            // TopicSeeder::class,          // 3. Commented out - Creates 1000 topics
            // SubjectSeeder::class,        // 4. Commented out - Creates 1000 subjects
            // ClassRoomSeeder::class,      // 5. Commented out - Creates 1000 classrooms
            // ExamDataSeeder::class,       // 6. Commented out - Creates massive exam data
        ]);

        $this->command->info('Creating basic user accounts...');
        $this->createBasicUsers();

        $this->command->info('');
        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->info('');
        $this->command->info('📊 Summary:');
        $this->command->info('• Users: 3 (1 manager, 1 teacher, 1 student)');
        $this->command->info('• Basic setup ready for testing');
        $this->command->info('');
        $this->command->info('🔐 Test Accounts:');
        $this->command->info('• Manager: admin@jawara.com / password');
        $this->command->info('• Teacher: teacher@jawara.com / password');
        $this->command->info('• Student: student@jawara.com / password');
    }

    /**
     * Create basic user accounts for testing
     */
    private function createBasicUsers(): void
    {
        // Create manager account
        $adminUser = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@jawara.com',
            'password' => bcrypt('password'),
            'photo' => fake()->imageUrl(400, 400, 'people'),
            'gender' => 'male',
            'email_verified_at' => now(),
        ]);
        $adminUser->assignRole('manager');

        // Create teacher account
        $teacherUser = User::create([
            'name' => 'Sample Teacher',
            'email' => 'teacher@jawara.com',
            'password' => bcrypt('password'),
            'photo' => fake()->imageUrl(400, 400, 'people'),
            'gender' => 'female',
            'email_verified_at' => now(),
        ]);
        $teacherUser->assignRole('teacher');

        // Create student account
        $studentUser = User::create([
            'name' => 'Sample Student',
            'email' => 'student@jawara.com',
            'password' => bcrypt('password'),
            'photo' => fake()->imageUrl(400, 400, 'people'),
            'gender' => 'male',
            'email_verified_at' => now(),
        ]);
        $studentUser->assignRole('student');

        $this->command->info('✅ Basic users created successfully!');
    }
}
