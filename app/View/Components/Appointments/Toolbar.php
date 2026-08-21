<?php

namespace App\View\Components\Appointments;

use App\Enums\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Toolbar extends Component
{
    public string $periodLabel;

    public string $previousUrl;

    public string $nextUrl;

    public string $todayUrl;

    /**
     * @var array<int, array{label: string, url: string, active: bool}>
     */
    public array $modes;

    /**
     * @var array<int, array{label: string, url: string, active: bool}>
     */
    public array $statuses;

    /**
     * @param  array{view: string, date: CarbonImmutable, status: string}  $filters
     */
    public function __construct(
        public array $filters,
    ) {
        $isWeek = $filters['view'] === 'week';
        $date = $filters['date'];

        $this->periodLabel = $isWeek
            ? __('appointments.index.week_range', [
                'start' => $date->startOfWeek(CarbonImmutable::MONDAY)->isoFormat('D MMM'),
                'end' => $date->startOfWeek(CarbonImmutable::MONDAY)->addDays(6)->isoFormat('D MMM YYYY'),
            ])
            : $date->isoFormat('ddd D MMM YYYY');

        $this->previousUrl = $this->url($isWeek ? $date->subWeek() : $date->subDay());
        $this->nextUrl = $this->url($isWeek ? $date->addWeek() : $date->addDay());
        $this->todayUrl = $this->url(CarbonImmutable::today());

        $this->modes = $this->buildModes();
        $this->statuses = $this->buildStatuses();
    }

    /**
     * @return array<int, array{label: string, url: string, active: bool}>
     */
    private function buildModes(): array
    {
        return array_map(fn (string $mode): array => [
            'label' => __("appointments.index.{$mode}"),
            'url' => $this->url($this->filters['date'], view: $mode),
            'active' => $this->filters['view'] === $mode,
        ], ['day', 'week']);
    }

    /**
     * @return array<int, array{label: string, url: string, active: bool}>
     */
    private function buildStatuses(): array
    {
        $all = [[
            'label' => __('appointments.index.status_all'),
            'url' => $this->url($this->filters['date'], status: ''),
            'active' => $this->filters['status'] === '',
        ]];

        foreach (AppointmentStatus::cases() as $status) {
            $all[] = [
                'label' => $status->label(),
                'url' => $this->url($this->filters['date'], status: $status->value),
                'active' => $this->filters['status'] === $status->value,
            ];
        }

        return $all;
    }

    private function url(CarbonImmutable $date, ?string $view = null, ?string $status = null): string
    {
        return route('appointments.index', array_filter([
            'view' => $view ?? $this->filters['view'],
            'date' => $date->format('Y-m-d'),
            'status' => $status ?? $this->filters['status'],
        ]));
    }

    public function render(): View
    {
        return view('components.appointments.toolbar');
    }
}
