<?php
/**
 * swoole 运行这个文件
 * php swoole.php
 */
ini_set('display_errors',1);
error_reporting(E_ALL);
set_error_handler(function ($code,$message,$errfile,$errline){
    throw new \ErrorException($message, $code, E_ERROR, $errfile, $errline);
});
define('_APP_PATH_', __DIR__);
define('_APP_PATH_VIEW_', __DIR__ . '/View');
//define('_DEBUG_',true);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/Utility/helper_function.php';
require __DIR__ . '/../vendor/hinabian/swoole_one/src/run.php';
require __DIR__ . '/config.php';
\Swoole\Runtime::enableCoroutine();
\One\Swoole\OneServer::runAll();