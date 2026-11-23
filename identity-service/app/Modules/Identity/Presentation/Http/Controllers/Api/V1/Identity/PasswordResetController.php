<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Presentation\Http\Requests\Api\V1\Identity\ForgotPasswordRequest;
use App\Modules\Identity\Presentation\Http\Requests\Api\V1\Identity\ResetPasswordRequest;
use App\Modules\Identity\Application\Commands\RequestPasswordResetCommand;
use App\Modules\Identity\Application\Commands\ResetPasswordCommand;
use App\Modules\Identity\Application\Exceptions\InvalidAccountActionToken;
use App\Modules\Identity\Application\Exceptions\InvalidRegistrationInput;
use App\Modules\Identity\Application\Actions\RequestPasswordResetAction;
use App\Modules\Identity\Application\Actions\ResetPasswordAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class PasswordResetController extends Controller
{
    public function request(
        ForgotPasswordRequest $request,
        RequestPasswordResetAction $requestPasswordReset,
    ): JsonResponse {
        $requestPasswordReset->execute(
            new RequestPasswordResetCommand($request->validated('email')),
        );

        return response()->json([
            'message' => 'If the account exists, password reset instructions will be sent to its email address.',
        ], 202);
    }

    public function reset(
        ResetPasswordRequest $request,
        ResetPasswordAction $resetPassword,
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $resetPassword->execute(
                new ResetPasswordCommand(
                    token: $validated['token'],
                    plainPassword: $validated['password'],
                ),
            );
        } catch (InvalidRegistrationInput $exception) {
            throw ValidationException::withMessages([
                $exception->field => [$exception->getMessage()],
            ]);
        } catch (InvalidAccountActionToken $exception) {
            throw ValidationException::withMessages([
                'token' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'message' => 'The password has been reset. You can now sign in with your new password.',
        ]);
    }
}
