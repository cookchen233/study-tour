<?php

namespace App\Model;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class CrmUserModel extends BasicModel
{

    CONST TABLE = 't_crm_user';

    public function __construct($relation = null)
    {
        parent::__construct($relation);
        $this->_cache_time = 0;
    }

    public function createOne($data)
    {
        $data['f_create_time'] = time();
        $data['f_update_time'] = time();
        $result = parent::createOne($data);
        //手机号保存在crm_user_contact
        if(!empty($data['mobile'])){
            $data['f_mobile'] = $data['mobile'];
            $data['f_default'] = 1;
        }
        $data['f_mobile'];
        (new CrmUserContact())->createOne($data);
        return $result;
    }
    public function updateOne($data, $condition)
    {
        $data['f_update_time'] = time();
        return parent::updateOne($data, $condition);
    }

    public function updateUser($data, $result){
        if(!empty($result['f_nickname'])){
            unset($data['name']);
        }
        if(!empty($result['f_sex'])){
            unset($data['gender']);
        }
        if(array_intersect(array_keys($data), array_keys($this->getFields()))){
            $this->updateOne($data, ['f_uid' => $result['f_id']]);
        }
    }

    protected function formatBeforeSave($model, & $data)
    {
        parent::formatBeforeSave($model, $data);
        if(!empty($data['weixin_openid'])){
            $data['f_wx_openid'] = $data['weixin_openid'];
        }
        if(!empty($data['gender'])){
            $data['f_sex'] = $data['gender'];
        }
        if(!empty($data['name'])){
            $data['f_name'] = $data['name'];
        }
    }
}