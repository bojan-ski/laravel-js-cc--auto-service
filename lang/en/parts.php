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

    'import' => [
        'title' => 'Import parts from a CSV',
        'action' => 'Import CSV',
        'intro' => 'A row whose SKU is new creates a part; a row whose SKU already exists adds its quantity to stock. An import never changes an existing part — its name, price, brand, category and shelf are left exactly as they are.',
        'file_label' => 'CSV file',
        'submit' => 'Import',
        'template' => 'Download template',
        'template_note' => 'The template carries the exact header row this page expects.',
        'limits' => 'Up to :rows rows per file. Save from Excel as CSV — comma, semicolon and tab separators are all accepted.',
        'movement_note' => 'CSV import',

        'columns_heading' => 'Columns',
        'column_header' => 'Column',
        'column_required' => 'Required',
        'column_meaning' => 'Meaning',
        'required_always' => 'Always',
        'required_new' => 'New parts only',
        'required_never' => 'Optional',

        'columns' => [
            'sku' => 'The shop\'s own part number. Decides whether the row creates or restocks.',
            'quantity' => 'How many arrived. A whole number, at least 1.',
            'name' => 'What the part is called.',
            'unit_price' => 'Selling price.',
            'cost_price' => 'What it cost. Administrators only — the column is refused for anyone else.',
            'brand' => 'An existing brand name, exactly as spelled in Brands. Unknown brands are refused.',
            'category' => 'A free-text grouping, such as Braking.',
            'location' => 'Shelf or bin.',
            'reorder_level' => 'The low-stock threshold.',
            'supplier' => 'Who this delivery came from. A new name is remembered for next time.',
            'oe_numbers' => 'OE numbers, separated by a pipe: 1K0615301AA|8K0698451D',
        ],

        'errors' => [
            'heading' => 'Nothing was imported',
            'intro' => 'The whole file is rejected when any row is wrong, so your catalogue is untouched. Fix these and upload again.',
            'no_rows' => 'The file has a header row but no data rows.',
            'too_many_rows' => 'The file has :count rows; the limit is :max. Split it into smaller files.',
            'missing_column' => 'The header row is missing the required column ":column".',
            'unknown_column' => 'The header row has a column this import does not recognise: ":column".',
            'duplicate_column' => 'The header row names the same column twice.',
            'cost_price_forbidden' => 'Only an administrator may import a cost_price column. Remove it and upload again.',
            'missing_sku' => 'Line :line has no SKU.',
            'duplicate_sku' => 'SKU :sku appears on line :first and again on line :line. Each part may appear once.',
            'deleted_part' => 'Line :line: :sku belongs to a deleted part. Restore it before importing.',
            'bad_quantity' => 'Line :line (:sku): quantity must be a whole number of at least 1.',
            'missing_for_new_part' => 'Line :line (:sku) would create a new part, so ":column" is required.',
            'bad_price' => 'Line :line (:sku): unit_price must be a number.',
            'bad_cost' => 'Line :line (:sku): cost_price must be a number.',
            'bad_reorder_level' => 'Line :line (:sku): reorder_level must be a whole number.',
            'unknown_brand' => 'Line :line: there is no brand called ":brand". Add it under Brands first.',
        ],

        'flash' => [
            'imported' => 'Import complete: :created parts created, :restocked restocked.',
            'details_ignored' => ':count rows carried details for parts that already exist. Those parts were restocked and otherwise left unchanged.',
            'unreadable' => 'That file could not be read as a CSV. Check it opens in a spreadsheet and has a header row.',
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
