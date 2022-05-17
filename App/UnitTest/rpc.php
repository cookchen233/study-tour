<?php
include_once __DIR__. '/init.php';
use \Respect\Validation\Validator as v;

cache("consul_tcp_insurance", null);
$faker = \Faker\Factory::create('zh_CN');
$begin = mstime();
$loop = 1;
while ($loop--){
    /**************************************分类***************************************************/
    $client = new \OneRpcClient\Tcp\App\Rpc\StudyTour\ProjectRpc();
    $i = 1;
    while ($i--){
        try {
            $result  =  $client->getList();
            ok($result);
        } catch (Throwable $e) {
            fail($e);
        }
    }

    /****************************************评估申请*************************************************/
    //$client = new \OneRpcClient\Tcp\App\Rpc\MatchApplicationRpc();

}

$time = sprintf('%01.2f',round(mstime() - $begin, 2));
echo count(\OneRpcClient\RpcClient::$called_list) ." complete {$time}s --end-- \n";