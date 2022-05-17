<?php

namespace App\Model;

use One\Facades\Cache;

class UserModel extends BasicModel
{
    CONST TABLE = 't_user';

    public function __construct($relation = null)
    {
        parent::__construct($relation);
        $this->_cache_time = 0;
    }

    public function filter($filter){
        $this->from(self::TABLE . ' u')
            ->column('u.*');
        if(!empty($filter['keywords'])){
            if(!empty($filter['keywords'])){
                $this->whereRaw("u.f_nickname like '%{$filter['keywords']}%'");
            }
        }
        if(isset($filter['type'])){
            $this->where('u.f_type', $filter['type']);
        }
        return $this;
    }

    public static function signMobile($mobile){
        return md5(md5($mobile));
    }

    public static function signData($data){
        return md5(md5($data) . md5(strlen($data)));
    }

    public static function getByMobile(string $mobile)
    {
        $result = static::where('f_mobile_num_md5', static::signMobile($mobile))->orderBy('f_id desc')->find();
        return $result ? $result->formatFields() : $result;
    }

    public static function getByWeixinOpenid($openid){
        $result = static::where('f_weixin_openid_sign', static::signData($openid))->orderBy('f_id asc')->find();
        return $result ? $result->formatFields() : $result;
    }

    public function formatFields( $data = null)
    {
        $data = parent::formatFields($data);
        $data['mobile'] = isset($data['f_mobile_num_ciphertext']) ? static::decryptData($data['f_mobile_num_ciphertext'], $data['f_ctime'] . $data['f_mobile_num_md5']) : '';
        $data['email'] = isset($data['f_email_ciphertext']) ? static::decryptData($data['f_email_ciphertext'], $data['f_ctime'] . $data['f_email_md5']) : '';
        $data['weixin_openid'] = isset($data['f_weixin_openid']) ? static::decryptData($data['f_weixin_openid'], $data['f_ctime']) : '';
        return $data;
    }

    /**
     * 创建一条记录(如果手机号不存在)
     * 如果手机号已存在,则直接返回存在记录的id,
     * 否则就创建一条记录,并且同时创建一条crm用户记录
     * @param $data
     * @param $source_data 用户来源信息
     * @param $log_data 日志信息
     * @return int
     */
    public function createNxAndCrmUser($data, $source_data)
    {
        //如果手机号不为空
        if(!empty($data['mobile']) ){
            $nickid=$data['mobile'];
            $result = static::getByMobile($data['mobile']);
            //如果存在该手机号用户
            if ($result){
                //更新微信openid
                $this->updateUser($data, $result);
                (new CrmUserModel())->updateUser($data, $result);
                cache('user_info_'.$result['f_id'], null);
                return $result['f_id'];
            }
            //如果不存在该手机号用户,但有weixin_openid
            elseif(!empty($data['weixin_openid'])){
                $result = static::getByWeixinOpenid($data['weixin_openid']);
                //if ($result && !$result['f_mobile_num_md5']){//如果手机号为空则更新手机号,否则创建新账号
                //如果存在该微信用户,更新手机号
                if ($result){
                    $this->updateUser($data, $result);
                    (new CrmUserModel())->updateUser($data, $result);
                    cache('user_info_'.$result['f_id'], null);
                    //如果原手机号不为空(必定与新手机号不一致), 则增加crm用户联系方式
                    if($result['f_mobile_num_md5']){
                        $contact_model = new CrmUserContact();
                        $contact = $contact_model
                            ->where('f_uid', $result['f_id'])
                            ->where('f_mobile', $contact_model::encryptData($data['mobile']))
                            ->find();
                        if(!$contact){
                            $contact_model->createOne(['f_mobile' => $data['mobile'], 'f_uid' => $result['f_id'], 'f_default' => 1]);
                        }
                    }
                    //否则需要创建crm用户
                    else{
                        (new CrmUserModel())->createOne(array_merge($data, ['f_uid' => $result['f_id']]));
                    }
                    return $result['f_id'];
                }
            }
        }
        elseif(!empty($data['weixin_openid'])){ //如果没有手机号但有微信openid时
            $nickid=$data['weixin_openid'];
            $result = static::getByWeixinOpenid($data['weixin_openid']);
            if ($result){
                $this->updateUser($data, $result);
                (new CrmUserModel())->updateUser($data, $result);
                return $result['f_id'];
            }
        }
        $data['name'] = !empty($data['name']) ? $data['name'] : substr_replace($nickid, '****', 3, -4);
        $id = $this->createOne($data);
        //创建用户来源
        $source_data['f_uid'] = $id;
        (new UserSourceModel())->createOne($source_data);
        //必须有手机号时才创建crm用户
        if(!empty($data['mobile'])){
            //创建crm用户
            (new CrmUserModel())->createOne(array_merge($data, ['f_uid' => $id]));
        }
        cache('user_info_'.$result['f_id'], null);
        return $id;
    }

    public function updateUser($data, $result){
        //清空之前的微信身份用户
        if(!empty($data['weixin_openid'])){
            $this->where(['f_weixin_openid_sign' => static::signData($data['weixin_openid'])])->update(['f_weixin_openid_sign' => tuuid()]);
        }
        if(!empty($result['f_nickname'])){
            unset($data['name']);
        }
        if(!empty($result['f_sex'])){
            unset($data['gender']);
        }
        $this->updateOne(array_merge($data, ['f_ctime' => $result['f_ctime']]), ['f_id' => $result['f_id']]);
    }

    protected function formatBeforeSave($model, & $data)
    {
        if(!isset($data['f_ctime']) && !isset($data['f_id'])){
            $data['f_ctime'] = time();
        }
        if (!empty($data['f_mobile'])){
            $data['f_mobile'] = static::encryptData($data['f_mobile']);
        }
        if (!empty($data['mobile'])){
            $data['f_mobile_num_md5'] = static::signMobile($data['mobile']);
            $data['f_mobile_num_ciphertext'] = static::encryptData($data['mobile'], $data['f_ctime'] . $data['f_mobile_num_md5']);
        }
        if (!empty($data['email'])){
            $data['f_email_md5'] = static::signMobile($data['email']);
            $data['f_email_ciphertext'] = static::encryptData($data['email'], $data['f_ctime'] . $data['f_email_md5']);
        }
        if (!empty($data['password'])){
            $data['f_password'] = md5(md5($data['password']) . $data['f_ctime']);
        }
        if (!empty($data['weixin_openid'])){
            $data['f_weixin_openid_sign'] = static::signData($data['weixin_openid']);
            $data['f_weixin_openid'] = static::encryptData($data['weixin_openid'], $data['f_ctime']);
        }
        if(!empty($data['gender'])){
            $data['f_sex'] = $data['gender'];
        }
        if(!empty($data['name'])){
            $data['f_nickname'] = $data['name'];
        }
        if(!empty($data['avatar'])){
            $data['f_head_url'] = $data['avatar'];
        }
        else{
            $data['f_head_url'] = "https://cache.hinabian.com/images/head/hf_def.png";
        }
        parent::formatBeforeSave($model, $data);
    }

}