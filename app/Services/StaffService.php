<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\AdminLockoutPrevented;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StaffService
{
    public const int PER_PAGE = 25;

    /**
     * @param  array{search: string, role: string}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateFiltered(array $filters): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @param  array{search: string, role: string}  $filters
     * @return array{all: int, roles: array<string, int>}
     */
    public function countByRole(array $filters): array
    {
        $counts = $this->baseQuery(['search' => $filters['search'], 'role' => ''])
            ->select('role', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('role')
            ->pluck('aggregate', 'role');

        $roles = [];

        foreach (UserRole::cases() as $role) {
            $roles[$role->value] = (int) $counts->get($role->value, 0);
        }

        return ['all' => array_sum($roles), 'roles' => $roles];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, UserRole $role): User
    {
        $user = new User;
        $user->fill($attributes);
        $user->role = $role;
        $user->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes, ?UserRole $role = null): User
    {
        if (! $this->isDemotion($user, $role)) {
            $this->write($user, $attributes, $role);

            return $user;
        }

        DB::transaction(function () use ($user, $attributes, $role): void {
            $this->guardAnotherAdminRemains($user, 'demoting them would leave no active administrator');
            $this->write($user, $attributes, $role);
        });

        return $user;
    }

    public function offboard(User $user): void
    {
        DB::transaction(function () use ($user): void {
            if ($user->isAdmin()) {
                $this->guardAnotherAdminRemains($user, 'offboarding them would leave no active administrator');
            }

            $user->delete();
        });
    }

    public function restore(User $user): User
    {
        $user->restore();

        return $user;
    }

    /**
     * @param  array{search: string, role: string}  $filters
     * @return Builder<User>
     */
    private function baseQuery(array $filters): Builder
    {
        return User::withTrashed()
            ->when($filters['search'] !== '', fn (Builder $query) => $query->matching($filters['search']))
            ->when($filters['role'] !== '', fn (Builder $query) => $query->where('role', $filters['role']));
    }

    private function isDemotion(User $user, ?UserRole $role): bool
    {
        return $role !== null && $role !== UserRole::Admin && $user->isAdmin();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(User $user, array $attributes, ?UserRole $role): void
    {
        $user->fill(Arr::except($attributes, ['password']));

        if (filled($attributes['password'] ?? null)) {
            $user->password = $attributes['password'];
        }

        if ($role !== null) {
            $user->role = $role;
        }

        $user->save();
    }

    private function guardAnotherAdminRemains(User $user, string $because): void
    {
        $others = User::query()
            ->activeAdmins()
            ->lockForUpdate()
            ->pluck('id')
            ->reject(fn ($id): bool => (int) $id === $user->getKey());

        if ($others->isEmpty()) {
            throw new AdminLockoutPrevented($user, $because);
        }
    }
}
