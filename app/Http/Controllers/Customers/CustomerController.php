<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\CustomerIndexRequest;
use App\Http\Requests\Customers\CustomerRequest;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\CustomerService;
use App\Services\JobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customers,
        private JobService $jobs,
    ) {}

    public function index(CustomerIndexRequest $request): View
    {
        Gate::authorize('viewAny', Customer::class);

        $filters = $request->filters();

        return view('pages.customers.index', [
            'customers' => $this->customers->paginateFiltered($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Customer::class);

        return view('pages.customers.create');
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        Gate::authorize('create', Customer::class);

        $customer = $this->customers->create($request->validated());

        return redirect()->route('customers.show', $customer)
            ->with('success', __('customers.flash.created', ['name' => $customer->name]));
    }

    public function show(Customer $customer): View
    {
        Gate::authorize('view', $customer);

        $customer->load('vehicles');

        if (Gate::allows('viewDeleted', Vehicle::class)) {
            $customer->load('deletedVehicles');
        }

        return view('pages.customers.show', [
            'customer' => $customer,
            'jobHistory' => $this->jobs->historyForCustomer($customer),
            'jobTotal' => $this->jobs->countForCustomer($customer),
        ]);
    }

    public function edit(Customer $customer): View
    {
        Gate::authorize('update', $customer);

        return view('pages.customers.edit', ['customer' => $customer]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('update', $customer);

        $this->customers->update($customer, $request->validated());

        return redirect()->route('customers.show', $customer)
            ->with('success', __('customers.flash.updated', ['name' => $customer->name]));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $this->customers->delete($customer);

        return redirect()->route('customers.index')
            ->with('success', __('customers.flash.deleted', ['name' => $customer->name]));
    }
}
