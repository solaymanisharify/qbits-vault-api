<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Service Base URL
    |--------------------------------------------------------------------------
    | The base URL of your Notification Service API.
    | Example: https://notification.yourcompany.com
    |
    */
    'base_url' => env('NOTIFICATION_SERVICE_URL', 'https://pippasync-notification-service.test/api'),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    | Your service API key (sent as 'apiKey' header).
    |
    */
    'api_key' => env('NOTIFICATION_API_KEY', 'dOSpvHjEql7HFXgatlGX2rxXw4'),

    /*
    |--------------------------------------------------------------------------
    | Secret Key
    |--------------------------------------------------------------------------
    | Your service secret key (sent as 'secretKey' header).
    |
    */
    'secret_key' => env('NOTIFICATION_SECRET_KEY', '$2y$12$i7dU0yJPk5pVvjgkCII3o.vGEFhE92s7Uv.eg33xmWFkHwuJbnkzW'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => env('NOTIFICATION_TIMEOUT', 30),

];
