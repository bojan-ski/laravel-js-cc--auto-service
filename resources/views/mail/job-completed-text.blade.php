{{ __('mail.job_completed.greeting', ['name' => $customer->name]) }}

{{ __('mail.job_completed.body', ['make' => $vehicle->make, 'model' => $vehicle->model, 'plate' => $vehicle->registration_plate]) }}

{{ __('mail.pair', ['label' => __('mail.job_completed.reference'), 'value' => $job->reference]) }}

<x-mail.shop-footer-text />
