<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Kemudi LMS Multi-Institution Database Seeding...');

        $this->call([
            RolePermissionSeeder::class,
            MultiInstitutionSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('✅ Multi-institution database seeding completed successfully!');
        $this->command->info('');
        $this->command->info('🔐 Test Accounts:');
        $this->command->info('• Super Admin: superadmin / superadmin123 (superadmin@kabingroup.my.id)');
        $this->command->info('• Bimbel A PIC: pic.bimbela / password123 (bimbel-a)');
        $this->command->info('• Bimbel A Teacher: guru.bimbela / password123 (bimbel-a)');
        $this->command->info('• Bimbel A Student: siswa.bimbela / password123 (bimbel-a)');
        $this->command->info('• Bimbel B PIC: pic.bimbelb / password123 (bimbel-b)');
        $this->command->info('• Bimbel B Teacher: guru.bimbelb / password123 (bimbel-b)');
        $this->command->info('• Bimbel B Student: siswa.bimbelb / password123 (bimbel-b)');
    }
}
