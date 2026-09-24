<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CustomerService
{
    /**
     * @return Collection<int, Customer>
     */
    public function selectable(?Customer $except = null): Collection
    {
        return Customer::query()
            ->when($except, fn ($query, Customer $customer) => $query->whereKeyNot($customer->getKey()))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{search: string}  $filters
     * @return LengthAwarePaginator<int, Customer>
     */
    public function paginateFiltered(array $filters): LengthAwarePaginator
    {
        return Customer::query()
            ->with(['vehicles' => fn ($query) => $query->select(['id', 'customer_id', 'registration_plate', 'make', 'model'])])
            ->when($filters['search'], fn ($query, string $term) => $query->matching($term))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Customer
    {
        return Customer::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Customer $customer, array $attributes): Customer
    {
        $customer->update($attributes);

        return $customer;
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }
}
