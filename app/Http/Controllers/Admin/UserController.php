<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AdminLockoutPrevented;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private StaffService $staff) {}

    public function index(UserIndexRequest $request): View
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->filters();

        return view('pages.admin.users.index', [
            'members' => $this->staff->paginateFiltered($filters),
            'counts' => $this->staff->countByRole($filters),
            'filters' => $filters,
            'currentUserId' => $request->user()->getKey(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('pages.admin.users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $member = $this->staff->create($request->validated(), $request->role());

        return redirect()->route('admin.users.index')
            ->with('success', __('staff.flash.created', ['name' => $member->name]));
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('pages.admin.users.edit', [
            'member' => $user,
            'lockedRole' => $user->is($request->user()),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        try {
            $this->staff->update($user, $request->validated(), $request->role());
        } catch (AdminLockoutPrevented) {
            return redirect()->route('admin.users.edit', $user)
                ->with('error', __('staff.flash.last_admin'));
        }

        return redirect()->route('admin.users.index')
            ->with('success', __('staff.flash.updated', ['name' => $user->name]));
    }

    public function restore(User $user): RedirectResponse
    {
        Gate::authorize('restore', $user);

        $this->staff->restore($user);

        return redirect()->route('admin.users.index')
            ->with('success', __('staff.flash.restored', ['name' => $user->name]));
    }
}
