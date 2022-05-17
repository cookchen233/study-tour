<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/12/6
 * Time: 15:50
 */

use \One\Swoole\RpcServer;

RpcServer::group([
    'middle' => [ //后面的先进后出, 不可嵌套
        \App\Middleware\RpcMiddleware::class . '@checkSign',
        \App\Middleware\RpcMiddleware::class . '@logCall',
    ],
    //'cache'  => 10 //当以相同参数调用时会直接返回缓存结果, 单位:秒
], function () {
    RpcServer::add(\App\Rpc\UserRpc::class);
    RpcServer::add(\App\Rpc\CacheStrategyRpc::class);
    RpcServer::add(\App\Rpc\WeixinRpc::class);
    RpcServer::add(\App\Rpc\ProjectRpc::class);
});