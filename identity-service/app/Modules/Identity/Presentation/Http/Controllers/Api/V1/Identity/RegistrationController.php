<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Presentation\Http\Requests\Api\V1\Identity\RegisterUserRequest;
use App\Modules\Identity\Presentation\Http\Resources\Api\V1\Identity\UserResource;
use App\Modules\Identity\Application\Commands\RegisterUserCommand;
use App\Modules\Identity\Application\Exceptions\EmailAlreadyRegistered;
use App\Modules\Identity\Application\Exceptions\InvalidRegistrationInput;
use App\Modules\Identity\Application\Actions\RegisterUserAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class RegistrationController extends Controller
{
    public function __invoke(
        RegisterUserRequest $request,
        RegisterUserAction $registerUser,
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $user = $registerUser->execute(
                new RegisterUserCommand(
                    name: $validated['name'],
                    email: $validated['email'],
                    plainPassword: $validated['password'],
                ),
            );
        } catch (InvalidRegistrationInput $exception) {
            throw ValidationException::withMessages([
                $exception->field => [$exception->getMessage()],
            ]);
        } catch (EmailAlreadyRegistered $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['email' => [$exception->getMessage()]],
            ], 409);
        }

        return (new UserResource($user))->response()->setStatusCode(201);
    }
}
