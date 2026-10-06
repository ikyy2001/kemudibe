<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\User;
use App\Rules\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InstitutionController extends Controller
{
    /**
     * List all institutions with quota usage, student counts, and status.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Institution::query();

        // Search filter
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->query('status')) {
            if ($status === 'expired') {
                $query->where('expires_at', '<', now());
            } elseif ($status === 'active') {
                $query->where('status', 'active')
                      ->where(function ($q) {
                          $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                      });
            } elseif ($status === 'suspended') {
                $query->where('status', 'suspended');
            }
        }

        $perPage = (int) $request->query('per_page', 15);
        $institutions = $query->latest()->paginate($perPage);

        // Transform collection to include counts and PIC information
        $transformed = $institutions->getCollection()->map(function (Institution $inst) {
            $activeStudents = $inst->getActiveStudentsCount();
            $activeTeachers = User::withoutTenant()
                ->where('institution_id', $inst->id)
                ->role('teacher')
                ->count();

            // Find primary PIC
            $pic = User::withoutTenant()
                ->where('institution_id', $inst->id)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['pic', 'manager']))
                ->first();

            return [
                'id' => $inst->id,
                'name' => $inst->name,
                'slug' => $inst->slug,
                'logo' => $inst->logo,
                'status' => $inst->status,
                'contact_name' => $inst->contact_name,
                'contact_phone' => $inst->contact_phone,
                'contact_email' => $inst->contact_email,
                'starts_at' => $inst->starts_at?->format('Y-m-d'),
                'expires_at' => $inst->expires_at?->format('Y-m-d'),
                'max_students' => $inst->max_students,
                'active_students' => $activeStudents,
                'active_teachers' => $activeTeachers,
                'max_storage_mb' => $inst->max_storage_mb,
                'storage_used_bytes' => $inst->storage_used_bytes,
                'storage_used_mb' => round($inst->storage_used_bytes / (1024 * 1024), 2),
                'is_read_only' => $inst->isReadOnly(),
                'is_expired' => $inst->isExpired(),
                'pic' => $pic ? [
                    'id' => $pic->id,
                    'name' => $pic->name,
                    'username' => $pic->username,
                    'email' => $pic->email,
                ] : null,
                'created_at' => $inst->created_at?->format('Y-m-d H:i:s'),
            ];
        });

        $institutions->setCollection($transformed);

        return response()->json($institutions);
    }

    /**
     * Create a new institution and its primary PIC user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-z0-9-]+$/',
                'unique:institutions,slug',
                function ($attribute, $value, $fail) {
                    if (TenantRule::isReservedSlug($value)) {
                        $fail("Slug '{$value}' dicadangkan untuk rute sistem dan tidak dapat digunakan.");
                    }
                },
            ],
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'max_students' => 'nullable|integer|min:1',
            'max_storage_mb' => 'nullable|integer|min:50',

            // PIC User fields
            'pic_name' => 'required|string|max:255',
            'pic_username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9._-]+$/',
            ],
            'pic_email' => 'nullable|email|max:255',
            'pic_password' => 'required|string|min:8',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // 1. Create Institution
            $institution = Institution::create([
                'name' => $validated['name'],
                'slug' => strtolower($validated['slug']),
                'contact_name' => $validated['contact_name'] ?? $validated['pic_name'],
                'contact_phone' => $validated['contact_phone'] ?? null,
                'contact_email' => $validated['contact_email'] ?? $validated['pic_email'],
                'status' => $validated['status'] ?? 'active',
                'starts_at' => $validated['starts_at'] ?? now(),
                'expires_at' => $validated['expires_at'] ?? null,
                'max_students' => $validated['max_students'] ?? 100,
                'max_storage_mb' => $validated['max_storage_mb'] ?? 1024,
                'storage_used_bytes' => 0,
            ]);

            // 2. Create Initial PIC User
            $pic = User::create([
                'institution_id' => $institution->id,
                'name' => $validated['pic_name'],
                'username' => strtolower($validated['pic_username']),
                'email' => $validated['pic_email'] ?? null,
                'password' => Hash::make($validated['pic_password']),
                'gender' => 'male',
                'must_change_password' => false,
            ]);

            // Assign both pic and manager roles for backwards compatibility
            $pic->assignRole('pic');
            $pic->assignRole('manager');

            // 3. Log Audit Trail
            AuditLog::log(
                action: 'create_institution',
                entityType: Institution::class,
                entityId: $institution->id,
                newValues: [
                    'institution' => $institution->only(['name', 'slug', 'status', 'max_students', 'max_storage_mb']),
                    'pic_username' => $pic->username,
                ],
                institutionId: $institution->id,
                userId: $request->user()?->id
            );

            return response()->json([
                'message' => "Lembaga '{$institution->name}' dan akun PIC berhasil dibuat.",
                'data' => [
                    'institution' => $institution,
                    'pic' => [
                        'id' => $pic->id,
                        'name' => $pic->name,
                        'username' => $pic->username,
                        'email' => $pic->email,
                    ],
                ],
            ], 201);
        });
    }

    /**
     * Show single institution detail.
     */
    public function show(int $id): JsonResponse
    {
        $institution = Institution::findOrFail($id);

        $activeStudents = $institution->getActiveStudentsCount();
        $activeTeachers = User::withoutTenant()
            ->where('institution_id', $institution->id)
            ->role('teacher')
            ->count();
        $classroomsCount = DB::table('class_rooms')
            ->where('institution_id', $institution->id)
            ->count();
        $subjectsCount = DB::table('subjects')
            ->where('institution_id', $institution->id)
            ->count();
        $examsCount = DB::table('subject_exams')
            ->where('institution_id', $institution->id)
            ->count();

        // PIC users
        $pics = User::withoutTenant()
            ->where('institution_id', $institution->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['pic', 'manager']))
            ->get(['id', 'name', 'username', 'email', 'created_at']);

        // Recent Audit logs
        $recentLogs = AuditLog::with('user:id,name,username')
            ->where('institution_id', $institution->id)
            ->latest('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'id' => $institution->id,
                'name' => $institution->name,
                'slug' => $institution->slug,
                'logo' => $institution->logo,
                'status' => $institution->status,
                'contact_name' => $institution->contact_name,
                'contact_phone' => $institution->contact_phone,
                'contact_email' => $institution->contact_email,
                'starts_at' => $institution->starts_at?->format('Y-m-d'),
                'expires_at' => $institution->expires_at?->format('Y-m-d'),
                'max_students' => $institution->max_students,
                'active_students' => $activeStudents,
                'active_teachers' => $activeTeachers,
                'classrooms_count' => $classroomsCount,
                'subjects_count' => $subjectsCount,
                'exams_count' => $examsCount,
                'max_storage_mb' => $institution->max_storage_mb,
                'storage_used_bytes' => $institution->storage_used_bytes,
                'storage_used_mb' => round($institution->storage_used_bytes / (1024 * 1024), 2),
                'is_read_only' => $institution->isReadOnly(),
                'is_expired' => $institution->isExpired(),
                'pics' => $pics,
                'recent_logs' => $recentLogs,
                'created_at' => $institution->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $institution->updated_at?->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * Update institution configuration & quotas.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $institution = Institution::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'status' => ['sometimes', 'required', Rule::in(['active', 'suspended'])],
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'max_students' => 'sometimes|required|integer|min:1',
            'max_storage_mb' => 'sometimes|required|integer|min:50',
        ]);

        $oldValues = $institution->only([
            'name', 'status', 'starts_at', 'expires_at', 'max_students', 'max_storage_mb'
        ]);

        $institution->update($validated);

        AuditLog::log(
            action: 'update_institution',
            entityType: Institution::class,
            entityId: $institution->id,
            oldValues: $oldValues,
            newValues: $institution->only(array_keys($validated)),
            institutionId: $institution->id,
            userId: $request->user()?->id
        );

        return response()->json([
            'message' => 'Lembaga berhasil diperbarui.',
            'data' => $institution,
        ]);
    }

    /**
     * Soft delete an institution.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $institution = Institution::findOrFail($id);
        $name = $institution->name;

        $institution->delete();

        AuditLog::log(
            action: 'delete_institution',
            entityType: Institution::class,
            entityId: $id,
            oldValues: ['name' => $name, 'slug' => $institution->slug],
            institutionId: $id,
            userId: $request->user()?->id
        );

        return response()->json([
            'message' => "Lembaga '{$name}' berhasil dinonaktifkan/dihapus.",
        ]);
    }

    /**
     * Reset PIC Password for an institution.
     */
    public function resetPicPassword(Request $request, int $id): JsonResponse
    {
        $institution = Institution::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'nullable|integer',
            'password' => 'nullable|string|min:8',
            'must_change_password' => 'nullable|boolean',
        ]);

        $picQuery = User::withoutTenant()
            ->where('institution_id', $institution->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['pic', 'manager']));

        if (!empty($validated['user_id'])) {
            $pic = $picQuery->where('id', $validated['user_id'])->first();
        } else {
            $pic = $picQuery->first();
        }

        if (!$pic) {
            return response()->json([
                'message' => 'Akun PIC untuk lembaga ini tidak ditemukan.',
            ], 404);
        }

        $plainPassword = $validated['password'] ?? Str::random(10);
        $pic->update([
            'password' => Hash::make($plainPassword),
            'must_change_password' => $validated['must_change_password'] ?? true,
        ]);

        AuditLog::log(
            action: 'reset_pic_password',
            entityType: User::class,
            entityId: $pic->id,
            newValues: ['pic_username' => $pic->username],
            institutionId: $institution->id,
            userId: $request->user()?->id
        );

        return response()->json([
            'message' => "Password untuk PIC '{$pic->name}' ({$pic->username}) berhasil direset.",
            'data' => [
                'user_id' => $pic->id,
                'name' => $pic->name,
                'username' => $pic->username,
                'plain_password' => $plainPassword,
                'must_change_password' => $pic->must_change_password,
            ],
        ]);
    }
}
