<?php

namespace App\View\Components\Jobs;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class MineToggle extends Component
{
    public bool $active;

    /**
     * @var array<string, string>
     */
    public array $hiddenFields;

    /**
     * @param  array{status: string, search: string, sort: string, direction: string, mine: bool}  $filters
     */
    public function __construct(
        public array $filters,
    ) {
        $this->active = $filters['mine'];

        $this->hiddenFields = array_filter([
            'status' => $filters['status'],
            'search' => $filters['search'],
            'sort' => $filters['sort'],
            'direction' => $filters['direction'],
        ]);
    }

    public function render(): View
    {
        return view('components.jobs.mine-toggle');
    }
}
