<?php

return [
    'statuses' => [
        'unpaid' => 'Unpaid',
        'paid' => 'Paid',
        'void' => 'Void',
    ],

    'issue' => [
        'title' => 'Issue invoice · :reference',
        'heading' => 'Issue invoice',
        'subtotal' => 'Subtotal',
        'vat' => 'VAT :rate%',
        'total' => 'Total',
        'due_at' => 'Due date (optional)',
        'warning_title' => 'Issuing locks this job.',
        'warning_body' => 'Line items become read-only and only an admin can void the invoice to reopen it.',
        'submit' => 'Issue invoice',
        'submit_for' => 'Issue invoice for job :reference',
        'cancel' => 'Cancel',
    ],

    'flash' => [
        'issued' => 'Invoice :number issued. This job is now locked.',
        'not_issuable' => 'This job could not be invoiced. It may already have been invoiced by someone else.',
    ],
];
