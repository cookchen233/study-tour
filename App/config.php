<?php
$dotenv = \Dotenv\Dotenv::create(__DIR__ ."/../");
$dotenv->overload();
$dotenv->required(['environment', 'mysql_host', 'mysql_user', 'mysql_pass', 'redis_host', 'redis_port',]);
$dotenv = \Dotenv\Dotenv::create(__DIR__ ."/../", ".env.". env("environment"));
$dotenv->overload();
\One\Database\Mysql\Connect::setConfig(config('mysql', true));

\One\Exceptions\Handler::setConfig(config('exception', true));

\One\Log::setConfig(config('log', true));

\One\Http\Router::setConfig(['path' => _APP_PATH_ . '/Config/router.php']);

\One\Cache\File::setConfig(config('cache.file', true));
\One\Cache\Redis::setConfig(config('cache.redis', true));

\One\Crypt\Openssl::setConfig(config('crypt', true));

// 分布式配置
\App\Cloud\Server::setConfig(config('cloud', true));
\One\Swoole\OneServer::setConfig(config(isset($argv[1]) ? $argv[1] : 'protocol', true));
\One\Swoole\Client\Tcp::setConfig(config('client', true));

// 加载rpc配置
require _APP_PATH_ . '/Config/rpc.php';

// 解析路由
\One\Http\Router::loadRouter();