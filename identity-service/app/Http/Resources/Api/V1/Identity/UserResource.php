<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Explicit public representation. Sensitive model columns are never exposed.
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
            'id' => (string) $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status->value,
            'email_verified_at' => $user->email_verified_at?->toAtomString(),
            'registered_at' => $user->created_at?->toAtomString(),
        ];
    }
}
