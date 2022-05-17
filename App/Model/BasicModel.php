<?php

namespace App\Model;

use One\Database\Mysql\Model;
use One\Swoole\OneServer;

class BasicModel extends Model
{

    public function __construct()
    {
        if(env('environment') == 'pro'){
            $this->_cache_time = mt_rand(3600, 3600 * 8);
        }
    }

    /**
     * 数据库事件
     * @return array
     */
    public function events()
    {
        return [
            'beforeGet'    => function ($model, & $where) {
                $this->beforeGet($model, $where);
            },
            'afterGet' => function (& $result) {
                $this->afterGet($result);
            },
            'beforeUpdate'  => function ($model, & $data) {
                $this->beforeUpdate($model, $data);
            },
            'afterUpdate'   => function (& $result, & $data) {
                $this->afterUpdate($result, $data);
            },
            'beforeDelete'  => function ($model) {
                $this->beforeDelete($model);
            },
            'afterDelete'   => function (& $result) {
                $this->afterDelete($result);
            },
            'beforeInsert'  => function ($model, &$data) {
                $this->beforeInsert($model, $data);
            },
            'afterInsert'   => function (& $result, & $data) {
                $this->afterInsert($result, $data);
            },

        ];
    }

    /**
     * 读取前事件
     * @param $model
     * @param $where
     */
    protected function beforeGet($model, & $where){
        foreach ($where as &$v){
            if(in_array(str_replace('`', '', $v[0]), ['mobile'])){
                $v[2] = $this::encryptData($v[2]);
            }
        }
    }

    /**
     * 读取后事件
     * @param $result
     * @return bool
     */
    protected function afterGet( & $result){
        if (!$result){
            return false;
        }
        if (is_array($result)){
            foreach ($result as &$v){
                $this->formatAfterGet($v);
            }
        }
        else{
            $this->formatAfterGet($result);
        }
    }

    /**
     * 更新前事件
     * @param $model
     * @param $data
     */
    protected function beforeUpdate($model, & $data){
        $this->formatBeforeSave($model, $data);
    }

    /**
     * 更新后事件
     * @param $result
     * @param $data
     */
    protected function afterUpdate(& $result, & $data){}

    /**
     * 删除前事件
     * @param $model
     */
    protected function beforeDelete($model){}

    /**
     * @param $result删除后事件
     */
    protected function afterDelete(& $result){}

    /**
     * 插入前事件
     * @param Model $model
     * @param $data
     */
    protected function beforeInsert($model, & $data){
        $this->formatBeforeSave($model, $data);
        $model->flushTableInfo();
        $fields = $model->getFields();
        if(isset($fields['ctime']) && empty($data['ctime'])){
            $data['ctime'] = time();
        }
        if(isset($fields['uuid']) && empty($data['uuid'])){
            $data['uuid'] = tuuid();
        }
    }

    /**
     * 插入后事件
     * @param $result
     * @param $data
     */
    protected function afterInsert(& $result, & $data){}

    /**
     * 插入以及更新前事件
     * @param $model
     * @param $data
     */
    protected function formatBeforeSave($model, & $data){
        if (!empty($data['mobile'])){
            $data['mobile_hash'] = $this::hashData($data['mobile']);
            $data['mobile'] = $this::encryptData($data['mobile']);
        }
        if (!empty($data['weixin'])){
            $data['weixin_hash'] = $this::hashData($data['weixin']);
            $data['weixin'] = $this::encryptData($data['weixin']);
        }
        if (!empty($data['email'])){
            $data['email_hash'] = $this::hashData($data['email']);
            $data['email'] = $this::encryptData($data['email']);
        }
    }

    /**
     * 读取后格式化数据(不影响性能的轻度格式化)
     * @param $result
     */
    protected function formatAfterGet(& $result){
        if (!empty($result['ctime'])){
            $result['ctime_fmt'] = date('Y-m-d H:i:s', $result['ctime']);
        }
        if (!empty($result['mobile'])){
            $result['mobile'] = $this::decryptData($result['mobile']);
            $result['mobile_mask'] = substr_replace($result['mobile'], '****', 3, -4);
        }
        if (!empty($result['weixin'])){
            $result['weixin'] = $this::decryptData($result['weixin']);
            $result['weixin_mask'] = substr_replace($result['weixin'], '****', 3, -2);
        }
        if (!empty($result['email'])){
            $result['email'] = $this::decryptData($result['email']);
            $result['email_mask'] = substr_replace($result['email'], '****', 3, -2);
        }
        foreach ($result as $k => $v){
            if($v === null && isset($this->getStruct()['desc'][$k]) && $this->getStruct()['desc'][$k]['Type'] == 'text'){
                $result[$k] = '';
            }
        }
    }

    /**
     * 设置字段别名前缀, 自动处理为prefix.field as prefix_field
     * @param $alias
     * @param $columns
     * @return string
     */
    public static function aliasColumn($alias, $columns){
        $columns = explode(',', $columns);
        foreach ($columns as $k => $v){
            $columns[$k] = "$alias.$v as {$alias}_$v";
        }
        return implode(',', $columns);
    }

    /**
     * 去除不必要的数据字段
     * @param Model|array $data
     * @param array $keys
     * @return array
     */
    public static function unsetArrayKeys($data, array $keys = ['id', 'sys_id', 'sys_ctime','sys_utime', 'ip']){

        foreach ($keys as $v){
            if(isset($data[$v])){
                unset($data[$v]);
            }
        }
        return $data;
    }

    /**
     * hash数据
     * @param $data
     * @return string
     */
    public static function hashData($data){
        $data = str_pad($data, 500, '^%+@./(?<]{:"[#^&*-=,!}|>;$');
        return hash('sha256', hash('sha256', $data));
    }

    /**
     * 加密数据
     * @param $data
     * @param string $iv
     * @return string
     */
    public static function encryptData($data, $iv = ''){
        if(!$iv){
            $iv = md5(md5(env('hnb_aes_iv')));
        }
        $iv = substr($iv, 8, 16);
        $iv = str_pad($iv, 16, '-');
        return base64_encode(openssl_encrypt($data, 'AES-128-CBC', env('hnb_aes_key'), false, $iv));
    }

    /**
     * 解密数据
     * @param $data
     * @param string $iv
     * @return false|string
     */
    public static function decryptData($data, $iv = ''){
        if(!$iv){
            $iv = md5(md5(env('hnb_aes_iv')));
        }
        $iv = substr($iv, 8, 16);
        $iv = str_pad($iv, 16, '-');
        return openssl_decrypt(base64_decode($data), 'AES-128-CBC', env('hnb_aes_key'), false, $iv);
    }

    /**
     * 执行where过滤语句
     * @param array $filter
     * @return $this|mixed
     */
    protected function filter(array $filter){
        return $this;
    }

    /**
     * @param $filter
     * @param int $page
     * @param int $limit
     * @param string $sort
     * @return BasicModel[]|\One\Database\Mysql\ListModel|Model[]
     */
    public function getFilterList($filter, $page = 1, $limit = 30, $sort = ''){
        $skip = max(0, (int)$page - 1) * $limit;
        $list = $this->filter($filter)->limit($limit, $skip)->orderBy($sort ? $sort : $this->getAlias() . 'sys_id desc')->findAll();
        return $list ?: [];
    }

    /**
     * 获得过滤结果总数
     * @param array $filter
     * @return int
     */
    public function getFilterTotal($filter){
        return $this->filter($filter)->count();
    }

    /**
     * 格式化数据字段(按需使用)
     * @param array $data
     * @return $this
     */
    public function formatFields(){
        $data = func_get_args()[0] ?? $this;
        $data = $this::unsetArrayKeys($data);
        return $data;
    }

    /**
     * 去除数据字段前缀
     * @param $alias
     * @param $data
     * @return array
     */
    public function stripAlias($alias){
        $data = func_get_args()[1] ?? $this;
        foreach ($data as $k => $v) {
            if(strpos($k, $alias.'_') === 0){
                unset($data[$k]);
                $data[str_replace($alias . '_', '', $k)] = $v;
            }
        }
        return $data;
    }

    /**
     * 通过user_id字段得到列表数据
     * @param $user_id
     * @param int $limit
     * @return array|null
     */
    public static function getListByUserId($user_id, $limit = 1){
        $list = static::where(['user_id' => $user_id])->orderBy('sys_id desc')->limit($limit)->findAll();
        return $list ?: [];
    }

    /**
     * 通过uuid获取一条数据
     * @param string $uuid
     * @return array|null
     */
    public static function getByUuid(string $uuid){
        $result = static::where('uuid', $uuid)->find();
        return $result ? $result->formatFields() : $result;
    }

    /**
     * 通过id获取一条数据
     * @param string $id
     * @return array|null
     */
    public static function getBySysId(string $id){
        $result = static::where('sys_id', $id)->find();
        return $result ? $result->formatFields() : $result;
    }

    /**
     * @param string $mobile
     * @return BasicModel|null
     */
    public static function getByMobile(string $mobile){
        $result = static::where('mobile', $mobile)->orderBy('sys_id desc')->find();
        return $result ? $result->formatFields() : $result;
    }

    /**
     * 创建一条数据
     * @param $data
     * @return int
     */
    public function createOne($data){
        return $this->insert($data);
    }

    /**
     * 更新一条数据
     * @param $data
     * @param $condition 更新条件, 键值对
     * @return mixed
     */
    public function updateOne($data, $condition){
        return $this->where($condition)->update($data);
    }

}