<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Queries\ListUsersQuery;
use App\Modules\Identity\Application\QueryHandlers\ListUsersQueryHandler;
use App\Modules\Identity\Application\ReadModels\UserListItem;
use App\Modules\Identity\Presentation\Http\Requests\Api\V1\Identity\ListUsersRequest;
use App\Modules\Identity\Presentation\Http\Resources\Api\V1\Identity\UserListItemResource;
use Illuminate\Http\JsonResponse;

final class ListUsersController extends Controller
{
    public function __invoke(
        ListUsersRequest $request,
        ListUsersQueryHandler $listUsers,
    ): JsonResponse {
        $validated = $request->validated();

        $result = $listUsers->handle(new ListUsersQuery(
            search: isset($validated['search']) && trim($validated['search']) !== ''
                ? trim($validated['search'])
                : null,
            status: $validated['status'] ?? null,
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? 25),
        ));

        $data = array_map(
            static fn (UserListItem $user): array => (new UserListItemResource($user))->resolve($request),
            $result->items,
        );

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $result->currentPage,
                'per_page' => $result->perPage,
                'total' => $result->total,
                'last_page' => $result->lastPage,
            ],
        ]);
    }
}
