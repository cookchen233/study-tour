<?php

namespace App\Controllers;


use App\Model\BasicModel;
use App\Model\CrmUserModel;
use App\Model\PageConfigModel;
use App\Model\PolicyOrderModel;
use App\Model\TopicCommentModel;
use App\Model\UserKeyOpModel;
use App\Model\UserModel;
use App\Utility\Sms;
use App\Utility\UserCenter;
use App\Utility\UserCenterException;
use App\Utility\WeixinAccess;
use App\Utility\exception\AppException;
use One\Facades\Cache;
use One\Facades\Log;
use Respect\Validation\Validator as v;

class HomeController extends BasicController
{

    public function __construct($request, $response, $server = null)
    {
        parent::__construct($request, $response, $server);
        //微信授权初始化
        v::arrayVal()
            ->key('app_code', v::in(array_keys(WeixinAccess::apps_config)), false)
            ->assert($this->get);
        $app_code = $this->get['app_code'] ?? ($this->get['app'] ?? ($this->session()->get('app_code') ?: 'faq_app'));
        $this->session()->set('app_code', $app_code);
        $this->weixin_access = new WeixinAccess($app_code, $this->session_id ?: $this->session()->getId());
    }

    public function receiveWeixinPush($app){
        $this->response->header('Content-type', 'text/html;charset=utf-8');
        Log::debug([
            'title' => 'received',
            'method' => $this->request->method(),
            'get'=> $this->request->get(),
            'input'=> $this->request->input(),
        ], 0, 'weixin');
        $token = WeixinAccess::apps_config[$app]['token'];
        $signature = $this->get["signature"];
        $timestamp = $this->get["timestamp"];
        $nonce = $this->get["nonce"];
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );
        if(!hash_equals($tmpStr, $signature)){
            throw new AppException(sprintf('微信签名校验错误, hash:%s, received:%s', $tmpStr, $signature));
        }
        if(isset($this->get['echostr'])){
            Log::debug('authentication ok', 0, 'weixin');
            return $this->get['echostr'];
        }
        Log::debug('service msg start', 0, 'weixin');
        $this->receiveWeixinServiceMessage($app, $this->request->json());
        Log::debug('service msg end', 0, 'weixin');
        return 'success';
    }
    protected function receiveWeixinServiceMessage($app, $receive_data){
        $weixin_access = new WeixinAccess($app);
        $default_msg = function($weixin_access, $receive_data){
            $data = ['content' => "暂时还不能理解您的指令哦，\n您可以回复1试试"];
            $result = $weixin_access->sendServiceMessage($data, $receive_data['FromUserName'], 'text');
            if(!$result){
                throw new AppException(sprintf('微信消息发送失败(%s), %s(%s)', $data['content'], $weixin_access->msg, $weixin_access->code));
            }
            return true;
        };
        if($receive_data['MsgType'] == 'text'){
            // 回复进群二维码
            if($receive_data['Content'] == 1){
                $media_cache_key = 'media_id_weixin_group';
                $media_id = cache($media_cache_key);
                $retry = 0;
                if(!$media_id){
                    uploadTempMedia:{}
                    Log::debug('uploadTempMedia retry: '. $retry, 0, 'weixin');
                    $result = $weixin_access->uploadTempMedia(_APP_PATH_ . '/public/weixin_group.png');
                    if(!$result){
                        throw new AppException(sprintf('微信图片上传失败, %s(%s)', $weixin_access->msg, $weixin_access->code));
                    }
                    $media_id = $result['media_id'];
                    cache($media_cache_key, $media_id, 3600 * 23 * 3);
                }
                $data = ['media_id' => $media_id];
                Log::debug(['title' => 'sendServiceMessage', 'data' => $data, 'to' => $receive_data['FromUserName'], 'retry' => $retry], 0, 'weixin');
                $result = $weixin_access->sendServiceMessage($data, $receive_data['FromUserName'], 'image');
                if(!$result){
                    if($weixin_access->code == 40007 && $retry < 1){
                        $retry++;
                        goto uploadTempMedia;
                    }
                    throw new AppException(sprintf('微信消息发送失败(media_id=%s), %s(%s)', $data['media_id'], $weixin_access->msg, $weixin_access->code));
                }
                return true;
            }
            else{
                return $default_msg($weixin_access, $receive_data);
            }
        }
        elseif ($receive_data['MsgType'] == 'event'){
            if($receive_data['Event'] == 'user_enter_tempsession'){
                $data = ['content' => "请回复1吧"];
                $result = $weixin_access->sendServiceMessage($data, $receive_data['FromUserName'], 'text');
                if(!$result){
                    throw new AppException(sprintf('微信消息发送失败(%s), %s(%s)', $data['content'], $weixin_access->msg, $weixin_access->code));
                }
                return true;
            }
        }
        else{
            return $default_msg($weixin_access, $receive_data);
        }
    }

    public function decryptWeixinData(){
        $result = $this->weixin_access->decryptData($this->post['encrypt_data'], $this->post['iv']);
        $this->response->respond_data['decrypt_data'] = $result;
        return $this->ok();
    }

    //微信小程序登录授权, 通过客户端的凭证code请求code2session接口
    public function weixinAppSessionAccess(){
        v::arrayVal()
            ->key('code', v::notEmpty())
            ->assert($this->get);
        $result = $this->user_center->code2Session($this->weixin_access->config['appid'], $this->get['code']);
        $this->weixin_access = new WeixinAccess($this->session()->get('app_code'), $this->user_center->getUserCookie());
        $this->weixin_access->setOpenid($result['openid']);
        $this->response->respond_data['sessionid'] = $this->user_center->getUserCookie();
        $this->response->respond_data['expires'] = 7200;
        return $this->ok();
    }


    /**
     * 微信网页链接跳转
     * 所有前端url进入微信浏览器之前,先经过该方法取得openid后再跳回前端url.
     * 1.访问weixinRedirect(拼接了前端url参数)
     * 2.跳转至微信授权页(如果openid过期)
     * 3.跳回weixinPageAccessCallback
     * 4.跳回前端url
     */
    public function weixinOauth2Authorize(){
        if($this->weixin_access->getOpenid()){
            $this->response->redirect(urldecode($this->get['url']));
        }
        else{
            $this->response->redirect(sprintf('https://open.weixin.qq.com/connect/oauth2/authorize?appid=%s&redirect_uri=%s&response_type=code&scope=snsapi_base&state=%s#wechat_redirect',
                WeixinAccess::apps_config[$this->get['app']]['appid'],
                urlencode('https://api.hinabian.com/insurance-service/weixinOauth2AuthorizeCallback?'. http_build_query($this->get)),
                ''
            ));
        }
        //接用户中心
        /*$result = UserCenter::getWeixinRedirectUrl(WeixinAccess::apps_config[$this->get['app']]['appid'], urlencode('https://api.hinabian.com/insurance-service/weixinOauth2AuthorizeCallback?'. http_build_query($this->get)));
        var_dump($result);*/
    }
    public function weixinOauth2AuthorizeUserInfo(){
        if($this->weixin_access->getOpenid()){
            $this->response->redirect(urldecode($this->get['url']));
        }
        else{
            $this->response->redirect(sprintf('https://open.weixin.qq.com/connect/oauth2/authorize?appid=%s&redirect_uri=%s&response_type=code&scope=snsapi_userinfo&state=%s#wechat_redirect',
                WeixinAccess::apps_config[$this->get['app']]['appid'],
                urlencode('https://api.hinabian.com/insurance-service/weixinOauth2AuthorizeCallback?'. http_build_query($this->get)),
                ''
            ));
        }
    }

    public function weixinOauth2AuthorizeCallback(){
        /*$result = (new WeixinAccess($this->get['app'], $this->weixin_access->sessionid))->getUserInfoByOauth2($this->get['code'], $this->get['url']);
        if($result === false){
            throw new AppException(sprintf('获取微信用户信息发生错误, %s(%s)', $this->weixin_access->msg, $this->weixin_access->code));
        }
        $this->activeUser();
        //跳回至前端url
        $this->response->redirect(urldecode($this->get['url']));*/
        //接用户中心
        $user_center = new UserCenter();
        $weixin_info =  $user_center->getWeixinUserInfo(WeixinAccess::apps_config[$this->get['app']]['appid'], $this->get['url'], $this->get['code']);
        foreach ($user_center->cookie_jar->toArray() as $v){
            if(!empty($v['Expires']) || !empty($v['Max-Age'])){
                $this->response->cookie($v['Name'], $v['Value'], $v['Expires'], $v['Path'], $v['Domain'], $v['Secure'], $v['HttpOnly']);
            }
        }
        (new WeixinAccess($this->get['app'], $user_center->getUserCookie()->getValue()))->setOpenid($weixin_info['openid']);
        $this->response->redirect(urldecode($this->get['url']));
    }

    public function getJsTicket(){
        v::arrayVal()
            ->key('url', v::notEmpty())
            ->key('app_code', v::in(array_keys($this->weixin_access::apps_config)))
            ->assert($this->get);
        $ticket = $this->weixin_access->getJsTicket();
        if(!$ticket){
            throw new AppException(sprintf('微信jsticket获取失败, %s(%s)', $this->weixin_access->msg, $this->weixin_access->code));
        }
        $time = time();
        $nonceStr = rand_str();
        $this->response->respond_data = [
            'appId' => $this->weixin_access->config['appid'],
            'ticket' => $ticket,
            'timestamp' => $time,
            'nonceStr' => $nonceStr,
            'signature' => sha1('jsapi_ticket='. $ticket .'&noncestr='. $nonceStr .'&timestamp='. $time .'&url='. $this->get['url'])
        ];
        return $this->ok();
    }

    public function login(){
        v::arrayVal()
            //->key('name',v::notEmpty())
            ->key('mobile',v::mobile())
            ->key('captcha',v::notEmpty())
            ->assert($this->post);
        $source_data = $this->validSource('register');
        if(!$this->validCaptcha($this->post['mobile'],$this->post['captcha'], $source_data)){
            return $this->fail('验证码错误');
        }

        try{
            $model=new UserModel();
            $model->beginTransaction();
            //创建用户
            /*d$source_data = $this->getUserSourceData();
            $uid = (new UserModel())->createNxAndCrmUser([
                //'name' => $this->post['name'],
                'mobile' => $this->post['mobile'],
            ], $source_data);
            //创建用户操作日志
            $log_data = $source_data;
            $log_data['f_content'] = <<<eot
主动注册
eot;
            $log_data['f_uid'] = $this->user_info['f_id'];
            (new UserKeyOpModel())->createOne($log_data);*/

            $source_data['content'] = <<<eot
主动注册
eot;
            $this->user_center->setUserInfo($this->post);
            $this->user_center->entryCRM($this->user_info['mobile'], $source_data, $this->post);
            $model->commit();
            return $this->ok();
        }catch (\Throwable $e){
            $model->rollBack();
            throw $e;
        }
    }

    public function getUserInfo(){
        try{
            $user_info = $this->user_center->getUserInfo();
        }
        catch (UserCenterException $e){
            if($e->getCode() == 100003){
                return $this->fail($e->getMessage(), 'no_login');
            }
            throw $e;
        }
        $weixin = $this->weixin_access->getUserInfo();
        if(!empty($weixin['headimgurl'])){
            $weixin['headimgurl'] = substr_replace($weixin['headimgurl'], '0', -3);
        }
        $user_info['weixin'] = $weixin;
        $this->response->respond_data['info'] = $user_info;
        return $this->ok();
    }

    //发送验证码
    public function sendCaptcha(){
        v::arrayVal()
            ->key('mobile', v::mobile())
            ->key('idd_code', v::notEmpty(), false)
            ->assert($this->post);
        $limit_count_key = 'send_captcha_'. $this->request->ip();
        $count = cache($limit_count_key) ?: 0;
        if($count > 39){
            return $this->fail('你的操作过于频繁, 请于1小时后再试');
        }
        $count++;
        cache($limit_count_key, $count, 3600);
        //(new Sms())->sendVcode($this->post['mobile'], $this->post['idd_code'] ?? 86);
        $this->user_center->sendVcode($this->post['mobile'], $this->post['idd_code'] ?? 86);
        return $this->ok();
    }

    public function validateCaptcha(){
        v::arrayVal()
            ->key('mobile', v::mobile())
            ->key('captcha', v::notEmpty())
            ->assert($this->get);
        $limit_count_key = 'validate_captcha_'. $this->request->ip();
        $count = cache($limit_count_key) ?: 0;
        if($count > 39){
            return $this->fail('你的操作过于频繁, 请于1小时后再试');
        }
        $count++;
        cache($limit_count_key, $count, 3600);
        $this->user_center->verifyVcode($this->get['mobile'],$this->get['captcha']);
        return $this->ok();
    }

    public function makeAppt(){
        v::arrayVal()
            ->key('name',v::notEmpty(), false)
            ->key('content',v::notEmpty(), false)
            ->assert($this->post);
        $source_data = $this->validSource('reserve');
        if(!$this->setLogin() || !empty($this->post['mobile'])){
            v::arrayVal()
                ->key('mobile',v::mobile())
                ->key('captcha',v::notEmpty())
                ->assert($this->post);
            if(!$this->validCaptcha($this->post['mobile'],$this->post['captcha'], $source_data)){
                return $this->fail('验证码错误');
            }
        }
        $user_model = new UserModel();
        try{
            $user_model->beginTransaction();
            //创建用户
            /*$uid = $user_model->createNxAndCrmUser([
                'name' => $this->post['name'] ?? '',
                'mobile' => $this->post['mobile'],
            ], $source);
            //创建用户操作日志
            $log_data = $source;
            $log_data['f_content'] = $this->post['content'] ?? '预约';
            $log_data['f_uid'] = $this->user_info['f_id'];
            (new UserKeyOpModel())->createOne($log_data);*/

            $source_data['content'] = $this->post['content'] ?? '预约';
            $this->user_center->setUserInfo($this->post);
            $this->user_center->entryCRM($this->user_info['mobile'], $source_data, $this->post);
            $user_model->commit();
            return $this->ok();
        }catch (\Throwable $e){
            $user_model->rollBack();
            throw $e;
        }
    }

    public function syncEnvCache(){
        v::arrayVal()
            ->key('sign', v::notEmpty())
            ->key('data', v::notEmpty())
            ->key('time', v::intVal())
            ->assert($this->post);
        if(!hash_equals($this->post['sign'], md5($this->post['data'].$this->post['time'].'M8fa7rBBmEZmusWgncCIRg6Mj73aH3lk'))){
            return $this->fail('签名错误');
        }
        $data = BasicModel::decryptData($this->post['data']);
        $data = json_decode($data, true);
        cache($data['key'], $data['value'], $data['exp'] ?? null);
        return $this->ok();
    }

    //测试环境查看验证码
    public function viewCaptcha(){
        if(env('environment') == 'pro'){
            return '仅测试环境查看';
        }
        v::arrayVal()
            ->key('mobile', v::mobile())
            ->assert($this->get);
        return (new Sms())->getVcode($this->get['mobile']) ?: '无';
    }

    //制造内部用户
    public function fakeUser(){
        if(env('environment') == 'pro'){
            return $this->fail('请在测试环境尝试');
        }
        $source_data = $this->getUserSourceData();
        $faker = \Faker\Factory::create('zh_CN');
        for ($i = 0; $i < 100; $i++) {
            $r=mt_rand(1,1695);
            $avatar="https://cache.hinabian.com/avatar/aratar_$r.jpg";
            if($r>500){
                $name=$faker->userName;
                if($r>1000){
                    $name=$faker->name;
                }
            }
            else{
                $name='';
                $avatar='';
            }
            /*(new UserModel())->createNxAndCrmUser([
                'name' => $name,
                'mobile' => $faker->phoneNumber,
                'avatar' => $avatar,
                'f_type'=>'internal',
                'f_is_test'=>1,
            ], $source_data);*/
        }
        return $this->ok();
    }

    //M站首页数据
    public function index(){
        $page_config=[];
        $model=new PageConfigModel();
        foreach ($model::LOCATION as $location) {
            $list = $model->getFilterList(['is_enabled' => 1, 'correlation_is_enabled' => 1, 'location' => $location], 1, 30, 'sort asc');
            $page_config[$location] = [];
            foreach ($list as $k => $v) {
                $v->formatFields();
                if(!empty($v['t_comments'])){
                    $v['t_comments']=TopicCommentModel::where(['topic_uuid' => $v['t_uuid'], 'is_enabled'=>1])->count();
                }
                $page_config[$location][] = $v;
            }
        }
        $this->response->respond_data['page_config'] = $page_config;

        //海那边App首页弹窗
        $info = $model->where(['location' => 'recommendSchemePop'])->find();
        if($info){
            $info = $info->formatFields()->toArray();
            $info = array_merge($info, $info['extra']);
        }
        $this->response->respond_data['recommendSchemePop'] = $info;
        return $this->ok();
    }


}




