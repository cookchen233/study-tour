<?php
return [
    'drive' => 'file', // [file | redis] 调用Cache:: 相关方法使用的缓存驱动

    'file' => [
        'path' => _APP_PATH_ . '/RunCache/cache', //文件缓存位置
        'prefix' => 'one_' //文件前缀
    ],

    'redis' => [ // redis配置
        'default' => [  // 默认配置方法
            'max_connect_count' => 5 , // 连接池最大数量
            'host' => env('redis_host', '', '127.0.0.1'),
            'port' => env('redis_port', '', 6379),//
            'prefix' => 'one_'. env('service_name') . ':',
            'auth' => env('redis_pass', 'string')
        ],
        'default_cluster' => [  // redis cluster 配置
            'max_connect_count' => 10, // 连接池最大数量
            'args' => [ //初始化参数
                null,
                ['192.168.1.10:7000','192.168.1.10:7001'],
                1.5,
                1.5,
                false,
                'password'
            ],
            'is_cluster' => false,
            'prefix' => 'one_'. env('service_name') . '_',
            'auth' => env('redis_pass', 'string')
        ]
    ]
];

