<?php

return [
    'frontend_url' => env('IDENTITY_FRONTEND_URL', env('APP_URL', 'http://localhost')),

    // Personal access tokens are stored hashed and expire after 30 days by default.
    'access_token_lifetime_minutes' => (int) env('IDENTITY_ACCESS_TOKEN_LIFETIME', 43200),

    // Server-to-server credential for the administrative user-list read API.
    // Keep this secret out of browser code and public repositories.
    'users_read_token' => env('IDENTITY_USERS_READ_TOKEN'),
];
