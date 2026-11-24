<?php

return [
    // Prefix opaque API tokens so secret-scanning tools can identify leaked values.
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'exchange_'),
];
