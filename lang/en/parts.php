<?php

return [
    'title' => 'Parts',
    'new' => 'New part',
    'count' => '{0} No parts|{1} 1 part|[2,*] :count parts',
    'search_label' => 'Search parts',
    'search_placeholder' => 'Search SKU, name or part number',
    'view_part' => 'View :sku',

    'tabs' => [
        'label' => 'Catalogue filters',
        'all' => 'All',
        'low_stock' => 'Low stock',
    ],

    'trashed' => [
        'show' => 'Show deleted',
        'hide' => 'Hide deleted',
        'badge' => 'Deleted',
    ],

    'headers' => [
        'sku' => 'SKU',
        'name' => 'Name',
        'brand' => 'Brand',
        'stock' => 'Stock',
        'price' => 'Price',
        'cost' => 'Cost',
        'shelf' => 'Shelf',
        'actions' => 'Actions',
    ],

    'stock' => [
        'low' => 'Low',
        'negative' => 'Negative',
    ],

    'picker' => [
        'placeholder' => 'Select a part',
        'option' => ':sku · :name · :stock in stock',
        'shelf' => ':label · shelf :location',
        'low_stock' => ':label · LOW',
        'out_of_stock' => ':label · OUT OF STOCK',
    ],

    'reasons' => [
        'job_use' => 'Job use',
        'job_return' => 'Job return',
        'restock' => 'Restock',
        'manual_adjustment' => 'Manual adjustment',
    ],

    'numbers' => [
        'section' => 'Part numbers',
        'empty' => 'No part numbers recorded yet. Add the OE number to make this part findable by it.',
        'add' => 'Add part number',
        'submit' => 'Add number',
        'type_label' => 'Type',
        'number_label' => 'Number',
        'delete_action' => 'Delete :number',
        'confirm_remove' => 'Delete :number from this part? It stops matching searches for that number.',
        'note' => 'Spacing, dashes and case are ignored when searching, so 1K0 615 301 AA and 1k0-615-301-aa find the same part.',

        'types' => [
            'oe' => 'OE',
            'catalog' => 'Catalogue',
            'ean' => 'Barcode',
            'supplier' => 'Supplier',
        ],

        'validation' => [
            'not_a_number' => 'That is not a usable part number — it must contain at least one letter or digit.',
            'duplicate' => 'This part already carries that number under the same type.',
        ],

        'flash' => [
            'added' => ':number has been added.',
            'removed' => ':number has been removed.',
            'catalogue_conflict' => ':number is also a catalogue number on :sku (:name) under the same brand. Both were kept — check whether one is a duplicate.',
        ],
    ],

    'show' => [
        'details' => 'Part details',
        'brand' => 'Brand',
        'category' => 'Category',
        'stock' => 'Stock',
        'reorder_level' => 'Reorder at',
        'price' => 'Price',
        'cost' => 'Cost',
        'shelf' => 'Shelf',
        'description' => 'Notes',
        'movements' => 'Stock movements',
        'movements_empty' => 'No movements recorded yet.',
    ],

    'movements' => [
        'headers' => [
            'when' => 'When',
            'change' => 'Change',
            'reason' => 'Reason',
            'who' => 'Recorded by',
        ],
        'showing' => 'Showing :shown of :total movements',
        'from_supplier' => 'from :name',
    ],

    'adjust' => [
        'title' => 'Adjust stock · :sku',
        'action' => 'Adjust',
        'action_for' => 'Adjust stock for :sku',
        'current' => 'Current stock',
        'reason_label' => 'What kind of movement is this?',
        'quantity' => 'Quantity',
        'note' => 'Note',
        'supplier' => 'Supplier (optional)',
        'supplier_note' => 'Who this batch came from. A new name is remembered for next time; spacing and case are ignored when matching.',
        'submit' => 'Record',
        'help' => [
            'restock' => 'Stock arriving. The quantity must be positive.',
            'manual_adjustment' => 'A correction after counting the shelf. It may be negative, and the note is required to say why.',
        ],
    ],

    'create' => [
        'title' => 'New part',
        'submit' => 'Add part',
    ],

    'edit' => [
        'title' => 'Edit :sku',
        'action' => 'Edit :sku',
        'submit' => 'Save changes',
    ],

    'form' => [
        'sku' => 'SKU',
        'name' => 'Name',
        'brand_id' => 'Brand (optional)',
        'category' => 'Category (optional)',
        'numbers_note' => 'OE and catalogue numbers are added on the part page once it exists.',
        'description' => 'Notes (optional)',
        'unit_price' => 'Selling price',
        'cost_price' => 'Cost price (optional)',
        'reorder_level' => 'Reorder level',
        'location' => 'Shelf location (optional)',
        'stock_title' => 'Stock is not typed in',
        'stock_note' => 'A new part starts at zero. Stock only moves by restocking or by being fitted to a job, so every change leaves a record.',
    ],

    'delete' => [
        'action' => 'Delete :sku',
        'confirm' => 'Delete :sku? The SKU stays reserved and cannot be used for another part until this one is restored.',
    ],

    'restore' => [
        'action' => 'Restore :sku',
        'label' => 'Restore',
        'confirm' => 'Restore :sku? It returns to the catalogue with its stock count unchanged.',
    ],

    'empty' => [
        'none' => [
            'title' => 'No parts yet',
            'body' => 'The catalogue is empty. Add the first part to start tracking stock.',
        ],
        'no_matches' => [
            'title' => 'No parts match that search',
            'body' => 'Check the SKU, or try part of the name or an OE number.',
        ],
        'none_low' => [
            'title' => 'Nothing is low on stock',
            'body' => 'Every part is above its reorder level.',
            'clear' => 'Show all parts',
        ],
    ],

    'validation' => [
        'sku_deleted' => 'That SKU belongs to a deleted part, :name. An administrator can restore it from the catalogue.',
    ],

    'flash' => [
        'created' => ':sku has been added.',
        'updated' => ':sku has been updated.',
        'deleted' => ':sku has been deleted. Its SKU stays reserved.',
        'restored' => ':sku has been restored.',
        'stock_recorded' => ':sku: :change recorded. Stock is now :balance.',
        'stock_negative' => 'Stock for :sku is now :balance, below zero. The movement was saved anyway.',
    ],
];
