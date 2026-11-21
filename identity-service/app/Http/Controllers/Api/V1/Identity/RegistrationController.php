<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\RegisterUserRequest;
use App\Modules\Identity\Application\Exceptions\EmailAlreadyRegistered;
use App\Modules\Identity\Application\UseCases\RegisterUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class RegistrationController extends Controller
{
    public function __invoke(
        RegisterUserRequest $request,
        RegisterUser $registerUser,
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $user = $registerUser->execute(
                $validated['name'],
                $validated['email'],
                $validated['password'],
            );
        } catch (EmailAlreadyRegistered) {
            throw ValidationException::withMessages([
                'email' => ['This email address is already registered.'],
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $user->id()->value(),
                'name' => $user->name(),
                'email' => $user->email()->value(),
                'registered_at' => $user->registeredAt()->format(DATE_ATOM),
            ],
        ], 201);
    }
}
