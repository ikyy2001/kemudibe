<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MassSubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Increase memory limit and disable timeout for this operation
        ini_set('memory_limit', '2G');
        set_time_limit(0);
        
        $currentCount = \App\Models\Subject::count();
        $targetRecords = 50;
        
        if ($currentCount >= $targetRecords) {
            $this->command->info("Already have {$currentCount} subjects. Target reached!");
            return;
        }
        
        $remainingRecords = $targetRecords - $currentCount;
        $this->command->info("Current subjects: {$currentCount}. Need to add: {$remainingRecords} more...");
        
        // Get existing topics and users
        $topics = \App\Models\Topic::pluck('id')->toArray();
        $teachers = \App\Models\User::role('teacher')->pluck('id')->toArray();
        
        if (empty($topics) || empty($teachers)) {
            $this->command->error('Please ensure you have topics and teachers in the database first.');
            return;
        }
        
        $batchSize = 50; // Smaller batch size to avoid memory issues
        $batches = ceil($remainingRecords / $batchSize);
        
        for ($batch = 0; $batch < $batches; $batch++) {
            $subjects = [];
            
            for ($i = 0; $i < $batchSize; $i++) {
                $recordNumber = $currentCount + ($batch * $batchSize) + $i + 1;
                
                if (($batch * $batchSize) + $i >= $remainingRecords) {
                    break;
                }
                
                $subjects[] = [
                    'name' => "Subject " . str_pad($recordNumber, 6, '0', STR_PAD_LEFT),
                    'tagline' => "Learning tagline for subject {$recordNumber}",
                    'about' => "This is a comprehensive description for subject {$recordNumber}. It covers various aspects of learning and provides detailed information about the curriculum, objectives, and expected outcomes for students.",
                    'photo' => 'subjects/default.png',
                    'content' => 'subjects/content/sample-content-' . str_pad($recordNumber, 6, '0', STR_PAD_LEFT) . '.pdf',
                    'topic_id' => $topics[array_rand($topics)],
                    'teacher_id' => $teachers[array_rand($teachers)],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            if (!empty($subjects)) {
                \App\Models\Subject::insert($subjects);
                
                // Clear memory after each batch
                unset($subjects);
                
                $completed = $currentCount + min(($batch + 1) * $batchSize, $remainingRecords);
                $percentage = round(($completed / $targetRecords) * 100, 2);
                $this->command->info("Batch " . ($batch + 1) . "/{$batches} completed. Total progress: {$completed}/{$targetRecords} ({$percentage}%)");
                
                // Small delay to prevent overwhelming the database
                usleep(100000); // 0.1 second
            }
        }
        
        $finalCount = \App\Models\Subject::count();
        $this->command->info("Successfully seeded subjects! Final count: {$finalCount}");
    }
}
