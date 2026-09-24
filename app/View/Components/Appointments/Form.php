<?php

namespace App\View\Components\Appointments;

use App\Enums\Locale;
use App\Models\Appointment;
use App\Models\ShopSetting;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Form extends Component
{
    /**
     * @var array{one: string, few: string, other: string}
     */
    public array $overlapTemplates;

    /**
     * @param  array<int, string>  $vehicleOptions
     */
    public function __construct(
        public ShopSetting $hours,
        public ?Appointment $appointment = null,
        public array $vehicleOptions = [],
        public ?string $earliest = null,
    ) {
        $this->overlapTemplates = Locale::pluralTemplates('appointments.overlap.warning');
    }

    public function render(): View
    {
        return view('components.appointments.form');
    }
}
