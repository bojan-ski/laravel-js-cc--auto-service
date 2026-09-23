<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AppointmentHoursRequest;
use App\Models\ShopSetting;
use App\Services\ShopSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AppointmentHoursController extends Controller
{
    public function __construct(private ShopSettingService $settings) {}

    public function edit(): View
    {
        Gate::authorize('update', ShopSetting::class);

        return view('pages.admin.appointment-hours.edit', [
            'hours' => $this->settings->current(),
        ]);
    }

    public function update(AppointmentHoursRequest $request): RedirectResponse
    {
        Gate::authorize('update', ShopSetting::class);

        $hours = $this->settings->updateAppointmentHours(
            $request->string('open_at')->value(),
            $request->string('close_at')->value(),
        );

        return redirect()->route('appointments.index')
            ->with('success', __('appointments.flash.hours_updated', [
                'open' => $hours->opensAt(),
                'close' => $hours->closesAt(),
            ]));
    }
}
