<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\LoginRequest;
use App\Http\Resources\Api\V1\Identity\UserResource;
use App\Services\Identity\LoginService;
use Illuminate\Http\JsonResponse;

final class LoginController extends Controller
{
    public function __invoke(
        LoginRequest $request,
        LoginService $login,
    ): JsonResponse {
        $result = $login->authenticate(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name'),
        );

        if ($result === null) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $result['access_token'],
                'expires_at' => $result['expires_at']->toIso8601String(),
                'user' => (new UserResource($result['user']))->resolve($request),
            ],
        ]);
    }
}
