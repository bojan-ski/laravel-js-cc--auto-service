<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'phone', 'email', 'address', 'notes'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $escaped = addcslashes($term, '%_\\');

        $query->where(function (Builder $query) use ($escaped) {
            $query->where('name', 'like', "%{$escaped}%")
                ->orWhere('phone', 'like', "%{$escaped}%");
        });
    }
}
