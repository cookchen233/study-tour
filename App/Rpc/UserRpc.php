<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/4/23
 * Time: 10:40
 */

namespace App\Rpc;

use App\Model\BookingModel;
use App\Model\CrmUserKeyOpModel;
use App\Model\ExpertModel;
use App\Model\UserKeyOpModel;
use App\Model\UserModel;
use One\Swoole\RpcData;
use Respect\Validation\Validator as v;

/**
 * 用户
 */
class UserRpc extends BasicRpc
{

    /**
     * @var MatchGoodsModel
     */
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new UserModel();
    }
    
    /**
     * 通过手机号获取一条记录
     * @param $mobile
     * @return array|null
     */
    public function getByMobile(string $mobile){
        v::mobile()->assert($mobile);
        return $this->model::getByMobile($mobile);
    }

    /**
     * 通过uuid(f_id)获取一条记录
     * @param $uuid
     * @return array|null
     */
    public function getByUuid(string $uuid){
        return $this->model->find($uuid)->formatFields();
    }


}

