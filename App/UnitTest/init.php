<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
set_error_handler(function ($code,$message,$errfile,$errline){
    throw new ErrorException($message, $code, E_ERROR, $errfile, $errline);
});

require __DIR__ . '/../../vendor/autoload.php';

function mstime(){
    $mstime = explode(' ', microtime());
    return $mstime[0] + $mstime[1];
}

function cache($key, $value = false, $exp = null){
    $redis = new Redis();
    $redis->connect('127.0.0.1');
    //$redis->set($key, null);
    if(false  ===  $value){
        return $redis->get($key);
    }
    return $redis->set($key, $value,  $exp);
}

function ok(...$args){
    echo implode(' ', [
        (\OneRpcClient\RpcClient::$last_called['time'] > 0.5 ? '🐷' : '🍏') . ' success'
        , \OneRpcClient\RpcClient::$last_called['time']
        , \OneRpcClient\RpcClient::$last_called['class']
        , debug_backtrace()[0]['line']
        , \OneRpcClient\RpcClient::$last_called['name']
        , implode(' ', array_map(function($v){
            if(is_scalar($v)){
                return $v;
            }
            return substr(json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 0, 124);
        }, $args)), "\n"
    ]);
}
function fail(Throwable $e){
    try{
        $args = [];
        foreach (\OneRpcClient\RpcClient::$last_called['args'] as $v){
            $args[] = var_export($v, true);
        }
        echo implode('', [
            '😂😂😂fail'
            , \OneRpcClient\RpcClient::$last_called['time']
            , \OneRpcClient\RpcClient::$last_called['class']
            , debug_backtrace()[0]['line']
            , \OneRpcClient\RpcClient::$last_called['name']
            , "\n"
            , \OneRpcClient\RpcClient::$last_called['name'] . '('.implode(', ', $args) .')'
            , "\n"
            , $e->getMessage() . '('. $e->getCode() .')'
            , "\n"
            , $e->getTraceAsString()
            , "\n"
        ]);
    }catch (Throwable $exception){
        echo sprintf('%s in %s %s', $e->getMessage(), $e->getFile(), $e->getLine());
    }
    exit;
}