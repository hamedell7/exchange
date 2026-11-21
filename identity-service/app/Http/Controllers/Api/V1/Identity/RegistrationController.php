<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\RegisterUserRequest;
use App\Modules\Identity\Application\Commands\RegisterUserCommand;
use App\Modules\Identity\Application\Exceptions\EmailAlreadyRegistered;
use App\Modules\Identity\Application\UseCases\RegisterUser;
use App\Modules\Identity\Domain\Exceptions\WeakPassword;
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
            $result = $registerUser->execute(
                new RegisterUserCommand(
                    name: $validated['name'],
                    email: $validated['email'],
                    plainPassword: $validated['password'],
                ),
            );
        } catch (EmailAlreadyRegistered) {
            throw ValidationException::withMessages([
                'email' => ['This email address is already registered.'],
            ]);
        } catch (WeakPassword) {
            throw ValidationException::withMessages([
                'password' => ['The password must contain at least 12 characters.'],
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $result->id,
                'name' => $result->name,
                'email' => $result->email,
                'registered_at' => $result->registeredAt->format(DATE_ATOM),
            ],
        ], 201);
    }
}
