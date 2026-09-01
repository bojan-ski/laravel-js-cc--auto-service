<?php

namespace App\View\Components\Invoices;

use App\Enums\InvoiceStatus;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class StatusTabs extends Component
{
    /**
     * @var array<int, array{label: string, count: int, url: string, active: bool}>
     */
    public array $tabs;

    /**
     * @param  array{status: string, search: string}  $filters
     * @param  array<string, int>  $counts
     */
    public function __construct(
        public array $filters,
        public array $counts,
    ) {
        $this->tabs = $this->buildTabs();
    }

    /**
     * @return array<int, array{label: string, count: int, url: string, active: bool}>
     */
    private function buildTabs(): array
    {
        $tabs = [[
            'label' => __('invoices.tabs.all'),
            'count' => array_sum($this->counts),
            'url' => $this->url(''),
            'active' => $this->filters['status'] === '',
        ]];

        foreach (InvoiceStatus::cases() as $status) {
            $tabs[] = [
                'label' => $status->label(),
                'count' => $this->counts[$status->value] ?? 0,
                'url' => $this->url($status->value),
                'active' => $this->filters['status'] === $status->value,
            ];
        }

        return $tabs;
    }

    private function url(string $status): string
    {
        return route('invoices.index', array_filter([
            'status' => $status,
            'search' => $this->filters['search'],
        ]));
    }

    public function render(): View
    {
        return view('components.invoices.status-tabs');
    }
}
