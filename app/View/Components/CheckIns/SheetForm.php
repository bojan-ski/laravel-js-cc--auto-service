<?php

namespace App\View\Components\CheckIns;

use App\Enums\FuelLevel;
use App\Models\Job;
use App\Models\JobCheckIn;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class SheetForm extends Component
{
    /**
     * @var array<string, array{label: string}>
     */
    public array $fuelLevels;

    public ?JobCheckIn $checkIn;

    public function __construct(
        public Job $job,
    ) {
        $this->checkIn = $job->checkIn;
        $this->fuelLevels = $this->buildFuelLevels();
    }

    /**
     * @return array<string, array{label: string}>
     */
    private function buildFuelLevels(): array
    {
        $levels = [];

        foreach (FuelLevel::cases() as $level) {
            $levels[$level->value] = ['label' => $level->label()];
        }

        return $levels;
    }

    public function render(): View
    {
        return view('components.check-ins.sheet-form');
    }
}
