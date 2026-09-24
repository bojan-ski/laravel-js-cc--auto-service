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
        'new_appointment' => 'New appointment',
        'set_hours' => 'Set hours',
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

    'show' => [
        'title' => ':plate booked :time',
        'details' => 'Appointment details',
        'customer' => 'Customer',
        'duration' => 'Duration',
        'duration_value' => ':minutes minutes',
        'notes' => 'Notes',
        'booked_by' => 'Booked by',
        'arrived' => 'Arrived',
        'no_show' => 'Marked no-show',
        'cancelled' => 'Cancelled',
        'job' => 'Job',
    ],

    'actions' => [
        'arrived' => 'Arrived',
        'no_show' => 'No-show',
        'cancel' => 'Cancel appointment',
        'edit' => 'Reschedule',
        'mark_arrived_for' => 'Arrived: open a job for :plate booked at :time',
        'mark_no_show_for' => 'Mark :plate at :time as a no-show',
        'cancel_for' => 'Cancel appointment for :plate at :time',
        'edit_for' => 'Reschedule :plate at :time',
        'confirm_cancel' => 'Cancel the appointment for :plate at :time? This cannot be undone.',
    ],

    'form' => [
        'create_title' => 'New appointment',
        'edit_title' => 'Reschedule appointment',
        'vehicle' => 'Vehicle',
        'vehicle_placeholder' => 'Select a vehicle',
        'vehicle_fixed' => 'Vehicle',
        'vehicle_fixed_hint' => 'The vehicle cannot be changed. Cancel this appointment and create a new one instead.',
        'scheduled_at' => 'Date and time',
        'scheduled_at_hint' => 'From tomorrow onward, starting and finishing between :open and :close.',
        'duration_minutes' => 'Duration (minutes)',
        'notes' => 'Notes',
        'submit_create' => 'Book',
        'submit_update' => 'Save changes',
        'back' => 'Cancel',
    ],

    'overlap' => [
        'warning' => '{1}1 other car is already booked in this window.|[2,*]:count other cars are already booked in this window.',
    ],

    'validation' => [
        'not_before_tomorrow' => 'Appointments can only be booked from tomorrow onward.',
        'outside_hours' => 'The appointment must start and finish between :open and :close.',
    ],

    'hours' => [
        'title' => 'Appointment hours',
        'intro' => 'New and rescheduled appointments must start and finish inside these hours. Existing appointments are not changed.',
        'open_at' => 'Opens',
        'close_at' => 'Closes',
        'close_after_open' => 'Closing time must be after opening time.',
        'submit' => 'Save hours',
        'cancel' => 'Cancel',
    ],

    'flash' => [
        'created' => 'Appointment created for :plate.',
        'updated' => 'Appointment updated for :plate.',
        'no_show' => ':plate marked as a no-show.',
        'cancelled' => 'Appointment cancelled for :plate.',
        'hours_updated' => 'Appointment hours set to :open – :close.',
    ],
];
