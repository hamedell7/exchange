<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Identity\ResetPasswordRequest;
use App\Services\Identity\PasswordResetService;
use Illuminate\Http\JsonResponse;

final class PasswordResetController extends Controller
{
    public function request(
        ForgotPasswordRequest $request,
        PasswordResetService $passwordReset,
    ): JsonResponse {
        $passwordReset->request($request->validated('email'));

        return response()->json([
            'message' => 'If the account exists, password reset instructions will be sent to its email address.',
        ], 202);
    }

    public function reset(
        ResetPasswordRequest $request,
        PasswordResetService $passwordReset,
    ): JsonResponse {
        $passwordReset->reset(
            $request->validated('token'),
            $request->validated('password'),
        );

        return response()->json([
            'message' => 'The password has been reset. You can now sign in with your new password.',
        ]);
    }
}
