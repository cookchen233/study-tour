<?php

return [
    'debug_log' => true, // 是否打印sql日志
    'default'      => [
        'max_connect_count' => 10,
        'dns'               => 'mysql:host='.env('mysql_host').';dbname='.env('mysql_db'),
        'username'          => env('mysql_user'),
        'password'          => env('mysql_pass'),
        'ops'               => [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false
        ]
    ],
    'd_hnb'      => [
        'max_connect_count' => 10,
        'dns'               => 'mysql:host='.env('mysql_hnb_host').';dbname='.env('mysql_hnb_db'),
        'username'          => env('mysql_hnb_user'),
        'password'          => env('mysql_hnb_pass'),
        'ops'               => [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false
        ]
    ],
];
