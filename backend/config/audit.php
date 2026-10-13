<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Read window
    |--------------------------------------------------------------------------
    |
    | Someone reading the same record is written to the audit log once, then not
    | again until this many minutes have passed. Every other kind of access —
    | downloads, exports, uploads — is recorded each time it happens.
    |
    */

    'read_window_minutes' => (int) env('AUDIT_READ_WINDOW_MINUTES', 60),

];
