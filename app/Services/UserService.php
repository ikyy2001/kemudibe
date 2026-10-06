<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserService
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Get paginated users
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->userRepository->getPaginated($fields, $perPage);
    }

    /**
     * Get all users without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return $this->userRepository->getAll($fields);
    }

    /**
     * Search users by query
     */
    public function searchUsers(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->userRepository->searchByNameAndEmail($query, $fields, $perPage);
    }

    /**
     * Get users by gender
     */
    public function findUsersByGender(string $gender, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->userRepository->findByGender($gender, $fields, $perPage);
    }

    /**
     * Get users by role
     */
    public function findUsersByRole(string $role, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->userRepository->findByRole($role, $fields, $perPage);
    }

    /**
     * Search users with pagination for modal
     */
    public function searchWithPagination(string $query = '', array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        return $this->userRepository->searchWithPagination($query, $fields, $page, $perPage);
    }

    /**
     * Parse an uploaded Excel or CSV file into an array of user rows
     */
    public function parseSpreadsheet(\Illuminate\Http\UploadedFile $file): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, true);

        if (empty($rawRows)) {
            return [];
        }

        // Find header row (usually row 1)
        $headerRow = array_shift($rawRows);
        $headerMap = [];

        foreach ($headerRow as $col => $val) {
            if (!$val) continue;
            $clean = strtolower(trim((string)$val));

            // Kolom kelas/rombel dideteksi lebih dulu agar 'Nama/ID Kelas' tidak menimpa 'Nama Lengkap'
            if (str_contains($clean, 'kelas') || str_contains($clean, 'class') || str_contains($clean, 'rombel')) {
                $headerMap['classroom'] = $col;
            } elseif (str_contains($clean, 'email') || str_contains($clean, 'surel') || str_contains($clean, 'mail')) {
                $headerMap['email'] = $col;
            } elseif (str_contains($clean, 'pass') || str_contains($clean, 'sandi')) {
                $headerMap['password'] = $col;
            } elseif (str_contains($clean, 'gender') || str_contains($clean, 'kelamin') || $clean === 'jk' || str_contains($clean, 'sex')) {
                $headerMap['gender'] = $col;
            } elseif (str_contains($clean, 'role') || str_contains($clean, 'peran') || str_contains($clean, 'jabatan')) {
                $headerMap['role'] = $col;
            } elseif (str_contains($clean, 'nama') || str_contains($clean, 'name')) {
                // Kolom nama siswa/guru: prioritaskan 'nama lengkap' atau kolom pertama yang belum terisi
                if (!isset($headerMap['name']) || str_contains($clean, 'lengkap') || str_contains($clean, 'full')) {
                    $headerMap['name'] = $col;
                }
            }
        }

        // Default positional fallbacks if headers weren't named standardly
        if (!isset($headerMap['name'])) $headerMap['name'] = 'A';
        if (!isset($headerMap['email'])) $headerMap['email'] = 'B';
        if (!isset($headerMap['password'])) $headerMap['password'] = 'C';
        if (!isset($headerMap['gender'])) $headerMap['gender'] = 'D';
        if (!isset($headerMap['role'])) $headerMap['role'] = 'E';
        if (!isset($headerMap['classroom'])) $headerMap['classroom'] = 'F';

        $parsed = [];
        $rowNumber = 1;

        foreach ($rawRows as $row) {
            $rowNumber++;
            $name = isset($headerMap['name'], $row[$headerMap['name']]) ? trim((string)$row[$headerMap['name']]) : '';
            $email = isset($headerMap['email'], $row[$headerMap['email']]) ? trim((string)$row[$headerMap['email']]) : '';

            // Skip completely empty rows
            if (empty($name) && empty($email)) {
                continue;
            }

            $password = isset($headerMap['password'], $row[$headerMap['password']]) ? trim((string)$row[$headerMap['password']]) : '';
            $gender = isset($headerMap['gender'], $row[$headerMap['gender']]) ? trim((string)$row[$headerMap['gender']]) : '';
            $role = isset($headerMap['role'], $row[$headerMap['role']]) ? trim((string)$row[$headerMap['role']]) : '';
            $classroom = isset($headerMap['classroom'], $row[$headerMap['classroom']]) ? trim((string)$row[$headerMap['classroom']]) : '';

            $parsed[] = [
                'row_number' => $rowNumber,
                'name' => $name,
                'email' => $email,
                'password' => $password ?: 'password123',
                'gender' => $gender,
                'role' => $role ?: 'student',
                'classroom' => $classroom,
            ];
        }

        return $parsed;
    }

    /**
     * Process bulk import of accounts
     */
    public function bulkImport(array $accountRows, string $defaultPassword = 'password123'): array
    {
        $imported = [];
        $errors = [];
        $seenEmails = [];

        // Preload classrooms for quick ID/Name matching
        $classrooms = \App\Models\ClassRoom::all();

        foreach ($accountRows as $idx => $row) {
            $rowNum = $row['row_number'] ?? ($idx + 2);
            $name = trim((string)($row['name'] ?? ''));
            $email = strtolower(trim((string)($row['email'] ?? '')));
            $password = trim((string)($row['password'] ?? '')) ?: $defaultPassword;
            $rawGender = strtolower(trim((string)($row['gender'] ?? '')));
            $rawRole = strtolower(trim((string)($row['role'] ?? 'student')));
            $classroomIdentifier = trim((string)($row['classroom'] ?? ''));

            // 1. Validation: Name
            if (empty($name)) {
                $errors[] = [
                    'row' => $rowNum,
                    'email' => $email ?: '-',
                    'reason' => 'Nama lengkap wajib diisi.'
                ];
                continue;
            }

            // 2. Validation: Email
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = [
                    'row' => $rowNum,
                    'email' => $email ?: '-',
                    'reason' => 'Format email tidak valid.'
                ];
                continue;
            }

            // 3. Validation: Duplicate within file
            if (in_array($email, $seenEmails)) {
                $errors[] = [
                    'row' => $rowNum,
                    'email' => $email,
                    'reason' => 'Email duplikat terdeteksi di dalam file yang sama.'
                ];
                continue;
            }

            // 4. Validation: Duplicate in database
            if (\App\Models\User::where('email', $email)->exists()) {
                $errors[] = [
                    'row' => $rowNum,
                    'email' => $email,
                    'reason' => 'Email sudah terdaftar di sistem.'
                ];
                continue;
            }

            // 5. Normalization: Gender
            $gender = 'male';
            if (in_array($rawGender, ['female', 'f', 'p', 'perempuan', 'wanita', 'akhwat'])) {
                $gender = 'female';
            } elseif (in_array($rawGender, ['male', 'm', 'l', 'laki-laki', 'pria', 'ikhwan', 'laki'])) {
                $gender = 'male';
            }

            // 6. Normalization: Role
            $role = 'student';
            if (in_array($rawRole, ['teacher', 'guru', 'pengajar', 'ustadz', 'ustadzah'])) {
                $role = 'teacher';
            } elseif (in_array($rawRole, ['manager', 'admin', 'administrator', 'pic'])) {
                $role = 'pic';
            }

            // 7. Check student quota
            $institution = app(\App\Services\InstitutionContext::class)->get();
            if ($role === 'student' && $institution && !$institution->canAddStudents(1)) {
                $errors[] = [
                    'row' => $rowNum,
                    'email' => $email,
                    'reason' => "Batas kuota siswa untuk lembaga ini telah tercapai ({$institution->max_students} siswa)."
                ];
                continue;
            }

            // Derive username if not present
            $username = isset($row['username']) && !empty($row['username'])
                ? strtolower(trim((string)$row['username']))
                : strtolower(explode('@', $email)[0]);

            // Ensure username uniqueness within tenant
            $originalUsername = $username;
            $counter = 1;
            while (\App\Models\User::where('username', $username)->exists()) {
                $username = $originalUsername . $counter;
                $counter++;
            }

            // Create user safely in transaction
            try {
                \Illuminate\Support\Facades\DB::beginTransaction();

                $user = \App\Models\User::create([
                    'institution_id' => $institution?->id,
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                    'password' => \Illuminate\Support\Facades\Hash::make($password),
                    'must_change_password' => true,
                    'gender' => $gender,
                    'photo' => 'default.png',
                    'email_verified_at' => now(),
                ]);

                $user->assignRole($role);

                // If classroom is provided and role is student, enroll automatically
                if (!empty($classroomIdentifier) && $role === 'student') {
                    $matchedClass = $classrooms->first(function ($c) use ($classroomIdentifier) {
                        return (string)$c->id === $classroomIdentifier 
                            || strcasecmp($c->name, $classroomIdentifier) === 0;
                    });

                    if ($matchedClass) {
                        \App\Models\ClassStudent::firstOrCreate([
                            'class_room_id' => $matchedClass->id,
                            'student_id' => $user->id,
                        ], [
                            'has_passed' => false,
                        ]);
                    }
                }

                \Illuminate\Support\Facades\DB::commit();

                $seenEmails[] = $email;
                $imported[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'plain_password' => $password,
                    'role' => $role,
                    'gender' => $gender,
                ];
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                $errors[] = [
                    'row' => $rowNum,
                    'email' => $email,
                    'reason' => 'Gagal membuat user: ' . $e->getMessage()
                ];
            }
        }

        return [
            'total_rows' => count($accountRows),
            'imported_count' => count($imported),
            'skipped_count' => count($errors),
            'imported_users' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * Generate Excel template as binary response
     */
    public function generateTemplateSpreadsheet(): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Akun');

        // Headers
        $headers = [
            'A1' => 'Nama Lengkap*',
            'B1' => 'Email*',
            'C1' => 'Password (Default)',
            'D1' => 'Jenis Kelamin* (L/P)',
            'E1' => 'Role (student/teacher)',
            'F1' => 'Nama/ID Kelas (Opsional)',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        // Header style
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFEF3F09'], // Brand Orange
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];

        $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Sample Data Rows
        $sampleData = [
            ['Ahmad Fauzi', 'ahmad.fauzi@sekolah.sch.id', 'password123', 'L', 'student', 'Kelas 10-A'],
            ['Siti Aisyah', 'siti.aisyah@sekolah.sch.id', 'password123', 'P', 'student', 'Kelas 10-A'],
            ['Bambang Pamungkas', 'bambang.guru@sekolah.sch.id', 'password123', 'L', 'teacher', ''],
        ];

        $rowIdx = 2;
        foreach ($sampleData as $row) {
            $sheet->setCellValue('A' . $rowIdx, $row[0]);
            $sheet->setCellValue('B' . $rowIdx, $row[1]);
            $sheet->setCellValue('C' . $rowIdx, $row[2]);
            $sheet->setCellValue('D' . $rowIdx, $row[3]);
            $sheet->setCellValue('E' . $rowIdx, $row[4]);
            $sheet->setCellValue('F' . $rowIdx, $row[5]);
            $rowIdx++;
        }

        // Auto width
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}