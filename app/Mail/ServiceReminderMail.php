<?php

namespace App\Mail;

use App\Enums\ReminderKind;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleReminder;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ServiceReminderMail extends CustomerMailable
{
    public function __construct(
        public VehicleReminder $reminder,
        public Customer $customer,
        public Vehicle $vehicle,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.service-reminder',
            text: 'mail.service-reminder-text',
            with: [
                'subjectLine' => $this->subjectLine(),
                'heading' => $this->heading(),
                'body' => $this->body(),
                'rows' => $this->rows(),
            ],
        );
    }

    protected function recipient(): Customer
    {
        return $this->customer;
    }

    protected function record(): string
    {
        return "{$this->vehicle->registration_plate} {$this->reminder->kind->value} {$this->reminder->due_on->format('Y-m-d')}";
    }

    private function subjectLine(): string
    {
        $plate = ['plate' => $this->vehicle->registration_plate];

        return match ($this->reminder->kind) {
            ReminderKind::Service => __('mail.service_reminder.subject_service', $plate),
            ReminderKind::Registration => __('mail.service_reminder.subject_registration', $plate),
        };
    }

    private function heading(): string
    {
        return match ($this->reminder->kind) {
            ReminderKind::Service => __('mail.service_reminder.heading_service'),
            ReminderKind::Registration => __('mail.service_reminder.heading_registration'),
        };
    }

    private function body(): string
    {
        $replace = [
            'make' => $this->vehicle->make,
            'model' => $this->vehicle->model,
            'plate' => $this->vehicle->registration_plate,
            'date' => $this->dueDate(),
        ];

        return match ($this->reminder->kind) {
            ReminderKind::Service => __('mail.service_reminder.body_service', $replace),
            ReminderKind::Registration => __('mail.service_reminder.body_registration', $replace),
        };
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function rows(): array
    {
        if ($this->reminder->kind === ReminderKind::Registration) {
            return [['label' => __('mail.service_reminder.expires_on'), 'value' => $this->dueDate()]];
        }

        $rows = [['label' => __('mail.service_reminder.due_on'), 'value' => $this->dueDate()]];

        if ($this->vehicle->next_service_mileage !== null) {
            $rows[] = [
                'label' => __('mail.service_reminder.or_at'),
                'value' => __('vehicles.show.mileage_value', ['mileage' => number_format($this->vehicle->next_service_mileage)]),
            ];
        }

        return $rows;
    }

    private function dueDate(): string
    {
        return $this->reminder->due_on->isoFormat('LL');
    }
}
