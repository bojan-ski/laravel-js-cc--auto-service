<?php

namespace App\View\Components\Staff;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Form extends Component
{
    public function __construct(
        public string $action,
        public string $submit,
        public string $cancel,
        public string $method = 'POST',
        public ?User $member = null,
        public bool $lockedRole = false,
    ) {}

    /**
     * @return array<string, string>
     */
    public function roleOptions(): array
    {
        $options = [];

        foreach (UserRole::cases() as $role) {
            $options[$role->value] = $role->label();
        }

        return $options;
    }

    public function render(): View
    {
        return view('components.staff.form');
    }
}
