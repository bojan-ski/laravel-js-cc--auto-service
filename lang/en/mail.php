<?php

return [

    'job_completed' => [
        'subject' => 'Your vehicle is ready — :plate',
        'greeting' => 'Dear :name,',
        'body' => 'Your :make :model (:plate) is ready for collection.',
        'reference' => 'Job reference: :reference',
    ],

    'invoice_issued' => [
        'subject' => 'Invoice :number — :plate',
        'greeting' => 'Dear :name,',
        'body' => 'Here is the invoice for work on your :make :model (:plate).',
        'number' => 'Invoice: :number',
        'issued' => 'Issued: :date',
        'due' => 'Due: :date',
        'subtotal' => 'Subtotal: :amount',
        'vat' => 'VAT :rate%: :amount',
        'total' => 'Total: :amount',
    ],

];
