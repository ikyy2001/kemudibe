<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveInstitution;
use App\Models\ClassRoom;
use App\Models\ClassStudent;
use App\Models\ClassSubject;
use App\Models\ClassroomActivity;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\ExamViolation;
use App\Models\Institution;
use App\Models\Lesson;
use App\Models\Project;
use App\Models\QuestionAnswer;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\SubjectExam;
use App\Models\Topic;
use App\Models\Traits\BelongsToInstitution;
use App\Models\User;
use App\Rules\TenantRule;
use App\Services\InstitutionContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TenancyFoundationTest extends TestCase
{
    protected InstitutionContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = app(InstitutionContext::class);
        $this->context->reset();
    }

    protected function tearDown(): void
    {
        $this->context->reset();
        parent::tearDown();
    }

    /**
     * Test 1: Verify all 15 tenant models use the BelongsToInstitution trait
     */
    public function test_all_tenant_models_use_belongs_to_institution_trait(): void
    {
        $models = [
            User::class,
            ClassRoom::class,
            ClassStudent::class,
            ClassSubject::class,
            ClassroomActivity::class,
            ExamAttempt::class,
            ExamQuestion::class,
            ExamViolation::class,
            Lesson::class,
            Project::class,
            QuestionAnswer::class,
            QuestionOption::class,
            Subject::class,
            SubjectExam::class,
            Topic::class,
        ];

        foreach ($models as $modelClass) {
            $traits = class_uses_recursive($modelClass);
            $this->assertArrayHasKey(
                BelongsToInstitution::class,
                $traits,
                "Model [{$modelClass}] must use the BelongsToInstitution trait."
            );
        }
    }

    /**
     * Test 2: Fail-Closed Security - Queries return 0 rows when no tenant context is set
     */
    public function test_fail_closed_returns_zero_rows_without_context(): void
    {
        $this->context->reset();
        $this->assertNull($this->context->getId());

        // Even though database has seeded subjects, topics, and classrooms,
        // without an institution context, queries must fail-closed (return 0).
        $this->assertEquals(0, Subject::count(), 'Fail-closed: Subject count without context must be 0');
        $this->assertEquals(0, Topic::count(), 'Fail-closed: Topic count without context must be 0');
        $this->assertEquals(0, ClassRoom::count(), 'Fail-closed: ClassRoom count without context must be 0');
        $this->assertEquals(0, SubjectExam::count(), 'Fail-closed: SubjectExam count without context must be 0');
        $this->assertEquals(0, Lesson::count(), 'Fail-closed: Lesson count without context must be 0');
    }

    /**
     * Test 3: Tenant Data Isolation between Bimbel A and Bimbel B
     */
    public function test_tenant_data_isolation(): void
    {
        /** @var Institution $bimbelA */
        $bimbelA = Institution::where('slug', 'bimbel-a')->firstOrFail();
        /** @var Institution $bimbelB */
        $bimbelB = Institution::where('slug', 'bimbel-b')->firstOrFail();

        // 1. Set context to Bimbel A
        $this->context->set($bimbelA);

        $subjectsA = Subject::all();
        $this->assertGreaterThan(0, $subjectsA->count());
        foreach ($subjectsA as $subject) {
            $this->assertEquals($bimbelA->id, $subject->institution_id);
            $this->assertNotEquals('Fisika Dasar', $subject->name);
        }
        $this->assertTrue($subjectsA->contains('name', 'Matematika Dasar'));

        $classroomsA = ClassRoom::all();
        $this->assertGreaterThan(0, $classroomsA->count());
        foreach ($classroomsA as $cr) {
            $this->assertEquals($bimbelA->id, $cr->institution_id);
        }

        // 2. Switch context to Bimbel B
        $this->context->set($bimbelB);

        $subjectsB = Subject::all();
        $this->assertGreaterThan(0, $subjectsB->count());
        foreach ($subjectsB as $subject) {
            $this->assertEquals($bimbelB->id, $subject->institution_id);
            $this->assertNotEquals('Matematika Dasar', $subject->name);
        }
        $this->assertTrue($subjectsB->contains('name', 'Fisika Dasar'));

        $classroomsB = ClassRoom::all();
        $this->assertGreaterThan(0, $classroomsB->count());
        foreach ($classroomsB as $cr) {
            $this->assertEquals($bimbelB->id, $cr->institution_id);
        }
    }

    /**
     * Test 4: Auto-filling institution_id on model creation
     */
    public function test_auto_filling_institution_id_on_create(): void
    {
        /** @var Institution $bimbelA */
        $bimbelA = Institution::where('slug', 'bimbel-a')->firstOrFail();
        $this->context->set($bimbelA);

        $classroom = ClassRoom::create([
            'name' => 'Kelas Otomatis Tenant A',
            'grade' => 12,
        ]);

        $this->assertEquals($bimbelA->id, $classroom->institution_id);

        // Clean up
        $classroom->delete();
    }

    /**
     * Test 5: ResolveInstitution middleware blocks cross-tenant access and missing institutions
     */
    public function test_resolve_institution_middleware_logic(): void
    {
        $middleware = new ResolveInstitution($this->context);

        /** @var Institution $bimbelA */
        $bimbelA = Institution::where('slug', 'bimbel-a')->firstOrFail();
        /** @var User $picA */
        $picA = User::where('username', 'pic.bimbela')->firstOrFail();
        /** @var User $picB */
        $picB = User::where('username', 'pic.bimbelb')->firstOrFail();
        /** @var User $superAdmin */
        $superAdmin = User::where('username', 'superadmin')->firstOrFail();

        // 1. Missing institution slug
        $request1 = Request::create('/api//dashboard', 'GET');
        $response1 = $middleware->handle($request1, fn() => new Response('OK'));
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response1->getStatusCode());

        // 2. Non-existent institution slug
        $request2 = Request::create('/api/non-existent-institution/dashboard', 'GET');
        $request2->setRouteResolver(fn() => new class {
            public function parameter($name) { return 'non-existent-institution'; }
        });
        $response2 = $middleware->handle($request2, fn() => new Response('OK'));
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response2->getStatusCode());

        // 3. Authenticated user matches institution (picA -> bimbelA) -> OK
        $request3 = Request::create('/api/bimbel-a/dashboard', 'GET');
        $request3->setUserResolver(fn() => $picA);
        $request3->setRouteResolver(fn() => new class {
            public function parameter($name) { return 'bimbel-a'; }
        });
        $response3 = $middleware->handle($request3, fn() => new Response('OK'));
        $this->assertEquals(Response::HTTP_OK, $response3->getStatusCode());
        $this->assertEquals($bimbelA->id, $this->context->getId());

        // 4. Cross-tenant intrusion (picB trying to access bimbelA) -> 403 FORBIDDEN
        $request4 = Request::create('/api/bimbel-a/dashboard', 'GET');
        $request4->setUserResolver(fn() => $picB);
        $request4->setRouteResolver(fn() => new class {
            public function parameter($name) { return 'bimbel-a'; }
        });
        $response4 = $middleware->handle($request4, fn() => new Response('OK'));
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response4->getStatusCode());

        // 5. Super Admin accessing bimbelA -> OK (Global platform access)
        $request5 = Request::create('/api/bimbel-a/dashboard', 'GET');
        $request5->setUserResolver(fn() => $superAdmin);
        $request5->setRouteResolver(fn() => new class {
            public function parameter($name) { return 'bimbel-a'; }
        });
        $response5 = $middleware->handle($request5, fn() => new Response('OK'));
        $this->assertEquals(Response::HTTP_OK, $response5->getStatusCode());
    }

    /**
     * Test 6: Read-only mode enforcement when institution is suspended or expired
     */
    public function test_read_only_mode_blocks_writes_except_exam_submission(): void
    {
        $middleware = new ResolveInstitution($this->context);

        /** @var Institution $bimbelA */
        $bimbelA = Institution::where('slug', 'bimbel-a')->firstOrFail();
        $bimbelA->update(['status' => 'suspended']); // Suspend institution

        /** @var User $picA */
        $picA = User::where('username', 'pic.bimbela')->firstOrFail();

        // 1. GET requests are allowed (read-only viewing)
        $getRequest = Request::create('/api/bimbel-a/dashboard', 'GET');
        $getRequest->setUserResolver(fn() => $picA);
        $getRequest->setRouteResolver(fn() => new class {
            public function parameter($name) { return 'bimbel-a'; }
        });
        $getResponse = $middleware->handle($getRequest, fn() => new Response('OK'));
        $this->assertEquals(Response::HTTP_OK, $getResponse->getStatusCode());

        // 2. Normal POST (e.g. creating classroom) is BLOCKED with 403
        $postRequest = Request::create('/api/bimbel-a/class-rooms', 'POST');
        $postRequest->setUserResolver(fn() => $picA);
        $postRequest->setRouteResolver(fn() => new class {
            public function parameter($name) { return 'bimbel-a'; }
        });
        $postResponse = $middleware->handle($postRequest, fn() => new Response('OK'));
        $this->assertEquals(Response::HTTP_FORBIDDEN, $postResponse->getStatusCode());
        $this->assertStringContainsString('INSTITUTION_READ_ONLY', (string) $postResponse->getContent());

        // 3. Exam submission POST is EXEMPT and allowed to complete
        $examSubmitRequest = Request::create('/api/bimbel-a/student/exams/1/complete', 'POST');
        $examSubmitRequest->setUserResolver(fn() => $picA);
        $examSubmitRequest->setRouteResolver(fn() => new class {
            public function parameter($name) { return 'bimbel-a'; }
        });
        $examSubmitResponse = $middleware->handle($examSubmitRequest, fn() => new Response('OK'));
        $this->assertEquals(Response::HTTP_OK, $examSubmitResponse->getStatusCode());

        // Revert status to active
        $bimbelA->update(['status' => 'active']);
    }

    /**
     * Test 7: TenantRule helper behaves accurately
     */
    public function test_tenant_rule_reserved_slugs(): void
    {
        $this->assertTrue(TenantRule::isReservedSlug('superadmin'));
        $this->assertTrue(TenantRule::isReservedSlug('admin'));
        $this->assertTrue(TenantRule::isReservedSlug('api'));
        $this->assertTrue(TenantRule::isReservedSlug('login'));
        $this->assertTrue(TenantRule::isReservedSlug('dashboard'));
        $this->assertTrue(TenantRule::isReservedSlug('exams'));

        $this->assertFalse(TenantRule::isReservedSlug('bimbel-prestasi'));
        $this->assertFalse(TenantRule::isReservedSlug('sekolah-teladan'));
    }
}
