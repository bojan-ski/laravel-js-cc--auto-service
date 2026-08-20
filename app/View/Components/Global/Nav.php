<?php

namespace App\View\Components\Global;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Nav extends Component
{
    /**
     * @var array<int, array{label: string, url: string, active: bool}>
     */
    public array $items;

    public User $user;

    public function __construct()
    {
        $this->user = auth()->user();
        $this->items = $this->buildItems();
    }

    /**
     * @return array<int, array{label: string, url: string, active: bool}>
     */
    private function buildItems(): array
    {
        $destinations = [
            ['label' => __('app.nav.dashboard'), 'route' => 'dashboard', 'pattern' => 'dashboard'],
            ['label' => __('app.nav.customers'), 'route' => 'customers.index', 'pattern' => 'customers.*'],
        ];

        return array_map(fn (array $destination): array => [
            'label' => $destination['label'],
            'url' => route($destination['route']),
            'active' => request()->routeIs($destination['pattern']),
        ], $destinations);
    }

    public function render(): View
    {
        return view('components.global.nav');
    }
}
