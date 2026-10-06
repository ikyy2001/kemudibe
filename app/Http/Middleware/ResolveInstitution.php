<?php

namespace App\Http\Middleware;

use App\Models\Institution;
use App\Services\InstitutionContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveInstitution
{
    public function __construct(
        protected InstitutionContext $context
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('institution');

        if (!$slug) {
            return response()->json([
                'message' => 'Institution identifier missing',
                'code' => 'INSTITUTION_MISSING'
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var Institution|null $institution */
        $institution = Institution::where('slug', $slug)->first();

        if (!$institution) {
            return response()->json([
                'message' => "Institution '{$slug}' not found",
                'code' => 'INSTITUTION_NOT_FOUND'
            ], Response::HTTP_NOT_FOUND);
        }

        // Set active context
        $this->context->set($institution);

        // If user is authenticated, ensure their institution matches
        $user = $request->user();
        if ($user) {
            // Superadmin has global access across all institutions
            if (!$user->hasRole('superadmin')) {
                if ($user->institution_id !== $institution->id) {
                    return response()->json([
                        'message' => 'Anda tidak memiliki hak akses ke lembaga ini.',
                        'code' => 'INSTITUTION_MISMATCH'
                    ], Response::HTTP_FORBIDDEN);
                }
            }
        }

        // Check if institution is read-only (suspended or expired)
        if ($institution->isReadOnly()) {
            $method = strtoupper($request->method());
            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                // Allow finishing active exam attempts and heartbeats
                if (!$this->isExemptFromReadOnly($request)) {
                    return response()->json([
                        'message' => 'Lembaga sedang dalam mode baca-saja karena masa aktif telah berakhir atau dinonaktifkan.',
                        'code' => 'INSTITUTION_READ_ONLY'
                    ], Response::HTTP_FORBIDDEN);
                }
            }
        }

        // Remove 'institution' parameter from route so existing controllers keep their expected parameter signatures
        $route = $request->route();
        if (is_object($route) && method_exists($route, 'forgetParameter')) {
            $route->forgetParameter('institution');
        }

        return $next($request);
    }

    /**
     * Check if a request path is exempt from read-only mode
     */
    protected function isExemptFromReadOnly(Request $request): bool
    {
        $path = $request->path();
        return (str_contains($path, '/questions/') && str_contains($path, '/answer'))
            || str_contains($path, '/complete')
            || str_contains($path, '/heartbeat')
            || str_contains($path, '/log-violation');
    }
}
