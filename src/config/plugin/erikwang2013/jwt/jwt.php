<?php

return [
    'enable' => true,
    'secret_key' => 'your-very-secret-key',
    'algorithm' => 'HS256',
    'issuer' => 'my-app',
    'audience' => 'my-users',
    'leeway' => 60,
    'storage' => [
        'type' => 'redis',
        'config' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0
        ]
    ]
];
