<?php

return [
    'show' => [
        'title' => 'Check-in :reference',
        'back' => 'Back to :reference',
        'print' => 'Print sheet',
        'none' => 'No check-in was recorded for this job.',
        'recorded_by' => 'Recorded by :name',
    ],

    'fields' => [
        'fuel_level' => 'Fuel level',
        'damage_notes' => 'Damage',
        'valuables' => 'Valuables left in the car',
        'mileage_in' => 'Mileage in',
        'received' => 'Received',
        'signed_by_name' => 'Customer name',
        'signature' => 'Signature',
        'signature_method' => 'Signing method',
        'photo' => 'Photo',
        'vehicle' => 'Vehicle',
        'customer' => 'Customer',
        'vin' => 'VIN',
    ],

    'fuel_levels' => [
        'empty' => 'Empty',
        'quarter' => '1/4',
        'half' => '1/2',
        'three_quarters' => '3/4',
        'full' => 'Full',
    ],

    'signature_methods' => [
        'drawn' => 'Signed on the tablet',
        'paper' => 'Signed on paper',
    ],

    'form' => [
        'heading' => 'Condition on arrival',
        'submit' => 'Save check-in',
    ],

    'photos' => [
        'heading' => 'Damage photos',
        'print_alt' => 'Damage photo :position of :count, job :reference',
        'empty' => 'No damage photos.',
        'label' => 'Add a damage photo',
        'upload' => 'Upload photo',
        'upload_for' => 'Upload photo to the check-in for :reference',
        'hint' => 'JPEG, PNG or WebP, up to 10 MB.',
    ],

    'sign' => [
        'heading' => 'Signature',
        'pad_label' => 'Signature pad',
        'pad_hint' => 'The customer signs in the box with a finger or stylus. If they cannot draw, print the sheet and confirm the paper signature below.',
        'clear' => 'Clear',
        'submit' => 'Sign',
        'empty' => 'The customer has not signed yet.',
        'paper_hint' => 'Print the sheet, have the customer sign it, then confirm here.',
        'paper_submit' => 'Confirm paper signature',
        'signed' => 'Signed by :name · :date',
        'image_alt' => 'Signature of :name',
        'read_only' => 'This sheet is signed and can no longer be changed.',
        'errors' => [
            'unreadable' => 'The signature could not be read. Clear the pad and sign again.',
            'empty' => 'Draw the signature in the box, then press Sign.',
        ],
    ],

    'print' => [
        'title' => 'Vehicle check-in',
        'printed' => 'Printed :date',
        'declaration' => 'I confirm that the condition of the vehicle and the valuables left in it are recorded correctly above.',
        'signature_line' => 'Customer signature',
        'name_line' => 'Name',
        'date_line' => 'Date',
        'signed_on_paper' => 'Signed on paper by :name on :date',
        'action' => 'Print',
        'back' => 'Back to check-in',
    ],

    'summary' => [
        'label' => 'Check-in',
        'not_recorded' => 'Not recorded',
        'unsigned' => 'Recorded, not signed',
        'signed' => 'Signed :date · :method',
        'photos' => ':count photo|:count photos',
        'record' => 'Record check-in',
        'open' => 'Open sheet',
        'view' => 'View sheet',
        'methods' => [
            'drawn' => 'drawn',
            'paper' => 'on paper',
        ],
    ],

    'flash' => [
        'saved' => 'Check-in saved.',
        'photo_added' => 'Photo added.',
        'photo_deleted' => 'Photo deleted.',
        'signed' => 'Check-in signed by :name.',
        'not_open' => 'This check-in can no longer be changed.',
    ],
];
