<?php

return [

    'vehicle' => 'Vehicle',
    'pair' => ':label: :value',

    'notice' => [
        'queued' => 'The customer is being emailed at :email.',
        'skipped' => 'The customer has no email on file, so no email was sent.',
    ],

    'job_completed' => [
        'subject' => 'Your vehicle is ready — :plate',
        'heading' => 'Your vehicle is ready',
        'greeting' => 'Dear :name,',
        'body' => 'Your :make :model (:plate) is ready for collection.',
        'reference' => 'Job reference',
    ],

    'invoice_issued' => [
        'subject' => 'Invoice :number — :plate',
        'heading' => 'Invoice :number',
        'greeting' => 'Dear :name,',
        'body' => 'Here is the invoice for work on your :make :model (:plate).',
        'number' => 'Invoice',
        'issued' => 'Issued',
        'due' => 'Due',
        'subtotal' => 'Subtotal',
        'vat' => 'VAT :rate%',
        'total' => 'Total',
        'tax_id' => 'Tax ID (PIB)',
        'registration_number' => 'Registration no. (MB)',
    ],

];
