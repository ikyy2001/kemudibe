<?php

namespace Database\Seeders;

use App\Models\ClassRoom;
use App\Models\ClassStudent;
use App\Models\ClassSubject;
use App\Models\ExamQuestion;
use App\Models\Institution;
use App\Models\Lesson;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\SubjectExam;
use App\Models\Topic;
use App\Models\User;
use App\Services\InstitutionContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MultiInstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $context = app(InstitutionContext::class);
        $context->setBypass(true);

        $this->command->info('Creating Super Admin account...');
        $superadmin = User::create([
            'institution_id' => null,
            'name' => 'Super Administrator',
            'username' => 'superadmin',
            'email' => 'superadmin@kabingroup.my.id',
            'password' => Hash::make('superadmin123'),
            'gender' => 'male',
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        $this->command->info('Creating Institution 1: Bimbel Prestasi Juara (bimbel-a)...');
        $bimbelA = Institution::create([
            'slug' => 'bimbel-a',
            'name' => 'Bimbel Prestasi Juara',
            'contact_name' => 'Budi Santoso (PIC A)',
            'contact_phone' => '081234567890',
            'contact_email' => 'pic@bimbela.com',
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'max_students' => 100,
            'max_storage_mb' => 1024,
            'storage_used_bytes' => 0,
        ]);

        $picA = User::create([
            'institution_id' => $bimbelA->id,
            'name' => 'Budi Santoso',
            'username' => 'pic.bimbela',
            'email' => 'pic@bimbela.com',
            'password' => Hash::make('password123'),
            'gender' => 'male',
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);
        $picA->assignRole(['pic', 'manager']);

        $guruA = User::create([
            'institution_id' => $bimbelA->id,
            'name' => 'Siti Guru Matematika',
            'username' => 'guru.bimbela',
            'email' => 'guru@bimbela.com',
            'password' => Hash::make('password123'),
            'gender' => 'female',
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);
        $guruA->assignRole('teacher');

        $siswaA = User::create([
            'institution_id' => $bimbelA->id,
            'name' => 'Ani Siswa A',
            'username' => 'siswa.bimbela',
            'email' => 'siswa@bimbela.com',
            'password' => Hash::make('password123'),
            'gender' => 'female',
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);
        $siswaA->assignRole('student');

        // Sample academic data for Bimbel A
        $classA = ClassRoom::create([
            'institution_id' => $bimbelA->id,
            'name' => 'Kelas 10 A',
            'grade' => 10,
        ]);

        ClassStudent::create([
            'institution_id' => $bimbelA->id,
            'class_room_id' => $classA->id,
            'student_id' => $siswaA->id,
        ]);

        $subjectA = Subject::create([
            'institution_id' => $bimbelA->id,
            'name' => 'Matematika Dasar',
            'code' => 'MTK-10',
            'passing_grade' => 75,
            'tagline' => 'Belajar Matematika Menyenangkan',
            'about' => 'Materi Matematika untuk Kelas 10',
            'photo' => 'default-subject.png',
            'teacher_id' => $guruA->id,
            'status' => 'published',
        ]);

        ClassSubject::create([
            'institution_id' => $bimbelA->id,
            'class_room_id' => $classA->id,
            'subject_id' => $subjectA->id,
        ]);

        $topicA = Topic::create([
            'institution_id' => $bimbelA->id,
            'subject_id' => $subjectA->id,
            'name' => 'Bab 1: Persamaan Linier',
            'about' => 'Konsep dasar persamaan linier satu variabel',
            'photo' => 'default-topic.png',
            'order' => 1,
        ]);

        Lesson::create([
            'institution_id' => $bimbelA->id,
            'subject_id' => $subjectA->id,
            'topic_id' => $topicA->id,
            'title' => 'Pengenalan Variabel dan Konstanta',
            'content_type' => 'text',
            'text_content' => 'Variabel adalah lambang pengganti suatu bilangan yang belum diketahui nilainya.',
            'order' => 1,
        ]);

        $examA = SubjectExam::create([
            'institution_id' => $bimbelA->id,
            'subject_id' => $subjectA->id,
            'topic_id' => $topicA->id,
            'name' => 'Ulangan Harian 1 MTK',
            'about' => 'Uji pemahaman bab persamaan linier',
            'duration_minutes' => 60,
            'token' => 'MTK123',
            'total_points' => 100,
            'passing_grade' => 75,
            'category' => 'daily_quiz',
            'started_at' => now()->toDateString(),
            'ended_at' => now()->addDays(7)->toDateString(),
        ]);

        $questionA = ExamQuestion::create([
            'institution_id' => $bimbelA->id,
            'subject_exam_id' => $examA->id,
            'name' => 'Berapa nilai x jika 2x + 4 = 10?',
            'type' => 'multiple_choice',
            'timer' => 60,
            'points' => 100,
        ]);

        QuestionOption::create([
            'institution_id' => $bimbelA->id,
            'exam_question_id' => $questionA->id,
            'name' => '3',
            'is_correct' => true,
        ]);
        QuestionOption::create([
            'institution_id' => $bimbelA->id,
            'exam_question_id' => $questionA->id,
            'name' => '4',
            'is_correct' => false,
        ]);


        $this->command->info('Creating Institution 2: Bimbel Cerdas Mandiri (bimbel-b)...');
        $bimbelB = Institution::create([
            'slug' => 'bimbel-b',
            'name' => 'Bimbel Cerdas Mandiri',
            'contact_name' => 'Ahmad Dahlan (PIC B)',
            'contact_phone' => '089876543210',
            'contact_email' => 'pic@bimbelb.com',
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'max_students' => 50,
            'max_storage_mb' => 512,
            'storage_used_bytes' => 0,
        ]);

        $picB = User::create([
            'institution_id' => $bimbelB->id,
            'name' => 'Ahmad Dahlan',
            'username' => 'pic.bimbelb',
            'email' => 'pic@bimbelb.com',
            'password' => Hash::make('password123'),
            'gender' => 'male',
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);
        $picB->assignRole(['pic', 'manager']);

        $guruB = User::create([
            'institution_id' => $bimbelB->id,
            'name' => 'Bambang Guru Fisika',
            'username' => 'guru.bimbelb',
            'email' => 'guru@bimbelb.com',
            'password' => Hash::make('password123'),
            'gender' => 'male',
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);
        $guruB->assignRole('teacher');

        $siswaB = User::create([
            'institution_id' => $bimbelB->id,
            'name' => 'Budi Siswa B',
            'username' => 'siswa.bimbelb',
            'email' => 'siswa@bimbelb.com',
            'password' => Hash::make('password123'),
            'gender' => 'male',
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);
        $siswaB->assignRole('student');

        // Sample academic data for Bimbel B (Note: Same classroom name 'Kelas 10 A' to demonstrate safe isolation!)
        $classB = ClassRoom::create([
            'institution_id' => $bimbelB->id,
            'name' => 'Kelas 10 A',
            'grade' => 10,
        ]);

        ClassStudent::create([
            'institution_id' => $bimbelB->id,
            'class_room_id' => $classB->id,
            'student_id' => $siswaB->id,
        ]);

        $subjectB = Subject::create([
            'institution_id' => $bimbelB->id,
            'name' => 'Fisika Dasar',
            'code' => 'FIS-10',
            'passing_grade' => 70,
            'tagline' => 'Eksplorasi Hukum Alam',
            'about' => 'Materi Fisika untuk Kelas 10',
            'photo' => 'default-subject.png',
            'teacher_id' => $guruB->id,
            'status' => 'published',
        ]);

        ClassSubject::create([
            'institution_id' => $bimbelB->id,
            'class_room_id' => $classB->id,
            'subject_id' => $subjectB->id,
        ]);

        $topicB = Topic::create([
            'institution_id' => $bimbelB->id,
            'subject_id' => $subjectB->id,
            'name' => 'Bab 1: Gerak Lurus Beraturan',
            'about' => 'Konsep kecepatan dan jarak pada GLB',
            'photo' => 'default-topic.png',
            'order' => 1,
        ]);

        Lesson::create([
            'institution_id' => $bimbelB->id,
            'subject_id' => $subjectB->id,
            'topic_id' => $topicB->id,
            'title' => 'Pengenalan Gerak Lurus',
            'content_type' => 'text',
            'text_content' => 'GLB adalah gerak suatu benda pada lintasan lurus dengan kecepatan konstan.',
            'order' => 1,
        ]);

        $examB = SubjectExam::create([
            'institution_id' => $bimbelB->id,
            'subject_id' => $subjectB->id,
            'topic_id' => $topicB->id,
            'name' => 'Ulangan Harian 1 Fisika',
            'about' => 'Uji pemahaman bab GLB',
            'duration_minutes' => 45,
            'token' => 'FIS123',
            'total_points' => 100,
            'passing_grade' => 70,
            'category' => 'daily_quiz',
            'started_at' => now()->toDateString(),
            'ended_at' => now()->addDays(7)->toDateString(),
        ]);

        $questionB = ExamQuestion::create([
            'institution_id' => $bimbelB->id,
            'subject_exam_id' => $examB->id,
            'name' => 'Rumus kecepatan pada GLB adalah...',
            'type' => 'multiple_choice',
            'timer' => 60,
            'points' => 100,
        ]);

        QuestionOption::create([
            'institution_id' => $bimbelB->id,
            'exam_question_id' => $questionB->id,
            'name' => 'v = s / t',
            'is_correct' => true,
        ]);
        QuestionOption::create([
            'institution_id' => $bimbelB->id,
            'exam_question_id' => $questionB->id,
            'name' => 'v = s * t',
            'is_correct' => false,
        ]);

        // Reset bypass
        $context->reset();

        $this->command->info('Multi-Institution seeding complete!');
    }
}
