<?php

namespace App\Controllers;

use App\Model\UserModel;
use App\Utility\HttpOutputTrait;
use App\Utility\Sms;
use App\Utility\UserCenter;
use App\Utility\UserCenterException;
use App\Utility\WeixinAccess;
use App\Utility\exception\AppException;
use One\Facades\Cache;
use One\Facades\Log;
use One\Http\Controller;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;

class BasicController extends Controller
{

    use HttpOutputTrait;

    protected $get;
    protected $post;

    protected $lock_info;

    /**
     * @var WeixinAccess
     */
    protected $weixin_access;

    const USER_ID_SESSION_KEY = 'session_user_id';
    protected $user_info;

    /**
     * @var UserCenter
     */
    protected $user_center;

    protected $session_id;

    public function __construct($request, $response, $server = null)
    {
        parent::__construct($request, $response, $server);
        //$this->response->header('Content-type', 'text/html;charset=utf-8');
        //$this->response->header('Content-type', 'application/json');
        v::with('App\\Utility\\hivalidation\\rule');
        $this->get = $this->request->get() ?: [];
        $this->post = $this->request->post();
        if(!$this->post){
            $input = $this->request->input();
            if(strpos($input, '/*')){
                $input = preg_replace('/\/\*.*?\*\/\"/', '"', $input);
            }
            $this->post = json_decode($input, true);
            if($this->post === null && isset($this->request->header['content-type']) && $this->request->header['content-type'] == 'application/json'){
                throw new ValidationException('JSON数据语法有误, 请检查');
            }
            $this->post = $this->post ?: [];
        }

        $this->session_id = $this->get['sessionid'] ?? $this->request->cookie(UserCenter::user_cookie_name);

        $this->user_center = new UserCenter($this->request->cookie());

        Log::setTraceId(sprintf('%s[sessionid=%s][user_id=%s]', Log::getTraceId(), $this->session_id, $user_info['f_id'] ?? ''));
        if(isset($user_info['f_id'])){
            $this->user_info = $user_info;
            $log_data = [
                'ip' =>  $this->request->ip(),
                'url' =>  sprintf('%s%s?%s', $this->request->server('HTTP_HOST'), $this->request->server('REQUEST_URI'), $this->request->server('QUERY_STRING')),
            ];
            if($this->request->method() == 'post'){
                $log_data['post'] = $this->request->post() ?: ($this->request->json() ?: $this->request->input());
                $log_data['post'] = cut_data($log_data['post'], 0, 265);
            }
            $this->server->task(['action' => 'log', 'type' => 'debug', 'rid' => Log::getTraceId(), 'data' => [
                $log_data, 0, 'user/'.$user_info['f_id']
            ]]);
        }
    }

    protected function setLogin($user_id = null){
        $user_info = null;
        try{
            $user_info = $this->user_center->getUserInfo($user_id);
        }catch (UserCenterException $e){}
        if(!$user_info){
            return false;
        }
        foreach ($this->user_center->cookie_jar->toArray() as $v){
            if(!empty($v['Expires']) || !empty($v['Max-Age'])){
                $this->response->cookie($v['Name'], $v['Value'], $v['Expires'], $v['Path'], $v['Domain'], $v['Secure'], $v['HttpOnly']);
            }
        }
        $this->user_info = $user_info;
        return $this->user_info;
    }

    /**
     * 防并发锁
     * 使用方法, 在需要防止并发的开始处调用 $result = $this->lock('锁标识');结束处调用 $this->unlock($result);
     * @param $lock_key 锁标识,根据情况命名,不与其他程序段重复即可
     * @return array
     */
    protected function lock($lock_key = null){
        set_time_limit(0);
        if(!$lock_key){
            $lock_key = uniqid();
        }
        $this->lock_info = lock('lock_'. env('service_name'). md5($lock_key));
        if ($this->lock_info === false) {
            throw new AppException('锁超时, '. $lock_key);
        }
        return $this->lock_info;
    }

    protected function unlock($lock_info = null){
        if(!$lock_info){
            $lock_info = $this->lock_info;
        }
        unlock($lock_info);
    }

    /**
     * crm用户入库需要的渠道参数
    $platform1 = [
    '0' => 'other',
    '1' => 'windows',
    '2' => 'ios',
    '3' => 'android',
    '4' => 'mac',
    ];
    $platform2 = array(
    '0' => 'other',
    '1' => 'pc',
    '2' => 'h5',
    '3' => 'app',
    '4' => 'wxxcx',
    '5' => 'zfbxcx',
    );
     * 行为(register: 登录(注册), reserve: 预约, assess: 评估, comment:评论, group:入群, buy:购买)
     * 二级来源(998252357: 海那边ios, 40f2aec903b30df94197d24602792819: 海那边安卓, bxh5: 保险h5)
     * @param string $action
     * @return array
     */
    protected function getUserSourceData($action){
        $ua = $this->request->userAgent();
        if (preg_match('/win/i', $ua)) {
            $platform1 = 1;
        } elseif (preg_match('/iPhone OS|IOS_DigHouse|IOS_Hinabian/i', $ua)) {
            $platform1 = 2;
        } elseif (preg_match('/Linux; Android|Android_DigHouse|Android_Hinabian/i', $ua)) {
            $platform1 = 3;
        } elseif (preg_match('/Intel Mac OS/i', $ua)) {
            $platform1 = 4;
        } else {
            $platform1 = 0;
        }

        if ($platform1 == 1 || $platform1 == 4) {
            $platform2 = 1;
        } elseif ($platform1 == 2 || $platform1 == 3) {
            $platform2 = 2;
        } elseif (preg_match('/DigHouse|Hinabian/i', $ua)) {
            $platform2 = 3;
        } elseif (preg_match('/MicroMessenger/i', $ua)) {
            $platform2 = 4;
        } elseif (preg_match('/Alipay/i', $ua)) {
            $platform2 = 5;
        } else {
            $platform2 = 0;
        }
        $data = [
            'f_sid' => $this->session()->getId(),
            'f_platform1' => $this->request->header['os'] ?? $platform1,
            'f_platform2' => $this->request->header['platform'] ?? $platform2,
            'f_source' => $this->request->header['source'] ?? ($this->get['src'] ?? ''),
            'f_appid' => $this->request->header['second-source'] ?? ($this->get['ssrc'] ?? ''),
            'f_path' => $this->request->header['path'] ?? ($this->get['url'] ?? ($this->request->header['HTTP_REFERER'] ?? '')),
            'f_channel' => $this->request->header['cid'] ?? ($this->request->header['channel'] ?? ($this->get['ch'] ?? '')),
            'f_sceneid' => $this->request->header['sceneid'] ?? '',
            'f_cid' => $this->request->header['cid'] ?? ($this->request->header['channel'] ?? ($this->get['ch'] ?? '')),
            'f_action' => $action, // ?: ($this->request->header['act'] ?? ($this->get['act'] ?? 'reserve')),
            'f_data' => json_encode($this->post ?: $this->get),
            'f_keyword' => $this->request->header['keyword'] ?? '',
            'f_content' => $this->request->header['content'] ?? '',
            'f_ua' => $ua,
            'f_ip' => $this->request->ip(),
            'f_ctime' => time(),
        ];
        return $data;
    }

    protected function validSource($action){
        $source = $this->getUserSourceData($action);
        $source['os']=$source['f_platform1'];
        $source['platform']=$source['f_platform2'];
        $source['source']=$source['f_source'];
        $source['second-source']=$source['f_appid'];
        $source['path']=$source['f_path'];
        $source['channel']=$source['f_channel'];
        $source['act']=$source['f_action'];
        try{
            v::arrayVal()
                ->key('os', v::notEmpty())
                ->key('platform',v::notEmpty())
                ->key('source',v::notEmpty())
                ->key('second-source',v::notEmpty())
                ->key('path',v::notEmpty())
                ->key('channel',v::notEmpty())
                ->key('act',v::in(['register', 'reserve', 'assess', 'comment', 'group', 'buy']))
                ->assert($source);
        } catch (ValidationException $e){
            $msg = $e->getMessage();
            if($e instanceof NestedValidationException){
                $msgs=$e->getMessages();
                if(count($msgs) > 1){
                    array_shift($msgs);
                }
                $msg = implode(', ', $msgs);
            }
            throw new ValidationException('来源信息参数错误: '. $msg);
        }
        return $source;
    }

    protected function validCaptcha($mobile, $captcha, $source, $type = 1){
        /*if(strpos($mobile, '1700') === 0 && env('environment')!='pro'){
            return true;
        }
        return (new Sms())->verifyVcode($mobile, $captcha,$expire);*/
        if(!$this->user_center->loginByMobile($mobile, $captcha, $source)){
            return false;
        }
        foreach ($this->user_center->cookie_jar->toArray() as $v){
            if(!empty($v['Expires']) || !empty($v['Max-Age'])){
                $this->response->cookie($v['Name'], $v['Value'], $v['Expires'], $v['Path'], $v['Domain'], $v['Secure'], $v['HttpOnly']);
            }
        }
        $this->setLogin();
        return true;
    }

}




