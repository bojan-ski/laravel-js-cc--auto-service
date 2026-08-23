<?php

return [
    'statuses' => [
        'received' => 'Received',
        'in_progress' => 'In progress',
        'awaiting_parts' => 'Awaiting parts',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'board' => [
        'title' => 'Jobs',
        'new' => 'New job',
        'count' => ':count job|:count jobs',
        'tabs_label' => 'Filter by status',
        'tab_all' => 'All',
        'search_label' => 'Search jobs',
        'search_placeholder' => 'Plate or customer',
        'sort_by' => 'Sort by :column, :direction',
        'ascending' => 'ascending',
        'descending' => 'descending',

        'headers' => [
            'plate' => 'Plate',
            'vehicle' => 'Vehicle',
            'customer' => 'Customer',
            'status' => 'Status',
            'received' => 'Received',
            'assigned' => 'Assigned',
        ],

        'empty' => [
            'none' => [
                'title' => 'No jobs yet.',
                'body' => 'Open the first job from an arrived appointment or as a walk-in.',
            ],

            'none_in_status' => [
                'title' => 'No jobs in this status.',
                'body' => 'Show all statuses to see everything on the board.',
                'clear' => 'Show all statuses',
            ],

            'no_matches' => [
                'title' => 'No job matches that search.',
                'body' => 'Check the plate or try part of the customer\'s name.',
            ],
        ],
    ],

    'form' => [
        'create_title' => 'New job',
        'appointment' => 'Appointment',
        'appointment_summary' => ':time · booked by :name',
        'vehicle' => 'Vehicle',
        'vehicle_placeholder' => 'Select a vehicle',
        'complaint' => 'Complaint',
        'complaint_placeholder' => 'What did the customer say is wrong? Use their words.',
        'mileage_in' => 'Mileage in (km, optional)',
        'last_known_mileage' => 'Last known :mileage km',
        'submit_walk_in' => 'Open job',
        'submit_from_appointment' => 'Arrive & open job',
        'back' => 'Cancel',
    ],

    'validation' => [
        'appointment_not_booked' => 'That appointment is no longer booked, so it cannot be marked as arrived.',
    ],

    'flash' => [
        'created' => 'Job :reference opened.',
        'mileage_lower' => 'The entered mileage (:entered km) is lower than the last known :stored km, so the vehicle\'s mileage was not updated.',
    ],
];
