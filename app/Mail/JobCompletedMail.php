<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\Job;
use App\Models\Vehicle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(3)]
#[Timeout(30)]
#[Backoff(60)]
class JobCompletedMail extends Mailable implements ShouldQueue
{
    use SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public Job $job,
        public Customer $customer,
        public Vehicle $vehicle,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.job_completed.subject', [
                'plate' => $this->vehicle->registration_plate,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.job-completed',
            text: 'mail.job-completed-text',
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Customer email failed to send.', [
            'mailable' => self::class,
            'recipient' => $this->customer->email,
            'record' => $this->job->reference,
            'reason' => $exception->getMessage(),
        ]);
    }
}
