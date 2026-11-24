<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Exceptions\Identity\EmailAlreadyRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\RegisterUserRequest;
use App\Http\Resources\Api\V1\Identity\UserResource;
use App\Services\Identity\UserRegistrationService;
use Illuminate\Http\JsonResponse;

final class RegistrationController extends Controller
{
    public function __invoke(
        RegisterUserRequest $request,
        UserRegistrationService $registration,
    ): JsonResponse {
        try {
            $user = $registration->register(
                $request->validated('name'),
                $request->validated('email'),
                $request->validated('password'),
            );
        } catch (EmailAlreadyRegistered $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['email' => [$exception->getMessage()]],
            ], 409);
        }

        return (new UserResource($user))->response()->setStatusCode(201);
    }
}
