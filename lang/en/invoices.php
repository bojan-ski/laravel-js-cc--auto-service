<?php

return [
    'statuses' => [
        'unpaid' => 'Unpaid',
        'paid' => 'Paid',
        'void' => 'Void',
    ],

    'title' => 'Invoices',
    'count' => '{0}No invoices|{1}One invoice|[2,*]:count invoices',

    'tabs' => [
        'label' => 'Filter invoices by status',
        'all' => 'All',
    ],

    'search_label' => 'Search invoices',
    'search_placeholder' => 'Invoice number or customer name',

    'headers' => [
        'number' => 'Number',
        'customer' => 'Customer',
        'issued' => 'Issued',
        'total' => 'Total',
        'status' => 'Status',
    ],

    'empty' => [
        'none' => [
            'title' => 'No invoices yet.',
            'body' => 'An invoice is issued from a completed job, not created here.',
        ],

        'none_in_status' => [
            'title' => 'No invoice has that status.',
            'body' => 'Try another status, or view them all.',
            'clear' => 'Show all invoices',
        ],

        'no_matches' => [
            'title' => 'No invoice matches that search.',
            'body' => 'Search by invoice number or customer name.',
        ],
    ],

    'issue' => [
        'title' => 'Issue invoice · :reference',
        'heading' => 'Issue invoice',
        'due_at' => 'Due date (optional)',
        'warning_title' => 'Issuing locks this job.',
        'warning_body' => 'Line items become read-only and only an admin can void the invoice to reopen it.',
        'submit' => 'Issue invoice',
        'submit_for' => 'Issue invoice for job :reference',
        'cancel' => 'Cancel',
    ],

    'totals' => [
        'subtotal' => 'Subtotal',
        'vat' => 'VAT :rate%',
        'total' => 'Total',
    ],

    'show' => [
        'title' => 'Invoice :number',
        'details' => 'Invoice details',
        'job' => 'Job',
        'customer' => 'Customer',
        'vehicle' => 'Vehicle',
        'issued' => 'Issued',
        'issued_by' => 'Issued by',
        'due' => 'Due',
        'line_items' => 'Line items',
        'actions' => 'Invoice actions',
        'print' => 'Print',
        'mark_paid' => 'Mark paid',
        'mark_paid_for' => 'Mark paid for invoice :number',
        'confirm_paid' => 'Mark invoice :number as paid? This cannot be undone — a payment recorded in error is corrected by voiding the invoice and issuing a new one.',
    ],

    'items' => [
        'description' => 'Description',
        'quantity' => 'Qty',
        'price' => 'Unit price',
        'line_total' => 'Line total',
    ],

    'void' => [
        'title' => 'This invoice was voided.',
        'body' => 'Its job has been reopened and its line items may have changed since. The totals below are what was invoiced.',
        'view_job' => 'View job',
        'view_job_for' => 'View job :reference',

        'action' => 'Void invoice',
        'action_for' => 'Void invoice :number',
        'page_title' => 'Void invoice · :number',
        'heading' => 'Void invoice',
        'warning_title' => 'This cancels the invoice and unlocks job :reference.',
        'warning_body' => 'The job stays completed. Reopen it to edit its line items, then complete it again to issue a new invoice.',
        'consequences' => [
            'kept' => 'The invoice is kept and stays visible, marked void.',
            'number' => 'Its number :number is never reused.',
            'stock' => 'Parts already fitted are not returned to stock.',
            'reissue' => 'A new invoice can then be issued, with new totals.',
        ],
        'paid_notice' => 'This invoice was marked paid on :date. Voiding it cancels a settled bill.',
        'cancel' => 'Cancel',
        'voided' => 'Voided',
        'voided_by' => 'Voided by',
        'print_marker' => 'VOID',
    ],

    'identity' => [
        'heading' => 'Invoice :number',
        'issued' => 'Issued :date',
        'bill_to' => 'Bill to',
    ],

    'flash' => [
        'issued' => 'Invoice :number issued. This job is now locked.',
        'not_issuable' => 'This job could not be invoiced. It may already have been invoiced by someone else.',
        'paid' => 'Invoice :number is marked paid.',
        'not_payable' => 'This invoice could not be marked paid. Its status may have changed since the page was loaded.',
        'voided' => 'Invoice :number is void. Job :reference is unlocked.',
        'not_voidable' => 'This invoice could not be voided. Its status may have changed since the page was loaded.',
    ],
];
