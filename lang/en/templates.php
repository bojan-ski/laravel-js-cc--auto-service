<?php

return [
    'index' => [
        'title' => 'Job templates',
        'new' => 'New template',
        'headers' => [
            'actions' => 'Actions',
        ],
    ],

    'headers' => [
        'name' => 'Name',
        'description' => 'Description',
        'lines' => 'Lines',
    ],

    'lines_count' => ':count line|:count lines',
    'no_lines' => 'No lines yet',

    'empty' => [
        'none' => [
            'title' => 'No job templates yet',
            'body' => 'A template adds a set of labor and part lines to a job in one step.',
        ],
    ],

    'trashed' => [
        'badge' => 'Deleted',
    ],

    'restore' => [
        'label' => 'Restore',
        'action' => 'Restore :name',
        'confirm' => 'Restore the template :name?',
    ],

    'create' => [
        'title' => 'New job template',
        'submit' => 'Create template',
    ],

    'edit' => [
        'title' => 'Edit :name',
        'submit' => 'Save template',
    ],

    'fields' => [
        'name' => 'Name',
        'description' => 'Description',
    ],

    'errors' => [
        'name_trashed' => 'A deleted template is already named :name. An admin can restore it.',
    ],

    'delete' => [
        'label' => 'Delete template',
        'action' => 'Delete template :name',
        'confirm' => 'Delete the template :name? An admin can restore it.',
    ],

    'lines' => [
        'heading' => 'Lines',
        'empty' => 'This template has no lines yet. Add one below.',
        'headers' => [
            'type' => 'Type',
            'description' => 'Description',
            'quantity' => 'Qty',
            'actions' => 'Actions',
        ],
        'part_label' => ':name · :sku',
        'part_deleted' => '(deleted)',
        'deleted_part_notice' => 'A part in this template has been deleted. Replace or remove that line before applying the template.',
        'add' => [
            'labor_title' => 'Add a labor line',
            'part_title' => 'Add a part line',
            'group_label' => 'Line type',
            'labor_link' => '+ Labor',
            'part_link' => '+ Part',
            'description' => 'Description',
            'part' => 'Part',
            'hours' => 'Hours',
            'units' => 'Quantity',
            'no_parts' => 'There are no parts in the catalogue yet.',
            'submit' => 'Add line',
        ],
        'edit' => [
            'open' => 'Edit',
            'open_line' => 'Edit :description',
            'title_for' => 'Edit: :description',
            'save' => 'Save line',
            'cancel' => 'Cancel',
            'row_marker' => 'This line is being edited below.',
        ],
        'remove' => [
            'label' => 'Remove',
            'action' => 'Remove :description',
            'confirm' => 'Remove :description from this template?',
        ],
    ],

    'flash' => [
        'created' => ':name created. Add its lines below.',
        'updated' => ':name saved.',
        'deleted' => ':name deleted.',
        'restored' => ':name restored.',
        'line_added' => 'Line added.',
        'line_updated' => 'Line saved.',
        'line_removed' => 'Line removed.',
    ],

    'apply' => [
        'title' => 'Apply a template',
        'link' => '+ Template',
        'link_label' => 'Apply a template',
        'template' => 'Template',
        'template_placeholder' => 'Choose template',
        'option' => ':name — :lines',
        'mechanic' => 'Mechanic',
        'mechanic_placeholder' => 'Choose mechanic',
        'mechanic_hint' => 'Required when the template has labor lines. Every line is assigned to this mechanic.',
        'self_hint' => 'Every line is assigned to you.',
        'submit' => 'Apply template',
        'none' => 'There are no job templates yet.',
        'manage' => 'Manage templates',
        'flash' => [
            'applied' => ':name applied: :count line added.|:name applied: :count lines added.',
            'stock_negative' => 'Stock is now below zero for :parts.',
            'stock_negative_part' => ':name (:balance)',
        ],
        'errors' => [
            'deleted_part' => 'This template uses :part, which has been deleted. Edit the template before applying it.',
            'mechanic_required' => "Choose the mechanic who will do this template's labor.",
            'no_lines' => 'This template has no lines.',
        ],
    ],
];
