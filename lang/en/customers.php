<?php

return [
    'title' => 'Customers',

    'index' => [
        'search_label' => 'Search customers',
        'search_placeholder' => 'Name, phone, plate or vehicle',
        'new' => 'New customer',
        'count' => ':count customer|:count customers',

        'headers' => [
            'name' => 'Name',
            'phone' => 'Phone',
            'vehicles' => 'Vehicles',
            'email' => 'Email',
        ],
    ],

    'empty' => [
        'none' => [
            'title' => 'No customers yet.',
            'body' => 'Add the first customer to start booking work in.',
        ],

        'no_matches' => [
            'title' => 'No customer matches that search.',
            'body' => 'Check the spelling, or try part of a phone number.',
        ],
    ],

    'show' => [
        'contact' => 'Contact details',
        'phone' => 'Phone',
        'email' => 'Email',
        'address' => 'Address',
        'notes' => 'Notes',
        'jobs' => 'Job history',
        'jobs_empty' => 'No jobs recorded yet.',
        'jobs_view_all_for' => 'View all :count jobs for :name',
    ],

    'create' => [
        'title' => 'New customer',
        'submit' => 'Create customer',
    ],

    'edit' => [
        'title' => 'Edit :name',
        'submit' => 'Save changes',
    ],

    'form' => [
        'name' => 'Name',
        'phone' => 'Phone',
        'email' => 'Email (optional)',
        'address' => 'Address (optional)',
        'notes' => 'Notes (optional)',
    ],

    'delete' => [
        'action' => 'Delete :name',
        'confirm' => 'Delete :name? They will no longer appear in the customer list. Nothing is purged.',
    ],

    'flash' => [
        'created' => ':name has been added.',
        'updated' => ':name has been updated.',
        'deleted' => ':name has been deleted.',
    ],
];
