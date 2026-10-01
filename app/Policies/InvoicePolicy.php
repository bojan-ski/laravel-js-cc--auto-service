<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isFrontOffice();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->isFrontOffice();
    }

    /**
     * Determine whether the user can record payment of the model.
     */
    public function markPaid(User $user, Invoice $invoice): bool
    {
        return $user->isFrontOffice() && $invoice->canBeMarkedPaid();
    }

    /**
     * Determine whether the user can view reports and export invoices.
     */
    public function viewReports(User $user): bool
    {
        return $user->isFrontOffice();
    }

    /**
     * Determine whether the user can void the model, unlocking its job.
     */
    public function void(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() && $invoice->canBeVoided();
    }
}
