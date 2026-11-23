<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\ResendEmailVerificationRequest;
use App\Http\Requests\Api\V1\Identity\VerifyEmailRequest;
use App\Http\Resources\Api\V1\Identity\UserResource;
use App\Modules\Identity\Application\Commands\RequestEmailVerificationCommand;
use App\Modules\Identity\Application\Commands\VerifyEmailCommand;
use App\Modules\Identity\Application\Exceptions\InvalidAccountActionToken;
use App\Modules\Identity\Application\UseCases\RequestEmailVerification;
use App\Modules\Identity\Application\UseCases\VerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class EmailVerificationController extends Controller
{
    public function verify(VerifyEmailRequest $request, VerifyEmail $verifyEmail): JsonResponse
    {
        try {
            $user = $verifyEmail->execute(
                new VerifyEmailCommand($request->validated('token')),
            );
        } catch (InvalidAccountActionToken $exception) {
            throw ValidationException::withMessages([
                'token' => [$exception->getMessage()],
            ]);
        }

        return (new UserResource($user))->response();
    }

    public function resend(
        ResendEmailVerificationRequest $request,
        RequestEmailVerification $requestEmailVerification,
    ): JsonResponse {
        $requestEmailVerification->execute(
            new RequestEmailVerificationCommand($request->validated('email')),
        );

        return response()->json([
            'message' => 'If the account requires verification, instructions will be sent to its email address.',
        ], 202);
    }
}
