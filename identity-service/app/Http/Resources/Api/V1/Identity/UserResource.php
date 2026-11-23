<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use App\Modules\Identity\Domain\Entities\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Explicit public representation of an Identity user.
 *
 * Never serialize the Domain aggregate or Eloquent model directly.
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id()->value(),
            'name' => $user->name(),
            'email' => $user->email()->value(),
            'status' => $user->status()->value,
            'email_verified_at' => $user->emailVerifiedAt()?->format(DATE_ATOM),
            'registered_at' => $user->registeredAt()->format(DATE_ATOM),
        ];
    }
}
