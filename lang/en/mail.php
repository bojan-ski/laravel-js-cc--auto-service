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
        'tax_id' => 'Tax ID (PIB)',
        'registration_number' => 'Registration no. (MB)',
    ],

    'estimate' => [
        'subject' => 'Estimate :number — :plate',
        'heading' => 'Estimate :number',
        'greeting' => 'Dear :name,',
        'body' => 'Here is the estimate for work on your :make :model (:plate).',
        'reply' => 'Please contact us to approve or decline it.',
        'number' => 'Estimate',
        'created' => 'Date',
        'line' => ':description × :quantity',
        'line_discounted' => ':description × :quantity (:percent% off)',
    ],

    'totals' => [
        'before_discount' => 'Before discount',
        'discount' => 'Discount',
        'subtotal' => 'Subtotal',
        'vat' => 'VAT :rate%',
        'total' => 'Total',
    ],

];
