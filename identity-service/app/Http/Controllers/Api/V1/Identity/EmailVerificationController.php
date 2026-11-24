<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\ResendEmailVerificationRequest;
use App\Http\Requests\Api\V1\Identity\VerifyEmailRequest;
use App\Http\Resources\Api\V1\Identity\UserResource;
use App\Services\Identity\EmailVerificationService;
use Illuminate\Http\JsonResponse;

final class EmailVerificationController extends Controller
{
    public function verify(
        VerifyEmailRequest $request,
        EmailVerificationService $verification,
    ): JsonResponse {
        $user = $verification->verify($request->validated('token'));

        return (new UserResource($user))->response();
    }

    public function resend(
        ResendEmailVerificationRequest $request,
        EmailVerificationService $verification,
    ): JsonResponse {
        $verification->resend($request->validated('email'));

        return response()->json([
            'message' => 'If the account requires verification, instructions will be sent to its email address.',
        ], 202);
    }
}
