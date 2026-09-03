<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'password' => 'hashed',
        ];
    }

    public function isMechanic(): bool
    {
        return $this->role === UserRole::Mechanic;
    }

    public function isReceptionist(): bool
    {
        return $this->role === UserRole::Receptionist;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isFrontOffice(): bool
    {
        return (bool) $this->role?->isFrontOffice();
    }

    public function isOffboarded(): bool
    {
        return $this->deleted_at !== null;
    }

    #[Scope]
    protected function activeAdmins(Builder $query): void
    {
        $query->where('role', UserRole::Admin)->whereNull('deleted_at');
    }

    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $escaped = addcslashes($term, '%_\\');

        $query->where(function (Builder $query) use ($escaped) {
            $query->where('name', 'like', "%{$escaped}%")
                ->orWhere('email', 'like', "%{$escaped}%");
        });
    }

    public function shortName(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) < 2) {
            return $this->name;
        }

        return mb_substr(array_shift($parts), 0, 1).'. '.array_pop($parts);
    }
}
