<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Table Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for all ZKTeco database tables.
    | Default: 'zkteco_' (creates zkteco_devices, zkteco_attendance_logs, etc.)
    |
    */
    'table_prefix' => env('ZKTECO_TABLE_PREFIX', 'zkteco_'),

    /*
    |--------------------------------------------------------------------------
    | Device Route Prefix
    |--------------------------------------------------------------------------
    |
    | ZKTeco hardware devices connect to /iclock/cdata and /iclock/getrequest.
    | Do not change this unless your ZKTeco firmware uses a custom path.
    |
    */
    'device_route_prefix' => 'iclock',

    /*
    |--------------------------------------------------------------------------
    | REST JSON API Route Prefix
    |--------------------------------------------------------------------------
    |
    | The route prefix for external applications to query devices & logs.
    | Example: http://your-app.test/api/zkteco/devices
    |
    */
    'api_route_prefix' => 'api/zkteco',

    /*
    |--------------------------------------------------------------------------
    | Middleware API Access Key
    |--------------------------------------------------------------------------
    |
    | The secret key required in the X-API-Key header when external servers
    | make calls to the /api/zkteco REST endpoints.
    |
    */
    'api_key' => env('ZKTECO_API_KEY', 'zk_api_key_default_12345'),

    /*
    |--------------------------------------------------------------------------
    | External Server Webhook Target
    |--------------------------------------------------------------------------
    |
    | Configure the external API URL where translated JSON webhooks are sent
    | when attendance punches or heartbeats are received from devices.
    |
    */
    'external_api_url' => env('ZKTECO_EXTERNAL_API_URL', 'https://api.example.com/v1'),

    'webhook' => [
        'enabled' => env('ZKTECO_WEBHOOK_ENABLED', true),
        'secret_token' => env('ZKTECO_WEBHOOK_SECRET', 'sk_live_zkteco_secret_9988'),
        'attendance_path' => env('ZKTECO_ATTENDANCE_WEBHOOK_PATH', '/webhooks/attendance'),
        'heartbeat_path' => env('ZKTECO_HEARTBEAT_WEBHOOK_PATH', '/webhooks/device-status'),
        'command_result_path' => env('ZKTECO_CMD_RESULT_WEBHOOK_PATH', '/webhooks/command-result'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Enable Admin Dashboard UI
    |--------------------------------------------------------------------------
    |
    | Enable the web configuration dashboard at /zkteco/admin
    |
    */
    'enable_admin_ui' => env('ZKTECO_ENABLE_ADMIN_UI', true),
    'admin_route_prefix' => 'zkteco/admin',

];
