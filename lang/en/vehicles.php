<?php

return [
    'title' => 'Vehicles',

    'section' => [
        'title' => 'Vehicles',
        'empty' => 'No vehicles recorded yet.',
        'add' => 'Add vehicle',
        'add_for' => 'Add vehicle for :name',
        'view_vehicle' => 'View :plate',

        'headers' => [
            'plate' => 'Plate',
            'vehicle' => 'Vehicle',
            'year' => 'Year',
            'actions' => 'Actions',
        ],
    ],

    'deleted' => [
        'title' => 'Deleted vehicles',
        'intro' => 'These vehicles are hidden everywhere else. Their plates stay reserved until they are restored.',
        'restore' => 'Restore',
        'restore_vehicle' => 'Restore :plate',
        'confirm' => 'Restore :plate? It returns to this customer with its plate and history intact.',
    ],

    'show' => [
        'details' => 'Vehicle details',
        'owner' => 'Owner',
        'owner_deleted' => 'deleted',
        'year' => 'Year',
        'engine' => 'Engine',
        'vin' => 'VIN',
        'mileage' => 'Mileage',
        'notes' => 'Notes',
        'mileage_value' => ':mileage km',
        'history' => 'Service history',
        'history_empty' => 'No jobs recorded for this vehicle yet.',
        'history_view_all_for' => 'View all :count jobs for :plate',
    ],

    'create' => [
        'title' => 'New vehicle',
        'for' => 'For :name',
        'submit' => 'Add vehicle',
    ],

    'edit' => [
        'title' => 'Edit :plate',
        'submit' => 'Save changes',
    ],

    'picker' => [
        'option' => ':plate · :vehicle · :owner',
    ],

    'form' => [
        'owner' => 'Owner',
        'registration_plate' => 'Registration plate',
        'make' => 'Make',
        'model' => 'Model',
        'year' => 'Year (optional)',
        'vin' => 'VIN (optional)',
        'engine' => 'Engine (optional)',
        'mileage' => 'Mileage (optional)',
        'notes' => 'Notes (optional)',
    ],

    'delete' => [
        'action' => 'Delete :plate',
        'confirm' => 'Delete :plate? The plate stays reserved and cannot be used for another vehicle until this one is restored.',
    ],

    'reassign' => [
        'action' => 'Reassign',
        'action_vehicle' => 'Reassign :plate to another customer',
        'title' => 'Reassign :plate',
        'current_owner' => 'Current owner',
        'new_owner' => 'New owner',
        'placeholder' => 'Choose a customer',
        'warning_title' => 'History is preserved',
        'warning' => 'Only the vehicle moves. Existing jobs and invoices keep :name as their customer, so past work is never rebilled.',
        'submit' => 'Reassign vehicle',
        'confirm' => 'Reassign :plate from :name to :selected? Existing jobs and invoices keep :name as their customer.',
    ],

    'validation' => [
        'plate_deleted' => 'That plate belongs to a deleted vehicle owned by :owner. An administrator can restore it from that customer\'s page.',
        'vin_deleted' => 'That VIN belongs to a deleted vehicle owned by :owner. An administrator can restore it from that customer\'s page.',
        'already_owner' => 'That customer already owns this vehicle.',
    ],

    'flash' => [
        'created' => ':plate has been added.',
        'updated' => ':plate has been updated.',
        'deleted' => ':plate has been deleted. Its plate stays reserved.',
        'restored' => ':plate has been restored.',
        'reassigned' => ':plate now belongs to :name.',
    ],
];
