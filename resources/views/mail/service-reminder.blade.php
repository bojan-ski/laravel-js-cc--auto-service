<x-mail.layout :title="$subjectLine" :heading="$heading" :preheader="$body">
    <p style="margin: 0 0 12px;">{{ __('mail.service_reminder.greeting', ['name' => $customer->name]) }}</p>

    <p style="margin: 0;">{{ $body }}</p>

    <x-mail.vehicle :vehicle="$vehicle" />

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 16px;">
        @foreach ($rows as $row)
            <x-mail.row :label="$row['label']" :value="$row['value']" />
        @endforeach
    </table>

    <p style="margin: 0 0 12px;">{{ __('mail.service_reminder.book') }}</p>

    <p style="margin: 0; color: #57534E;">{{ __('mail.service_reminder.opt_out') }}</p>
</x-mail.layout>
