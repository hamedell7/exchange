<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Resources\Api\V1\Identity;

use App\Modules\Identity\Application\ReadModels\UserListItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Allow-listed representation for user-list queries.
 *
 * @mixin UserListItem
 */
final class UserListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UserListItem $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'email_verified_at' => $user->emailVerifiedAt?->format(DATE_ATOM),
            'registered_at' => $user->registeredAt->format(DATE_ATOM),
        ];
    }
}
