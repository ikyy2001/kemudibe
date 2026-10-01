<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating 1000 subjects in batches...');
        
        $topicIds = Topic::pluck('id')->toArray();
        $teacherIds = User::role('teacher')->pluck('id')->toArray();
        
        if (empty($topicIds) || empty($teacherIds)) {
            $this->command->error('Need topics and teachers to create subjects!');
            return;
        }
        
        $batchSize = 50;
        $totalRecords = 1000;
        $batches = ceil($totalRecords / $batchSize);
        
        for ($i = 0; $i < $batches; $i++) {
            $currentBatchSize = min($batchSize, $totalRecords - ($i * $batchSize));
            
            if ($currentBatchSize <= 0) break;
            
            $subjects = Subject::factory($currentBatchSize)->create([
                'topic_id' => fake()->randomElement($topicIds),
                'teacher_id' => fake()->randomElement($teacherIds),
            ]);
            
            $this->command->info("Subjects batch " . ($i + 1) . "/$batches completed ({$currentBatchSize} records)");
            
            unset($subjects);
            gc_collect_cycles();
        }
        
        $this->command->info('Subject seeding completed successfully!');
    }
}
