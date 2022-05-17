<?php
include_once __DIR__. '/init.php';
use \Respect\Validation\Validator as v;

//cache("consul_tcp_insurance", null);
$client = new \OneRpcClient\Tcp\App\Rpc\ProductRpc();

try{
    $condition = [
        'gender' => 'male',
        'people' => 'adult',
        'age' => 37,
        'income' => '300',
    ];
    $product_uuid_list = ['2LJB4SZ7F78J3068', '2LJBQWZ36FFCRGSV', '2LJDEYXK474TCPOM', '2LJT3UD9Q4NWSEFR'];
    $result = $client->getListByUuidList($product_uuid_list);var_dump($result);
    v::arrayVal()->length(1)->each(v::arrayVal()->key('uuid'))->check($result);
    ok($result);

    $client = new \OneRpcClient\Tcp\App\Rpc\MatchGoodsRpc();
    $result = $client->create([
        //'people' => 'adult', //(old:老人, child:少儿, adult:成人)
        //'income' => 300, //(100:低, 300:中, 500:高)
        'gender' => ['male'],
        'age_min' => 12,
        'age_max' => 20,
        'product_uuid' => '2LGQ4NHPZLWGELXQ',
        'insure_amount' => 500,
        'premium' => 30,
        'desc' => '--评估推荐语--'. uniqid(),
        'sort' => mt_rand(0, 200),
    ]);
    v::arrayVal()->key('uuid', v::notEmpty())->assert($result);
    ok($result);
}catch (Throwable $e){
    fail($e);
}