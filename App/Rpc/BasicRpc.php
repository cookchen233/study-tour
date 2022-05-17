<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/4/23
 * Time: 10:40
 */

namespace App\Rpc;

use App\Model\BasicModel;
use Respect\Validation\Validator as v;

class BasicRpc
{
    protected $msg = '';
    protected $code = '';
    protected $data = [];

    public $server;

    /**
     * @var BasicModel
     */
    protected $model;

    public function __construct()
    {
        v::with('App\\Utility\\hivalidation\\rule');
    }

    /**
     * 回滚
     */
    public function rollBack(){
        $this->model->rollBack();
    }

    /**
     * 提交
     */
    public function commit(){
        $this->model->commit();
    }

    protected function retCodeResult(){
        return [
            'code' => $this->code,
            'msg' => $this->msg,
            'data' => $this->data,
        ];
    }
    protected function fail($msg = '失败', $code = 'err'){
        $this->msg = $msg;
        $this->code = $code;
        return $this->retCodeResult();
    }

    protected function ok($data  = []){
        if($data){
            $this->data = $data;
        }
        $this->msg = '成功';
        $this->code = 'ok';
        return $this->retCodeResult();
    }

    /**
     * 通过uuid获取一条记录
     * @param $uuid
     * @return array|null
     */
    public function getByUuid(string $uuid){
        v::notEmpty()->assert($uuid);
        return $this->model::getByUuid($uuid);
    }


}

