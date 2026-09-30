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
        'status-received' => 'bg-status-received/10 text-status-received',
        'status-in-progress' => 'bg-status-in-progress/10 text-status-in-progress',
        'status-awaiting-parts' => 'bg-status-awaiting-parts/10 text-status-awaiting-parts',
        'status-completed' => 'bg-status-completed/10 text-status-completed',
        'status-unpaid' => 'bg-status-unpaid/10 text-status-unpaid',
        'status-paid' => 'bg-status-paid/10 text-status-paid',
        'status-void' => 'bg-status-void/10 text-status-void',
        'status-pending' => 'bg-status-pending/10 text-status-pending',
        'status-approved' => 'bg-status-approved/10 text-status-approved',
        'status-declined' => 'bg-status-declined/10 text-status-declined',
        'status-superseded' => 'bg-status-superseded/10 text-status-superseded',
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
