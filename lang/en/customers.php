<?php

return [
    'title' => 'Customers',

    'index' => [
        'search_label' => 'Search customers',
        'search_placeholder' => 'Name or phone',
        'new' => 'New customer',
        'count' => ':count customer|:count customers',
        'view_customer' => 'View :name',

        'headers' => [
            'name' => 'Name',
            'phone' => 'Phone',
            'email' => 'Email',
            'actions' => 'Actions',
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
        'vehicles' => 'Vehicles',
        'vehicles_empty' => 'No vehicles recorded yet.',
        'jobs' => 'Job history',
        'jobs_empty' => 'No jobs recorded yet.',
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
