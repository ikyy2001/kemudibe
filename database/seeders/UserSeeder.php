<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        $this->command->info('Creating 1000 users in batches...');
        
        $batchSize = 50;
        $totalUsers = 1000;
        $batches = ceil($totalUsers / $batchSize);
        
        // Create specific admin users first
        $adminUser = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@jawara.com',
            'password' => bcrypt('password'),
            'photo' => fake()->imageUrl(400, 400, 'people'),
            'gender' => 'male',
            'email_verified_at' => now(),
        ]);
        $adminUser->assignRole('manager');
        
        $teacherUser = User::create([
            'name' => 'Sample Teacher',
            'email' => 'teacher@jawara.com',
            'password' => bcrypt('password'),
            'photo' => fake()->imageUrl(400, 400, 'people'),
            'gender' => 'female',
            'email_verified_at' => now(),
        ]);
        $teacherUser->assignRole('teacher');
        
        $studentUser = User::create([
            'name' => 'Sample Student',
            'email' => 'student@jawara.com',
            'password' => bcrypt('password'),
            'photo' => fake()->imageUrl(400, 400, 'people'),
            'gender' => 'male',
            'email_verified_at' => now(),
        ]);
        $studentUser->assignRole('student');
        
        // Bulk create remaining users
        for ($i = 1; $i <= $batches; $i++) {
            $currentBatchSize = ($i == $batches) ? $totalUsers % $batchSize : $batchSize;
            if ($currentBatchSize == 0) $currentBatchSize = $batchSize;
            
            $users = User::factory($currentBatchSize)->create();
            
            // Assign roles in batches - 10% managers, 20% teachers, 70% students
            $users->each(function ($user, $index) {
                if ($index % 10 == 0) {
                    $user->assignRole('manager');
                } elseif ($index % 5 == 0) {
                    $user->assignRole('teacher');
                } else {
                    $user->assignRole('student');
                }
            });
            
            $this->command->info("Batch {$i}/{$batches} completed - {$currentBatchSize} users created");
            
            // Force garbage collection
            unset($users);
            gc_collect_cycles();
        }
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        $this->command->info('User seeding completed successfully!');
    }
}
