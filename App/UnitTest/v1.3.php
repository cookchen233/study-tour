<?php
include_once __DIR__. '/init.php';
use \Respect\Validation\Validator as v;

//$filter = [
//    'people' => 'old',
//    'keywords' => '安',
//    'income' => 300,
//];
//$limit = 3;

//$begin = mstime();
//$client = new \OneRpcClient\Http\App\Rpc\MatchGoodsRpc();
//$i=100;
//while($i--){
//    $list = $client->getList($filter, 1,  $limit);
//}
//$dt = mstime() - $begin;
//var_dump('http',$dt);

//$begin = mstime();
//$client = new \OneRpcClient\Tcp\App\Rpc\MatchGoodsRpc();
//$i=10000;
//while($i--){
//    var_dump($i);
//    $list = $client->getList($filter, 1,  $limit);
//}
//$dt = mstime() - $begin;
//var_dump('tcp',$dt);
//exit;

//cache("consul_tcp_insurance", null);
$begin = mstime();
$loop = 1;
while ($loop--){
    /**************************************评估商品***************************************************/
    $client = new \OneRpcClient\Tcp\App\Rpc\MatchGoodsRpc();

    try{
        $result = $client->getEnumList();
        v::arrayVal()->key('people', v::arrayVal()->key('adult'))->assert($result);
        ok($result);
    }catch (Throwable $e){
        fail($e);
    }

    $people = array_keys($result['people']);
    $genders = [['female'], ['male'], ['male', 'female'], ['female', 'male']];
    $gender = ['female', 'male'];
    $income = array_keys($result['income']);
    $product_uuid_list = ['2LJPGQGLDIMDIMNM', '2LGQ4NHPZLWGELXQ', '2LJBNLTNL57YQFO3', '2LJDEYXK474TCPOM'];
    $product_uuid_list = ['2LJB4SZ7F78J3068', '2LJBQWZ36FFCRGSV', '2LJDEYXK474TCPOM', '2LJT3UD9Q4NWSEFR'];
    $i = 10;
    while ($i--){
        $data = [
            'people' => $people[array_rand($people)],
            'income' => $income[array_rand($income)],
            'gender' =>  $genders[array_rand($genders)],
            'age_min' => mt_rand(2, 29),
            'age_max' => mt_rand(30, 90),
            'product_uuid' => $product_uuid_list[array_rand($product_uuid_list)],
            'insure_amount' => mt_rand(100, 900),
            'premium' => mt_rand(20, 90),
            'desc' => '--推荐评估语--' . uniqid(),
            'sort' => mt_rand(0, 200),
        ];
        //(new swoole_process(function(swoole_process $worker) use ($data){
            try {
                $client = new \OneRpcClient\Tcp\App\Rpc\MatchGoodsRpc();
                $result = $client->create($data);
                v::arrayVal()->key('uuid', v::notEmpty())->assert($result);
                ok($result);
            } catch (Throwable $e) {
                fail($e);
            }
        //}))->start();
    }

    try{
        $result = $client->create([
            'people' => 'adult', //(old:老人, child:少儿, adult:成人)
            'income' => 300, //(100:低, 300:中, 500:高)
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

    try{
        $uuid = $result['uuid'];
        $data = [
            'people' => 'old',
            'income' => 500,
            'gender' => ['male','female'],
            'age_min' => 12,
            'age_max' => 20,
            'product_uuid' => '2LGQ4NHPZLWGELXQ',
            'insure_amount' => 500,
            'premium' => 30,
            'desc' => '--评估推荐语...--',
            'uuid' => $uuid,
            'sort' => mt_rand(0, 200),
        ];
        $result = $client->update($data);
        v::intVal()->assert($result);
        ok($result);
    }catch (Throwable $e){
        fail($e);
    }

    try{
        $result = $client->getByUuid($uuid);
        v::arrayVal()->key('uuid', v::notEmpty())->assert($result);
        ok($result);
    }catch (Throwable $e){
        fail($e);
    }

    try{
        $result = $client->delete($uuid);
        v::intVal()->assert($result);
        ok($result);
    }catch (Throwable $e){
        fail($e);
    }

    try{
        $filter = [
            'people' => $people[array_rand($people)],
            'keywords' => '安',
            'income' => $income[array_rand($income)],
        ];
        $total = $client->getTotal($filter);
        v::intVal()->assert($total);
        $list = $client->getList($filter, 2, 10);
        v::arrayVal()->length(1)->each(v::arrayVal()->key('product', v::arrayVal()->key('is_show', v::intVal())))->assert($list);
        ok($result);
    }catch (Throwable $e){
        fail($e);
    }

    try{
        $condition = [
            'gender' => 'male',
            'people' => 'adult',
            'age' => 37,
            'income' => '300',
        ];
        $result = $client->recommendList($condition);
        v::arrayVal()->length(1)->each(v::arrayVal()->key('product', v::arrayVal()->key('is_show', v::intVal())))->assert($list);
        ok($result);
    }catch (Throwable $e){
        fail($e);
    }

    /****************************************评估申请*************************************************/
    $client = new \OneRpcClient\Tcp\App\Rpc\MatchApplicationRpc();

    try{
        $result = $client->getEnumList();
        v::arrayVal()->key('relation', v::arrayVal()->key('self'))->assert($result);
        ok($result);
    }catch (Throwable $e){
        fail($e);
    }

    try{
        $mobile = '13211'.mt_rand(100000, 999999);
        $data = [
            'insured_list' => [ //所有被保人信息
                'spouse' => ['age' =>35, 'gender' => 'male', 'relation' => 'spouse'],
                //'self' => ['age' =>37, 'gender' => 'female', 'relation' => 'self'],
                'children' => ['age' =>16, 'relation' => 'children'],
                'parents' => ['age' =>73, 'relation' => 'parents'],
            ],
            'income' => 300, //收入
            'ever_bought' => ['人寿', '健康'], //之前购买过的产品,
            'mobile' => $mobile, //手机号
            'gender' => 'female', //本人性别
        ];
        $source_data = [
            //
        ];
        $log_data = [
            //
        ];
        $result = $client->create($data, $source_data, $log_data);
        v::arrayVal()->key('uuid', v::notEmpty())->assert($result);
        ok($result);
    }catch (Throwable $e){
        fail($e);
    }

    try{
        $result = $client->getByMobile($mobile);
        v::arrayVal()->key('uuid', v::notEmpty())->assert($result);
        ok($result);
    }catch (Throwable $e){
        fail($e);
    }

    try{
        $result = $client->getByUuid($result['uuid']);
        v::arrayVal()->key('uuid', v::notEmpty())->assert($result);
        ok($result);
    }catch (Throwable $e){
        fail($e);
    }

    try{
        $result = $client->getListByUserId($result['user_id']);
        v::arrayVal()->length(1)->each(v::arrayVal()->key('uuid', v::notEmpty()))->assert($list);
        ok($result);
    }catch (Throwable $e){
        fail($e);
    }
}

$time = sprintf('%01.2f',round(mstime() - $begin, 2));
echo count(\OneRpcClient\RpcClient::$called_list) ." complete {$time}s --end-- \n";