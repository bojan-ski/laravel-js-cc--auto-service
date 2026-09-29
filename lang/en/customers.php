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
        'tax_id' => 'Tax ID (PIB)',
        'registration_number' => 'Registration no. (MB)',
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
        'company' => 'Company',
        'company_hint' => 'For a company, enter both its PIB and MB. Leave both empty for a private customer.',
        'tax_id' => 'Tax ID (PIB)',
        'registration_number' => 'Registration no. (MB)',
        'notes' => 'Notes (optional)',
    ],

    'validation' => [
        'phone_taken' => 'This phone number already belongs to :name.',
        'tax_id_digits' => 'A PIB has exactly 9 digits.',
        'tax_id_checksum' => 'This PIB\'s check digit is wrong. Check the number for a typo.',
        'registration_number_digits' => 'An MB has exactly 8 digits.',
        'registration_number_required' => 'A company needs its MB as well as its PIB.',
        'registration_number_without_tax_id' => 'An MB is recorded only for a company. Enter the PIB too, or leave the MB empty.',
    ],

    'intake' => [
        'legend' => 'Customer and vehicle',
        'customer' => 'customer',
        'vehicle' => 'vehicle',
        'attributes' => [
            'new_customer_name' => 'name',
            'new_customer_phone' => 'phone',
            'new_customer_email' => 'email',
            'new_vehicle_plate' => 'registration plate',
            'new_vehicle_make' => 'make',
            'new_vehicle_model' => 'model',
            'new_vehicle_year' => 'year',
        ],
        'search_label' => 'Find a customer',
        'search_hint' => 'Name, phone or plate. Leave it empty to add a new customer below.',
        'results' => ':count customer found|:count customers found',
        'results_none' => 'No customer matches. Fill in the new customer below.',
        'picked' => 'Customer',
        'change' => 'Change customer',
        'new_customer' => 'New customer',
        'existing_vehicle' => 'Existing vehicle',
        'existing_vehicle_placeholder' => 'Select a vehicle, or add a new customer below',
        'vehicle_legend' => 'Vehicle',
        'new_vehicle_option' => 'New vehicle',
        'new_vehicle' => 'New vehicle',
        'locked_hint' => 'Enter the customer\'s name and phone first.',
        'required_customer' => 'Enter the new customer\'s :attribute, or find an existing customer.',
        'required_vehicle' => 'Enter the vehicle\'s :attribute, or choose an existing vehicle.',
        'vehicle_not_theirs' => 'That vehicle does not belong to the selected customer.',
        'plate_taken' => ':plate already belongs to :owner.',
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
