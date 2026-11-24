<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class LogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $currentToken = $request->user()?->currentAccessToken();

        if ($currentToken instanceof PersonalAccessToken) {
            $currentToken->delete();
        }

        return response()->json([
            'message' => 'You have been signed out.',
        ]);
    }
}
