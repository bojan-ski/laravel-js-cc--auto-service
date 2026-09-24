<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\CustomerLookupRequest;
use App\Http\Resources\CustomerLookupResource;
use App\Models\Customer;
use App\Services\CustomerIntakeService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CustomerLookupController extends Controller
{
    public function __construct(
        private CustomerIntakeService $intake,
    ) {}

    public function __invoke(CustomerLookupRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('create', Customer::class);

        return CustomerLookupResource::collection($this->intake->lookup($request->term()));
    }
}
