<?php

return [
    'frontend_url' => env('IDENTITY_FRONTEND_URL', env('APP_URL', 'http://localhost')),

    // Server-to-server credential for the administrative user-list read API.
    // Keep this secret out of browser code and public repositories.
    'users_read_token' => env('IDENTITY_USERS_READ_TOKEN'),
];
