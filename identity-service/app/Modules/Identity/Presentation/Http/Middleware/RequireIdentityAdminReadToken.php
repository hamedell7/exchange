<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireIdentityAdminReadToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('identity.users_read_token');

        // Fail closed: this endpoint must never become public because an
        // environment variable was omitted during deployment.
        if (! is_string($expected) || preg_match('/\\A[a-f0-9]{64}\\z/D', $expected) !== 1) {
            return new JsonResponse([
                'message' => 'The user-list endpoint is not configured for access.',
            ], 503);
        }

        $provided = $request->header('X-Identity-Admin-Token');

        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            return new JsonResponse([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return $next($request);
    }
}
