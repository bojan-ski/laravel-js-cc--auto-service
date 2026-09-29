<?php

namespace App\View\Components\Jobs;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class SortHeader extends Component
{
    public bool $active;

    public string $url;

    public ?string $ariaSort;

    public string $targetLabel;

    /**
     * @param  array{status: string, search: string, sort: string, direction: string, mine: bool}  $filters
     */
    public function __construct(
        public string $column,
        public string $label,
        public array $filters,
    ) {
        $this->active = $filters['sort'] === $column;

        $target = $this->active && $filters['direction'] === 'asc' ? 'desc' : 'asc';

        $this->url = route('jobs.index', array_filter([
            'status' => $filters['status'],
            'search' => $filters['search'],
            'sort' => $column,
            'direction' => $target,
            'mine' => $filters['mine'],
        ]));

        $this->ariaSort = $this->active
            ? ($filters['direction'] === 'asc' ? 'ascending' : 'descending')
            : null;

        $this->targetLabel = __('jobs.board.sort_by', [
            'column' => $label,
            'direction' => __("jobs.board.{$target}ending"),
        ]);
    }

    public function render(): View
    {
        return view('components.jobs.sort-header');
    }
}
