<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hourly Labor Rate
    |--------------------------------------------------------------------------
    |
    | The shop-wide hourly rate stamped onto a labor line when it is created.
    | Changing this value never alters lines that are already recorded.
    |
    */

    'labor_rate' => env('SHOP_LABOR_RATE', 3000),

    /*
    |--------------------------------------------------------------------------
    | VAT Rate
    |--------------------------------------------------------------------------
    |
    | The shop-wide VAT percentage stamped onto an invoice when it is issued.
    | Changing this value never alters an invoice that is already issued.
    |
    */

    'vat_rate' => env('SHOP_VAT_RATE', 20),

    /*
    |--------------------------------------------------------------------------
    | Shop Identity
    |--------------------------------------------------------------------------
    |
    | Printed at the head of every invoice and in the footer of every customer
    | email. These are shop-wide facts rather than per-record data, so they are
    | never typed onto a form.
    |
    */

    'identity' => [
        'name' => env('SHOP_IDENTITY_NAME', 'Auto Service d.o.o.'),
        'address' => env('SHOP_IDENTITY_ADDRESS', 'Kneza Miloša 12, Beograd'),
        'phone' => env('SHOP_IDENTITY_PHONE', '+381 11 000 000'),
        'tax_number' => env('SHOP_IDENTITY_TAX_NUMBER', 'PIB 123456789'),
    ],

];
