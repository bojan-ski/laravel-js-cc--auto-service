<?php

namespace App\View\Components\Ui;

use App\Contracts\ProvidesStatusPresentation;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class StatusBadge extends Component
{
    /**
     * @var array<string, string>
     */
    private const TOKEN_CLASSES = [
        'status-booked' => 'bg-status-booked/10 text-status-booked',
        'status-arrived' => 'bg-status-arrived/10 text-status-arrived',
        'status-no-show' => 'bg-status-no-show/10 text-status-no-show',
        'status-cancelled' => 'bg-status-cancelled/10 text-status-cancelled',
    ];

    public function __construct(
        public ProvidesStatusPresentation $status,
    ) {}

    public function classes(): string
    {
        return self::TOKEN_CLASSES[$this->status->colorToken()] ?? 'bg-surface-muted text-text-soft';
    }

    public function render(): View
    {
        return view('components.ui.status-badge');
    }
}
