<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\Job;
use App\Models\Vehicle;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class JobCompletedMail extends CustomerMailable
{
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

    protected function recipient(): Customer
    {
        return $this->customer;
    }

    protected function record(): string
    {
        return $this->job->reference;
    }
}
