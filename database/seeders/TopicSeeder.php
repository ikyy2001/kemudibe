<?php

namespace Database\Seeders;

use App\Models\Topic;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TopicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting to seed 1000 topics...');
        
        // Clear existing topics (disable foreign key checks first)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Topic::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        $batchSize = 500; // Process in batches of 500 to avoid memory exhaustion
        $totalRecords = 1000;
        $batches = ceil($totalRecords / $batchSize);
        
        // Create all topics (no parent-child relationship per ERD)
        $batches = ceil($totalRecords / $batchSize);
        
        for ($i = 0; $i < $batches; $i++) {
            $currentBatchSize = min($batchSize, $totalRecords - ($i * $batchSize));
            
            if ($currentBatchSize <= 0) break;
            
            $topics = Topic::factory($currentBatchSize)->create();
            
            $this->command->info("Topics batch " . ($i + 1) . "/$batches completed ({$currentBatchSize} records)");
            
            // Clear memory
            unset($topics);
            gc_collect_cycles();
        }
        
        $this->command->info('Successfully seeded 1000 topics!');
    }
}
