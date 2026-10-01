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
    | Shop Language
    |--------------------------------------------------------------------------
    |
    | The language of every email sent to a customer, and the default for any
    | staff member who has not chosen one. It is read from the same variable as
    | app.locale but kept apart from it, because app.locale changes with every
    | request to match the signed-in user.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

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
    | Default Appointment Hours
    |--------------------------------------------------------------------------
    |
    | The window new and rescheduled appointments must fit inside until an
    | admin sets the shop's own hours from the appointments page.
    |
    */

    'appointment_hours' => [
        'open' => '08:00',
        'close' => '20:00',
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Reminders
    |--------------------------------------------------------------------------
    |
    | How many days before a vehicle's next-service date or registration expiry
    | the daily reminders:send command emails its owner.
    |
    */

    'reminders' => [
        'lead_days' => (int) env('SHOP_REMINDER_LEAD_DAYS', 14),
    ],

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
