<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:send')]
#[Description('Queue the service and registration reminders that are due')]
class SendServiceReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ReminderService $reminders): int
    {
        $queued = $reminders->sendDue(CarbonImmutable::today());

        $this->info("Queued {$queued} reminder(s).");

        return self::SUCCESS;
    }
}
