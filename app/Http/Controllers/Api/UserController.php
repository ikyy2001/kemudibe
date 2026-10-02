<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of users
     */
    public function index(Request $request)
    {
        try {
            $fields = ['id', 'name', 'email', 'photo', 'gender'];
            $perPage = $request->integer('per_page', 6);

            // Handle search
            if ($request->filled('search')) {
                $users = $this->userService->searchUsers(
                    $request->string('search'), 
                    $fields, 
                    $perPage
                );
                return UserResource::collection($users);
            }

            // Filter by gender
            if ($request->filled('gender')) {
                $users = $this->userService->findUsersByGender(
                    $request->string('gender'), 
                    $fields, 
                    $perPage
                );
                return UserResource::collection($users);
            }

            // Filter by role
            if ($request->filled('role')) {
                $users = $this->userService->findUsersByRole(
                    $request->string('role'), 
                    $fields, 
                    $perPage
                );
                return UserResource::collection($users);
            }

            // Handle all parameter
            if ($request->boolean('all')) {
                $users = $this->userService->getAll($fields);
                return UserResource::collection($users);
            }

            // Default paginated response
            $users = $this->userService->getPaginated($fields, $perPage);
            return UserResource::collection($users);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve users',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Download Excel template for bulk account creation
     */
    public function downloadBulkTemplate()
    {
        try {
            $spreadsheet = $this->userService->generateTemplateSpreadsheet();
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            $fileName = 'template_bulk_tambah_akun.xlsx';

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate template',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk import accounts via Excel/CSV file or parsed JSON array
     */
    public function bulkImport(Request $request)
    {
        try {
            $accountRows = [];
            $defaultPassword = $request->string('default_password', 'password123');

            // 1. Cek jika 'users' dikirimkan (bisa berupa JSON array atau JSON string dalam form-data)
            if ($request->has('users')) {
                $rawUsers = $request->input('users');
                if (is_string($rawUsers)) {
                    $decoded = json_decode($rawUsers, true);
                    if (is_array($decoded) && !empty($decoded)) {
                        $accountRows = $decoded;
                    }
                } elseif (is_array($rawUsers) && !empty($rawUsers)) {
                    $accountRows = $rawUsers;
                }
            }

            // 2. Jika 'users' belum ada dan ada upload file, parse file Excel/CSV via PhpSpreadsheet
            if (empty($accountRows) && $request->hasFile('file')) {
                $file = $request->file('file');
                $extension = strtolower($file->getClientOriginalExtension());

                if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File harus berformat Excel (.xlsx, .xls) atau CSV (.csv)',
                    ], 422);
                }

                $accountRows = $this->userService->parseSpreadsheet($file);
            } elseif (empty($accountRows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Silakan upload file Excel (.xlsx) atau kirim daftar akun yang valid.',
                ], 422);
            }

            if (empty($accountRows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data akun yang ditemukan di dalam file/permintaan.',
                ], 422);
            }

            $result = $this->userService->bulkImport($accountRows, $defaultPassword);

            return response()->json([
                'success' => true,
                'message' => "Proses impor selesai. {$result['imported_count']} akun berhasil dibuat, {$result['skipped_count']} dilewati.",
                'data' => $result,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses impor akun',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}