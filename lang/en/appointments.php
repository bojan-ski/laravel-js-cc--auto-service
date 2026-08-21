<?php

return [
    'statuses' => [
        'booked' => 'Booked',
        'arrived' => 'Arrived',
        'no_show' => 'No-show',
        'cancelled' => 'Cancelled',
    ],

    'title' => 'Appointments',

    'index' => [
        'new_booking' => 'New booking',
        'view_label' => 'View mode',
        'day' => 'Day',
        'week' => 'Week',
        'previous' => 'Previous',
        'next' => 'Next',
        'today' => 'Today',
        'status_label' => 'Status',
        'status_all' => 'All',
        'count' => '{0}No appointments|{1}1 appointment|[2,*]:count appointments',
        'nothing_booked' => 'Nothing booked',
        'week_range' => ':start — :end',
    ],

    'columns' => [
        'time' => 'Time',
        'plate' => 'Plate',
        'customer' => 'Customer',
        'status' => 'Status',
        'actions' => 'Actions',
    ],

    'empty' => [
        'none_title' => 'No appointments yet',
        'none_body' => 'Book the first appointment to see it here.',
        'window_title' => 'Nothing booked in this period',
        'window_body' => 'Jump back to today, or move to another day or week.',
        'filtered_title' => 'No appointments match this status',
        'filtered_body' => 'Clear the status filter to see everything in this period.',
        'clear_filter' => 'Clear status filter',
    ],

    'actions' => [
        'arrived' => 'Arrived',
        'no_show' => 'No-show',
        'cancel' => 'Cancel booking',
        'edit' => 'Reschedule',
        'mark_arrived_for' => 'Mark :plate at :time as arrived',
        'mark_no_show_for' => 'Mark :plate at :time as a no-show',
        'cancel_for' => 'Cancel booking for :plate at :time',
        'edit_for' => 'Reschedule :plate at :time',
        'confirm_cancel' => 'Cancel the booking for :plate at :time? This cannot be undone.',
    ],

    'form' => [
        'create_title' => 'New booking',
        'edit_title' => 'Reschedule booking',
        'vehicle' => 'Vehicle',
        'vehicle_placeholder' => 'Select a vehicle',
        'vehicle_option' => ':plate · :vehicle · :owner',
        'vehicle_fixed' => 'Vehicle',
        'vehicle_fixed_hint' => 'The vehicle cannot be changed. Cancel this booking and create a new one instead.',
        'scheduled_at' => 'Date and time',
        'duration_minutes' => 'Duration (minutes)',
        'notes' => 'Notes',
        'submit_create' => 'Book',
        'submit_update' => 'Save changes',
        'back' => 'Cancel',
    ],

    'overlap' => [
        'warning' => '{1}1 other car is already booked in this window.|[2,*]:count other cars are already booked in this window.',
        'none' => 'No other cars are booked in this window.',
    ],

    'flash' => [
        'created' => 'Booking created for :plate.',
        'updated' => 'Booking updated for :plate.',
        'arrived' => ':plate marked as arrived.',
        'no_show' => ':plate marked as a no-show.',
        'cancelled' => 'Booking cancelled for :plate.',
    ],
];
