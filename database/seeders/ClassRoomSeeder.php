<?php

namespace Database\Seeders;

use App\Models\ClassRoom;
use Illuminate\Database\Seeder;

class ClassRoomSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating 1000 classrooms in batches...');
        
        $batchSize = 50;
        $totalRecords = 1000;
        $batches = ceil($totalRecords / $batchSize);
        
        for ($i = 0; $i < $batches; $i++) {
            $currentBatchSize = min($batchSize, $totalRecords - ($i * $batchSize));
            
            if ($currentBatchSize <= 0) break;
            
            $classrooms = ClassRoom::factory($currentBatchSize)->create();
            
            $this->command->info("ClassRooms batch " . ($i + 1) . "/$batches completed ({$currentBatchSize} records)");
            
            unset($classrooms);
            gc_collect_cycles();
        }
        
        $this->command->info('ClassRoom seeding completed successfully!');
    }
}
