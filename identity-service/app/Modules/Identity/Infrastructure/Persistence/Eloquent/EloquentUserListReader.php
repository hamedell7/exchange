<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Application\Contracts\UserListReader;
use App\Modules\Identity\Application\Queries\ListUsersQuery;
use App\Modules\Identity\Application\ReadModels\UserListItem;
use App\Modules\Identity\Application\ReadModels\UserListPage;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentUserListReader implements UserListReader
{
    public function paginate(ListUsersQuery $query): UserListPage
    {
        $users = DB::table('users')
            ->select([
                'id',
                'name',
                'email',
                'status',
                'email_verified_at',
                'created_at',
            ])
            ->when(
                $query->status !== null,
                static fn (Builder $builder): Builder => $builder->where('status', $query->status),
            )
            ->when(
                $query->search !== null && $query->search !== '',
                static function (Builder $builder) use ($query): void {
                    $term = '%'.mb_strtolower($query->search, 'UTF-8').'%';

                    $builder->where(static function (Builder $nested) use ($term): void {
                        $nested
                            ->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhere('email_normalized', 'like', $term);
                    });
                },
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                $query->perPage,
                ['id', 'name', 'email', 'status', 'email_verified_at', 'created_at'],
                'page',
                $query->page,
            );

        $items = array_map(
            static fn (object $record): UserListItem => new UserListItem(
                id: (string) $record->id,
                name: (string) $record->name,
                email: (string) $record->email,
                status: (string) $record->status,
                emailVerifiedAt: $record->email_verified_at === null
                    ? null
                    : new DateTimeImmutable((string) $record->email_verified_at),
                registeredAt: new DateTimeImmutable((string) $record->created_at),
            ),
            $users->items(),
        );

        return new UserListPage(
            items: $items,
            currentPage: $users->currentPage(),
            perPage: $users->perPage(),
            total: $users->total(),
            lastPage: $users->lastPage(),
        );
    }
}
