<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class UserQueryService
{
    public function paginate(
        ?string $search,
        ?string $status,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        return User::query()
            ->select([
                'id',
                'name',
                'email',
                'status',
                'email_verified_at',
                'created_at',
            ])
            ->when(
                $status !== null,
                static fn ($query) => $query->where('status', $status),
            )
            ->when(
                $search !== null && $search !== '',
                static function ($query) use ($search): void {
                    $term = '%'.mb_strtolower($search, 'UTF-8').'%';

                    $query->where(static function ($nested) use ($term): void {
                        $nested
                            ->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhere('email_normalized', 'like', $term);
                    });
                },
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                $perPage,
                ['id', 'name', 'email', 'status', 'email_verified_at', 'created_at'],
                'page',
                $page,
            );
    }
}
