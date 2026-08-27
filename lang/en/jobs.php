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

    'show' => [
        'complaint' => 'Complaint',
        'details' => 'Job details',
        'mileage_in' => 'Mileage in',
        'mileage_value' => ':mileage km',
        'origin' => 'Origin',
        'origin_booking' => 'Booked :time',
        'walk_in' => 'Walk-in',
        'opened_by' => 'Opened by',
        'received' => 'Received',
        'started' => 'Started',
        'awaiting_parts' => 'Awaiting parts',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'line_items' => 'Line items',
        'line_items_empty' => 'No line items recorded yet.',
        'attachments' => 'Photos & documents',
        'attachments_empty' => 'Nothing uploaded yet.',

        'lock' => [
            'title' => 'Locked — invoiced :date',
            'body' => 'Line items are read-only. An admin must void the invoice to reopen this job.',
        ],
    ],

    'history' => [
        'view_all' => 'View all :count jobs',

        'headers' => [
            'reference' => 'Job',
            'plate' => 'Plate',
            'customer' => 'Customer',
            'status' => 'Status',
            'received' => 'Received',
            'mileage' => 'Mileage',
        ],
    ],

    'actions' => [
        'start' => 'Start',
        'pause' => 'Awaiting parts',
        'resume' => 'Resume',
        'complete' => 'Complete',
        'cancel' => 'Cancel job',
        'reopen' => 'Reopen',
        'start_for' => 'Start job :reference for :plate',
        'pause_for' => 'Awaiting parts for job :reference for :plate',
        'resume_for' => 'Resume job :reference for :plate',
        'complete_for' => 'Complete job :reference for :plate',
        'cancel_for' => 'Cancel job :reference for :plate',
        'reopen_for' => 'Reopen job :reference for :plate',
        'confirm_cancel' => 'Cancel job :reference for :plate? Cancelling is permanent — a cancelled job cannot be reopened.',
    ],

    'flash' => [
        'created' => 'Job :reference opened.',
        'mileage_lower' => 'The entered mileage (:entered km) is lower than the last known :stored km, so the vehicle\'s mileage was not updated.',
        'started' => 'Job :reference started.',
        'paused' => 'Job :reference is waiting for parts.',
        'resumed' => 'Job :reference resumed.',
        'completed' => 'Job :reference completed.',
        'cancelled' => 'Job :reference cancelled.',
        'reopened' => 'Job :reference reopened.',
    ],
];
