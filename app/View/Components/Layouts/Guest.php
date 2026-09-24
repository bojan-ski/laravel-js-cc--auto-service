<?php

namespace App\View\Components\Layouts;

use App\Enums\Locale;
use App\Enums\Theme;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Guest extends Component
{
    public Theme $theme;

    public string $htmlLang;

    /**
     * @var array<int, array{value: string, label: string, current: bool, lang: string}>
     */
    public array $localeOptions;

    public function __construct(public string $title, public string $width = 'max-w-sm')
    {
        $user = request()->user();

        $this->theme = Theme::forUser($user);
        $this->htmlLang = Locale::current()->htmlLang();
        $this->localeOptions = $user === null ? Locale::options() : [];
    }

    public function render(): View
    {
        return view('components.layouts.guest');
    }
}
