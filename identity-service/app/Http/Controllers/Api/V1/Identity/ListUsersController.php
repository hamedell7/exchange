<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\ListUsersRequest;
use App\Http\Resources\Api\V1\Identity\UserResource;
use App\Services\Identity\UserQueryService;
use Illuminate\Http\JsonResponse;

final class ListUsersController extends Controller
{
    public function __invoke(
        ListUsersRequest $request,
        UserQueryService $users,
    ): JsonResponse {
        $validated = $request->validated();

        $page = $users->paginate(
            isset($validated['search']) && trim($validated['search']) !== ''
                ? trim($validated['search'])
                : null,
            $validated['status'] ?? null,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 25),
        );

        $data = $page->getCollection()
            ->map(static fn ($user): array => (new UserResource($user))->resolve($request))
            ->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
