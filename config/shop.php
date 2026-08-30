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

];
