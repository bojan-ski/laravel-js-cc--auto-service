<?php

namespace App\View\Components\Global;

use App\Enums\Theme;
use App\Models\Brand;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;

class Nav extends Component
{
    /**
     * @var array<int, array{label: string, url: string, active: bool}>
     */
    public array $items;

    public User $user;

    /**
     * @var array<int, array{label: string, url: string, active: bool}>
     */
    public array $adminLinks;

    /**
     * @var array<int, array{value: string, label: string, current: bool}>
     */
    public array $themeOptions;

    public function __construct()
    {
        $this->user = auth()->user();
        $this->items = $this->buildItems();
        $this->adminLinks = $this->buildAdminLinks();
        $this->themeOptions = $this->buildThemeOptions();
    }

    /**
     * @return array<int, array{value: string, label: string, current: bool}>
     */
    private function buildThemeOptions(): array
    {
        return array_map(fn (Theme $theme): array => [
            'value' => $theme->value,
            'label' => $theme->label(),
            'current' => $theme === $this->user->theme,
        ], Theme::cases());
    }

    /**
     * @return array<int, array{label: string, url: string, active: bool}>
     */
    private function buildAdminLinks(): array
    {
        $links = [];

        if (Gate::allows('viewAny', User::class)) {
            $links[] = [
                'label' => __('app.nav.staff'),
                'url' => route('admin.users.index'),
                'active' => request()->routeIs('admin.users.*'),
            ];
        }

        if (Gate::allows('viewAny', Brand::class)) {
            $links[] = [
                'label' => __('app.nav.brands'),
                'url' => route('admin.brands.index'),
                'active' => request()->routeIs('admin.brands.*'),
            ];
        }

        return $links;
    }

    /**
     * @return array<int, array{label: string, url: string, active: bool}>
     */
    private function buildItems(): array
    {
        $destinations = [
            ['label' => __('app.nav.jobs'), 'route' => 'jobs.index', 'pattern' => 'jobs.*'],
            ['label' => __('app.nav.appointments'), 'route' => 'appointments.index', 'pattern' => 'appointments.*'],
            ['label' => __('app.nav.customers'), 'route' => 'customers.index', 'pattern' => 'customers.*'],
            ['label' => __('app.nav.parts'), 'route' => 'parts.index', 'pattern' => 'parts.*'],
        ];

        if (Gate::allows('viewAny', Invoice::class)) {
            $destinations[] = [
                'label' => __('app.nav.invoices'),
                'route' => 'invoices.index',
                'pattern' => 'invoices.*',
            ];
        }

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
