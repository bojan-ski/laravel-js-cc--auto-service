<?php

namespace App\View\Components\Staff;

use App\Enums\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class FilterTabs extends Component
{
    /**
     * @var array<int, array{label: string, count: int, url: string, active: bool}>
     */
    public array $tabs;

    /**
     * @param  array{search: string, role: string}  $filters
     * @param  array{all: int, roles: array<string, int>}  $counts
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
            'label' => __('staff.tabs.all'),
            'count' => $this->counts['all'],
            'url' => $this->url(''),
            'active' => $this->filters['role'] === '',
        ]];

        foreach (UserRole::cases() as $role) {
            $tabs[] = [
                'label' => $role->label(),
                'count' => $this->counts['roles'][$role->value] ?? 0,
                'url' => $this->url($role->value),
                'active' => $this->filters['role'] === $role->value,
            ];
        }

        return $tabs;
    }

    private function url(string $role): string
    {
        return route('admin.users.index', array_filter([
            'search' => $this->filters['search'],
            'role' => $role,
        ]));
    }

    public function render(): View
    {
        return view('components.staff.filter-tabs');
    }
}
