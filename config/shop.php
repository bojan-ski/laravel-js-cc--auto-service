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

];
