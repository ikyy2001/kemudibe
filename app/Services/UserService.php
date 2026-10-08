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

            // 1. Gender / Kelamin dideteksi lebih awal agar kata 'jenis' tidak tertangkap oleh 'nis'
            if (str_contains($clean, 'gender') || str_contains($clean, 'kelamin') || preg_match('/\bjk\b/', $clean) || str_contains($clean, 'sex')) {
                $headerMap['gender'] = $col;
            } 
            // 2. Kolom kelas/rombel dideteksi agar 'Nama/ID Kelas' tidak tertangkap oleh 'Nama Lengkap' atau 'id'
            elseif (str_contains($clean, 'kelas') || str_contains($clean, 'class') || str_contains($clean, 'rombel')) {
                $headerMap['classroom'] = $col;
            } 
            // 3. Email
            elseif (str_contains($clean, 'email') || str_contains($clean, 'surel') || str_contains($clean, 'mail')) {
                $headerMap['email'] = $col;
            } 
            // 4. Password
            elseif (str_contains($clean, 'pass') || str_contains($clean, 'sandi')) {
                $headerMap['password'] = $col;
            } 
            // 5. Role
            elseif (str_contains($clean, 'role') || str_contains($clean, 'peran') || str_contains($clean, 'jabatan')) {
                $headerMap['role'] = $col;
            } 
            // 6. Username / NIS / NIP
            elseif (str_contains($clean, 'username') || preg_match('/\bnis\b/', $clean) || preg_match('/\bnip\b/', $clean) || (preg_match('/\buser\b/', $clean) && !str_contains($clean, 'nama')) || preg_match('/\bid\b/', $clean)) {
                $headerMap['username'] = $col;
            } 
            // 7. Nama Lengkap
            elseif (str_contains($clean, 'nama') || str_contains($clean, 'name')) {
                if (!isset($headerMap['name']) || str_contains($clean, 'lengkap') || str_contains($clean, 'full')) {
                    $headerMap['name'] = $col;
                }
            }
        }

        // Positional fallbacks based on column count
        if (!isset($headerMap['name'])) $headerMap['name'] = 'A';
        if (!isset($headerMap['username']) && count($headerRow) >= 6) {
            $headerMap['username'] = 'B';
            if (!isset($headerMap['email'])) $headerMap['email'] = 'C';
            if (!isset($headerMap['password'])) $headerMap['password'] = 'D';
            if (!isset($headerMap['gender'])) $headerMap['gender'] = 'E';
            if (!isset($headerMap['role'])) $headerMap['role'] = 'F';
            if (!isset($headerMap['classroom']) && count($headerRow) >= 7) $headerMap['classroom'] = 'G';
        } else {
            if (!isset($headerMap['email'])) $headerMap['email'] = 'B';
            if (!isset($headerMap['password'])) $headerMap['password'] = 'C';
            if (!isset($headerMap['gender'])) $headerMap['gender'] = 'D';
            if (!isset($headerMap['role'])) $headerMap['role'] = 'E';
            if (!isset($headerMap['classroom']) && count($headerRow) >= 6) $headerMap['classroom'] = 'F';
        }

        $parsed = [];
        $rowNumber = 1;

        foreach ($rawRows as $row) {
            $rowNumber++;
            $name = isset($headerMap['name'], $row[$headerMap['name']]) ? trim((string)$row[$headerMap['name']]) : '';
            $username = isset($headerMap['username'], $row[$headerMap['username']]) ? trim((string)$row[$headerMap['username']]) : '';
            $email = isset($headerMap['email'], $row[$headerMap['email']]) ? trim((string)$row[$headerMap['email']]) : '';

            // Skip completely empty rows
            if (empty($name) && empty($email) && empty($username)) {
                continue;
            }

            $password = isset($headerMap['password'], $row[$headerMap['password']]) ? trim((string)$row[$headerMap['password']]) : '';
            $gender = isset($headerMap['gender'], $row[$headerMap['gender']]) ? trim((string)$row[$headerMap['gender']]) : '';
            $role = isset($headerMap['role'], $row[$headerMap['role']]) ? trim((string)$row[$headerMap['role']]) : '';
            $classroom = isset($headerMap['classroom'], $row[$headerMap['classroom']]) ? trim((string)$row[$headerMap['classroom']]) : '';

            $parsed[] = [
                'row_number' => $rowNumber,
                'name' => $name,
                'username' => $username ?: null,
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
        $seenUsernames = [];

        // Preload classrooms for quick ID/Name matching
        $classrooms = \App\Models\ClassRoom::all();

        foreach ($accountRows as $idx => $row) {
            $rowNum = $row['row_number'] ?? ($idx + 2);
            $name = trim((string)($row['name'] ?? ''));
            $rawUsername = strtolower(trim((string)($row['username'] ?? '')));
            $email = strtolower(trim((string)($row['email'] ?? '')));
            $password = trim((string)($row['password'] ?? '')) ?: $defaultPassword;
            $rawGender = strtolower(trim((string)($row['gender'] ?? '')));
            $rawRole = strtolower(trim((string)($row['role'] ?? 'student')));
            $classroomIdentifier = trim((string)($row['classroom'] ?? ''));

            // 1. Validation: Name
            if (empty($name)) {
                $errors[] = [
                    'row' => $rowNum,
                    'email' => $email ?: ($rawUsername ?: '-'),
                    'reason' => 'Nama lengkap wajib diisi.'
                ];
                continue;
            }

            // Derive username if not explicitly set
            $username = $rawUsername;
            if (empty($username)) {
                if (!empty($email)) {
                    $username = strtolower(explode('@', $email)[0]);
                } else {
                    $errors[] = [
                        'row' => $rowNum,
                        'email' => '-',
                        'reason' => 'Username atau Email harus diisi.'
                    ];
                    continue;
                }
            }

            // 2. Validation: Username format
            if (!preg_match('/^[a-z0-9._-]+$/', $username)) {
                $errors[] = [
                    'row' => $rowNum,
                    'email' => $username,
                    'reason' => 'Format username tidak valid (hanya huruf kecil, angka, titik, strip, atau underscore).'
                ];
                continue;
            }

            // 3. Validation: Username duplicate in file
            if (in_array($username, $seenUsernames)) {
                $errors[] = [
                    'row' => $rowNum,
                    'email' => $username,
                    'reason' => "Username/NIS/NIP '{$username}' duplikat di dalam file yang sama."
                ];
                continue;
            }

            // 4. Validation: Username duplicate in database (within tenant)
            if (\App\Models\User::where('username', $username)->exists()) {
                $errors[] = [
                    'row' => $rowNum,
                    'email' => $username,
                    'reason' => "Username/NIS/NIP '{$username}' sudah terdaftar di sistem."
                ];
                continue;
            }

            // 5. Validation: Email (if provided)
            if (!empty($email)) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = [
                        'row' => $rowNum,
                        'email' => $email,
                        'reason' => 'Format email tidak valid.'
                    ];
                    continue;
                }

                if (in_array($email, $seenEmails)) {
                    $errors[] = [
                        'row' => $rowNum,
                        'email' => $email,
                        'reason' => 'Email duplikat terdeteksi di dalam file yang sama.'
                    ];
                    continue;
                }

                if (\App\Models\User::where('email', $email)->exists()) {
                    $errors[] = [
                        'row' => $rowNum,
                        'email' => $email,
                        'reason' => 'Email sudah terdaftar di sistem.'
                    ];
                    continue;
                }
            } else {
                $email = null;
            }

            // 6. Normalization: Gender
            $gender = 'male';
            if (in_array($rawGender, ['female', 'f', 'p', 'perempuan', 'wanita', 'akhwat'])) {
                $gender = 'female';
            } elseif (in_array($rawGender, ['male', 'm', 'l', 'laki-laki', 'pria', 'ikhwan', 'laki'])) {
                $gender = 'male';
            }

            // 7. Normalization: Role
            $role = 'student';
            if (in_array($rawRole, ['teacher', 'guru', 'pengajar', 'ustadz', 'ustadzah'])) {
                $role = 'teacher';
            } elseif (in_array($rawRole, ['manager', 'admin', 'administrator', 'pic'])) {
                $role = 'pic';
            }

            // 8. Check student quota
            $institution = app(\App\Services\InstitutionContext::class)->get();
            if ($role === 'student' && $institution && !$institution->canAddStudents(1)) {
                $errors[] = [
                    'row' => $rowNum,
                    'email' => $username ?: ($email ?: '-'),
                    'reason' => "Batas kuota siswa untuk lembaga ini telah tercapai ({$institution->max_students} siswa)."
                ];
                continue;
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

                $seenUsernames[] = $username;
                if ($email) {
                    $seenEmails[] = $email;
                }
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
                    'email' => $username ?: ($email ?: '-'),
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
            'B1' => 'Username / NIS / NIP*',
            'C1' => 'Email (Opsional)',
            'D1' => 'Password Awal (Opsional)',
            'E1' => 'Jenis Kelamin* (L/P)',
            'F1' => 'Role (student/teacher)',
            'G1' => 'Nama/ID Kelas (Opsional)',
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

        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Sample Data Rows
        $sampleData = [
            ['Ahmad Fauzi', 'ahmadfauzi', 'ahmad.fauzi@sekolah.sch.id', 'password123', 'L', 'student', 'Kelas 10-A'],
            ['Siti Aisyah', '20261002', '', 'password123', 'P', 'student', 'Kelas 10-A'],
            ['Bambang Pamungkas', '198501012010011001', 'bambang.guru@sekolah.sch.id', 'password123', 'L', 'teacher', ''],
        ];

        $rowIdx = 2;
        foreach ($sampleData as $row) {
            $sheet->setCellValue('A' . $rowIdx, $row[0]);
            $sheet->setCellValue('B' . $rowIdx, $row[1]);
            $sheet->setCellValue('C' . $rowIdx, $row[2]);
            $sheet->setCellValue('D' . $rowIdx, $row[3]);
            $sheet->setCellValue('E' . $rowIdx, $row[4]);
            $sheet->setCellValue('F' . $rowIdx, $row[5]);
            $sheet->setCellValue('G' . $rowIdx, $row[6]);
            $rowIdx++;
        }

        // Auto width
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}