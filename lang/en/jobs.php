<?php

return [
    'statuses' => [
        'received' => 'Received',
        'in_progress' => 'In progress',
        'awaiting_parts' => 'Awaiting parts',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
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
