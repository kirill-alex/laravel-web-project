<?php

return [
    'default' => env('BROADCAST_CONNECTION', env('BROADCAST_DRIVER', 'log')),

    'connections' => [
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER', 'eu'),
                'useTLS' => true,
            ],
            'client_options' => [
                'connect_timeout' => 5,
                'timeout' => 10,
                'verify' => env('PUSHER_CA_BUNDLE', true),
            ],
        ],
        'log' => ['driver' => 'log'],
        'null' => ['driver' => 'null'],
    ],
];
