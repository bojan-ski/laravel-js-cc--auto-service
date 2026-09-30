<?php

return [
    'statuses' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'declined' => 'Declined',
        'superseded' => 'Superseded',
    ],

    'decision_methods' => [
        'in_person' => 'In person',
        'phone' => 'Phone',
        'email' => 'Email',
    ],

    'show' => [
        'title' => 'Estimate :number',
        'details' => 'Estimate details',
        'actions' => 'Actions',
        'job' => 'Job',
        'customer' => 'Customer',
        'vehicle' => 'Vehicle',
        'created' => 'Created',
        'created_by' => 'Created by',
        'decided' => 'Decided',
        'decided_by' => 'Recorded by',
        'decision_method' => 'How',
        'decision_note' => 'Note',
        'superseded' => 'Superseded',
        'emailed' => 'Last emailed',
        'print' => 'Print',
        'back' => 'Back to job',
        'superseded_notice' => 'This estimate was replaced on :date. It is kept as a record.',
        'history' => 'Estimates for this job',
    ],

    'history_headers' => [
        'number' => 'Number',
        'status' => 'Status',
        'created' => 'Created',
        'total' => 'Total',
    ],

    'identity' => [
        'heading' => 'Estimate :number',
        'created' => 'Date :date',
        'prepared_for' => 'Prepared for',
    ],

    'decision' => [
        'title' => "Record the customer's decision",
        'decision' => 'Decision',
        'approve' => 'Approved',
        'decline' => 'Declined',
        'method' => 'How the customer answered',
        'note' => 'Note',
        'submit' => 'Record decision',
        'submit_for' => 'Record decision on estimate :number',
    ],

    'email' => [
        'action' => 'Email to customer',
        'action_for' => 'Email to customer: estimate :number',
    ],

    'create' => [
        'action' => 'Create estimate',
        'action_for' => 'Create estimate for :reference',
        'revise' => 'Revise estimate',
        'revise_for' => 'Revise estimate for :reference',
        'revise_confirm' => 'This creates a new estimate from the current lines. Estimate :number will be marked superseded.',
    ],

    'summary' => [
        'label' => 'Estimate',
        'none' => 'No estimate',
        'method' => '(:method)',
        'view' => 'View',
        'view_for' => 'View estimate :number',
    ],

    'warning' => [
        'over' => 'Current total is :amount over approved estimate :number.',
        'not_approved' => 'Estimate :number has not been approved.',
        'declined' => 'Estimate :number was declined.',
    ],

    'flash' => [
        'created' => 'Estimate :number created.',
        'approved' => 'Approval recorded on estimate :number.',
        'declined' => 'Decline recorded on estimate :number.',
        'emailed' => 'Estimate :number is being emailed to :email.',
        'no_email' => 'The customer has no email on file, so the estimate was not sent.',
        'not_creatable' => 'An estimate needs a working job with at least one line.',
        'not_decidable' => 'That estimate has already been decided or replaced.',
        'not_emailable' => 'Only a pending or approved estimate can be emailed.',
    ],
];
