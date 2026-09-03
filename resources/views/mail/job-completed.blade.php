<div style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.5; color: #1C1917;">
    <p>{{ __('mail.job_completed.greeting', ['name' => $customer->name]) }}</p>

    <p>
        {{ __('mail.job_completed.body', [
            'make' => $vehicle->make,
            'model' => $vehicle->model,
            'plate' => $vehicle->registration_plate,
        ]) }}
    </p>

    <p>{{ __('mail.job_completed.reference', ['reference' => $job->reference]) }}</p>

    <x-mail.shop-footer />
</div>
