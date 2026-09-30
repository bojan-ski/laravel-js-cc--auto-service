<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(3)]
#[Timeout(30)]
#[Backoff(60)]
abstract class CustomerMailable extends Mailable implements ShouldQueue
{
    use SerializesModels;

    public bool $afterCommit = true;

    abstract protected function recipient(): Customer;

    abstract protected function record(): string;

    final public function failed(Throwable $exception): void
    {
        Log::error('Customer email failed to send.', [
            'mailable' => static::class,
            'recipient' => $this->recipient()->email,
            'record' => $this->record(),
            'reason' => $exception->getMessage(),
        ]);
    }
}
