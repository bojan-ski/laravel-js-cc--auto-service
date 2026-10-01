<?php

return [
    'title' => 'Reports',

    'period' => [
        'legend' => 'Period',
        'from' => 'From',
        'to' => 'To',
        'apply' => 'Apply',
        'summary' => 'Invoices issued :from – :to',
    ],

    'export' => [
        'label' => 'Export CSV',
        'aria' => 'Export CSV of invoices issued :from – :to',
    ],

    'empty' => [
        'title' => 'Nothing to report for this period',
        'body' => 'No invoice issued in this period counts toward the report. Choose another period.',
    ],

    'voided' => ':count voided invoice is not counted.|:count voided invoices are not counted.',

    'revenue' => [
        'title' => 'Revenue',
        'invoices' => 'Invoices',
        'before_discount' => 'Before discount',
        'discount' => 'Discounts',
        'subtotal' => 'Subtotal',
        'vat' => 'VAT',
        'total' => 'Total',
    ],

    'vat' => [
        'title' => 'VAT by rate',
        'rate' => 'Rate',
        'rate_value' => ':rate%',
        'base' => 'Base',
        'vat' => 'VAT',
    ],

    'payments' => [
        'title' => 'Payments',
        'method' => 'Method',
        'invoices' => 'Invoices',
        'amount' => 'Amount',
        'unrecorded' => 'Not recorded',
        'unpaid' => 'Unpaid',
    ],

    'labor' => [
        'title' => 'Labor by mechanic',
        'mechanic' => 'Mechanic',
        'hours' => 'Hours',
        'amount' => 'Amount',
        'empty' => 'No labor was invoiced in this period.',
    ],

    'parts' => [
        'title' => 'Parts sold',
        'sku' => 'SKU',
        'name' => 'Part',
        'quantity' => 'Qty',
        'revenue' => 'Revenue',
        'cost' => 'Cost',
        'margin' => 'Margin',
        'total' => 'Total',
        'empty' => 'No parts were invoiced in this period.',
        'without_cost' => ':count part has no cost price and is left out of the margin.|:count parts have no cost price and are left out of the margin.',
    ],

    'csv' => [
        'number' => 'Number',
        'issued_on' => 'Issued on',
        'status' => 'Status',
        'job' => 'Job',
        'buyer_name' => 'Buyer',
        'buyer_address' => 'Buyer address',
        'buyer_tax_id' => 'Buyer PIB',
        'buyer_registration_number' => 'Buyer MB',
        'before_discount' => 'Before discount',
        'discount' => 'Discount',
        'subtotal' => 'Subtotal',
        'vat_rate' => 'VAT rate',
        'vat' => 'VAT',
        'total' => 'Total',
        'payment_method' => 'Payment method',
        'paid_on' => 'Paid on',
        'voided_on' => 'Voided on',
    ],
];
