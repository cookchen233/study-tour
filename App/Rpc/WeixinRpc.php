<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/4/23
 * Time: 10:40
 */

namespace App\Rpc;

use App\Utility\WeixinAccess;
use Respect\Validation\Validator as v;

/**
 * 微信API(解决跨项目间的Accesstoken一致性问题)
 */
class WeixinRpc
{

    protected function getWeixinAccess($app_code){
        return new WeixinAccess($app_code);
    }


    /**
     * 获取Accesstoken
     * @param $app_code, jz_official
     * @return bool|mixed
     */
    public function getAccessToken($app_code){
        return $this->getWeixinAccess($app_code)->getAccessToken();
    }

    /**
     * js调用api前需获取的授权码
     * @param $app_code, jz_official
     * @return bool|mixed|string
     */
    public function getJsTicket($app_code){
        return $this->getWeixinAccess($app_code)->getJsTicket();
    }

}

