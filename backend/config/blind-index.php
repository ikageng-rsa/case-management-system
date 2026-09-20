<?php

return [

    /*
     * Key used to derive the deterministic hashes that make encrypted columns
     * (ID numbers, contact values) searchable.
     */
    'key' => env('BLIND_INDEX_KEY') ?: env('APP_KEY'),

];
