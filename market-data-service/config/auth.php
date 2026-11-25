<?php

declare(strict_types=1);

return [
    /*
     * Customer identities and credentials belong to the Identity service.
     * This service intentionally has no local Eloquent user provider,
     * authentication guard, or password broker.
     *
     * Add a request authentication adapter only after the inter-service
     * token/assertion contract has been selected and implemented.
     */
   'defaults' => [
    'guard' => env('AUTH_GUARD', 'pulse-guest'),
],

'guards' => [
    'pulse-guest' => [
        'driver' => 'pulse-guest',
    ],
],

'providers' => [],
'passwords' => [],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
