<?php

return [

    /*
     * Key used to derive the deterministic hashes that make encrypted columns
     * (ID numbers, contact values) searchable. Rotating this key invalidates
     * every stored hash, so they must be rebuilt from the decrypted values.
     */
    'key' => env('BLIND_INDEX_KEY', env('APP_KEY')),

];
