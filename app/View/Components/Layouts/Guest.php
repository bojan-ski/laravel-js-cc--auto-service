<?php

namespace App\View\Components\Layouts;

use App\Enums\Theme;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Guest extends Component
{
    public Theme $theme;

    public function __construct(public string $title, public string $width = 'max-w-sm')
    {
        $this->theme = Theme::forUser(request()->user());
    }

    public function render(): View
    {
        return view('components.layouts.guest');
    }
}
