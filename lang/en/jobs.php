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

        'assigned_more' => ':count more person|:count more people',

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
            'invoice' => 'Invoice',
            'view_invoice' => 'View invoice',
            'view_invoice_for' => 'View invoice :number',
        ],
    ],

    'items' => [
        'types' => [
            'labor' => 'Labor',
            'part' => 'Part',
        ],

        'headers' => [
            'type' => 'Type',
            'description' => 'Description',
            'quantity' => 'Qty',
            'price' => 'Price',
            'who' => 'Who',
            'actions' => 'Actions',
        ],

        'subtotal' => 'Subtotal',
        'complete_hint' => 'Add at least one line item before this job can be completed.',

        'add' => [
            'title' => 'Add labor',
            'description' => 'Description',
            'description_placeholder' => 'What was done?',
            'description_placeholder_part' => 'Optional note about this part',
            'quantity' => 'Hours',
            'who' => 'Performed by',
            'who_placeholder' => 'Select who performed the work',
            'who_unassigned' => 'Unassigned',
            'rate' => 'Hourly rate',
            'submit' => 'Add line',
            'group_label' => 'Add a line item',
            'labor_link' => '+ Labor',
            'labor_link_label' => 'Add a labor line',
            'part_link' => '+ Part',
            'part_link_label' => 'Add a part line',
            'no_parts' => 'The parts catalogue is empty, so there is no part to add yet.',
            'part_title' => 'Add part',
            'part' => 'Part',
            'quantity_units' => 'Quantity',
            'price' => 'Price (leave blank for the catalogue price)',
            'submit_part' => 'Add part',
        ],

        'edit' => [
            'title_for' => 'Edit line item: :description',
            'price' => 'Price',
            'row_marker' => 'This line is being edited below.',
            'open' => 'Edit',
            'open_line' => 'Edit :description',
            'save' => 'Save',
            'cancel' => 'Cancel',
        ],

        'delete' => [
            'label' => 'Delete',
            'label_line' => 'Delete :description',
            'confirm' => 'Delete ":description"? Line items are deleted permanently.',
        ],

        'flash' => [
            'added' => 'Line item added.',
            'part_added' => 'Part line added and taken off the shelf.',
            'updated' => 'Line item updated.',
            'deleted' => 'Line item deleted.',
            'stock_negative' => ':name is now at :balance. Stock has gone negative.',
        ],
    ],

    'attachments' => [
        'types' => [
            'photo' => 'Photo',
            'document' => 'Document',
        ],

        'photos_label' => 'Photos',
        'documents_label' => 'Documents',
        'uploaded_by' => ':name · :date',
        'photo_alt' => 'Photo of job :reference uploaded by :name',
        'open_photo' => 'Open full-size photo of job :reference uploaded by :name on :date',

        'upload' => [
            'label' => 'Add a photo or document',
            'action' => 'Upload',
            'action_for' => 'Upload a file to job :reference',
            'hint' => 'JPEG, PNG or WebP up to 10 MB, or a PDF up to 20 MB.',
        ],

        'download' => [
            'action' => 'Download',
            'action_for' => 'Download :filename',
        ],

        'delete' => [
            'action' => 'Delete',
            'action_for' => 'Delete :filename',
            'confirm' => 'Delete :filename? The file is removed permanently and cannot be restored.',
        ],

        'lightbox' => [
            'label' => 'Photo viewer',
            'close' => 'Close photo viewer',
        ],

        'flash' => [
            'uploaded' => 'File uploaded.',
            'deleted' => 'File deleted.',
            'unreadable' => 'That file could not be read as an image and was not saved.',
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
        'issue' => 'Issue invoice',
        'issue_for' => 'Issue invoice for job :reference for :plate',
        'confirm_cancel' => 'Cancel job :reference for :plate? Cancelling is permanent — a cancelled job cannot be reopened.',
        'confirm_cancel_with_parts' => '{1} Cancel job :reference for :plate? One part line will be returned to stock. Cancelling is permanent — a cancelled job cannot be reopened.|[2,*] Cancel job :reference for :plate? :count part lines will be returned to stock. Cancelling is permanent — a cancelled job cannot be reopened.',
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
