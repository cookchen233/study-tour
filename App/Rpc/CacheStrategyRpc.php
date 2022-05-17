<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/4/23
 * Time: 10:40
 */

namespace App\Rpc;

use App\Utility\CacheStrategyTrait;

/**
 * 缓存更新策略
 * 用法:
    $data = cache('api-goods-list');
    //使用 isCacheKeyValid 判断'api-goods-list'缓存键是否有效(该缓存归属goods_list分组)
    if(!$data || !$client->isCacheKeyValid('goods_list', 'api-goods-list')){
        $data = getlist();
        cache('api-goods-list', $data);
        //使用用 updateCacheKey 更新缓存键为有效
        $client->updateCacheKey('goods_list', 'api-goods-list');
    }
    return $data;
 */
class CacheStrategyRpc
{

    use CacheStrategyTrait;

}

