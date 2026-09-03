<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AdminLockoutPrevented;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserOffboardController extends Controller
{
    public function __construct(private StaffService $staff) {}

    public function create(User $user): View
    {
        Gate::authorize('delete', $user);

        return view('pages.admin.users.offboard', ['member' => $user]);
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        try {
            $this->staff->offboard($user);
        } catch (AdminLockoutPrevented) {
            return redirect()->route('admin.users.index')
                ->with('error', __('staff.flash.last_admin'));
        }

        return redirect()->route('admin.users.index')
            ->with('success', __('staff.flash.offboarded', ['name' => $user->name]));
    }
}
