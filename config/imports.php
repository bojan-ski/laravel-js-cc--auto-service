<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Rows Per Import
    |--------------------------------------------------------------------------
    |
    | Every row costs a locked read, a part write and a stock movement, all
    | inside one transaction. A file above this count is refused with a message
    | asking for it to be split, rather than holding row locks for minutes.
    |
    */

    'max_rows' => env('IMPORT_MAX_ROWS', 500),

    /*
    |--------------------------------------------------------------------------
    | Maximum Upload Size (kilobytes)
    |--------------------------------------------------------------------------
    |
    | Deliberately far below PHP's post_max_size. A POST above that limit
    | arrives with an empty body and loses its CSRF token, so it renders as a
    | 419 "page expired" rather than a size error — a failure the user cannot
    | act on. Keeping this small means the validator always gets to answer.
    |
    */

    'max_kilobytes' => env('IMPORT_MAX_KILOBYTES', 2048),

];
