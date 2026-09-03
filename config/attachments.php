<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Attachment Disk
    |--------------------------------------------------------------------------
    |
    | Job photos and documents are staff-only, so they live on a private disk
    | and are served exclusively through an authorized controller route. This
    | disk must never be a public one, and it must never have 'serve' enabled:
    | a signed URL is unguessable but not authorized.
    |
    */

    'disk' => env('ATTACHMENT_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Attachment Directory
    |--------------------------------------------------------------------------
    |
    | The root folder on that disk. Stored paths below it are generated, never
    | derived from the filename the user uploaded.
    |
    */

    'directory' => 'job-attachments',

];
