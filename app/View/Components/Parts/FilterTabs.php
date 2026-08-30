<?php

namespace App\View\Components\Parts;

use App\Http\Requests\Parts\PartIndexRequest;
use App\Models\Part;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;

class FilterTabs extends Component
{
    /**
     * @var array<int, array{label: string, count: int, url: string, active: bool}>
     */
    public array $tabs;

    /**
     * @var array{label: string, url: string, active: bool}|null
     */
    public ?array $trashedToggle;

    /**
     * @param  array{search: string, filter: string, trashed: string}  $filters
     * @param  array{all: int, low_stock: int}  $counts
     */
    public function __construct(
        public array $filters,
        public array $counts,
    ) {
        $this->tabs = $this->buildTabs();
        $this->trashedToggle = $this->buildTrashedToggle();
    }

    /**
     * @return array<int, array{label: string, count: int, url: string, active: bool}>
     */
    private function buildTabs(): array
    {
        return [
            [
                'label' => __('parts.tabs.all'),
                'count' => $this->counts['all'],
                'url' => $this->url(''),
                'active' => $this->filters['filter'] === '',
            ],
            [
                'label' => __('parts.tabs.low_stock'),
                'count' => $this->counts['low_stock'],
                'url' => $this->url(PartIndexRequest::LOW_STOCK),
                'active' => $this->filters['filter'] === PartIndexRequest::LOW_STOCK,
            ],
        ];
    }

    /**
     * @return array{label: string, url: string, active: bool}|null
     */
    private function buildTrashedToggle(): ?array
    {
        if (Gate::denies('viewDeleted', Part::class)) {
            return null;
        }

        $active = $this->filters['trashed'] === PartIndexRequest::TRASHED_WITH;

        return [
            'label' => $active ? __('parts.trashed.hide') : __('parts.trashed.show'),
            'url' => $this->url($this->filters['filter'], $active ? '' : PartIndexRequest::TRASHED_WITH),
            'active' => $active,
        ];
    }

    private function url(string $filter, ?string $trashed = null): string
    {
        return route('parts.index', array_filter([
            'search' => $this->filters['search'],
            'filter' => $filter,
            'trashed' => $trashed ?? $this->filters['trashed'],
        ]));
    }

    public function render(): View
    {
        return view('components.parts.filter-tabs');
    }
}
