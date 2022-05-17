<?php

namespace App\Controllers;

use App\Utility\exception\AppException;
use App\Utility\WeixinAccess;
use Respect\Validation\Validator as v;
use Yansongda\Pay\Exceptions\GatewayException;
use Yansongda\Pay\Exceptions\InvalidSignException;
use Yansongda\Pay\Pay;
use Yansongda\Pay\Log;

class WechatPayController extends BasicController
{
    use PayBusinessTrait;

    protected $config = [
        //'appid' => '', // APP APPID
        'app_id' => 'wxca33b0fbc98a8917', // 公众号 APPID
        //'miniapp_id' => '', // 小程序 APPID
        'mch_id' => '1562378491',
        'key' => 'Bx3r2p8O2Q2dBtqFpDw68ymOx4XkMihG',
        'notify_url' => 'https://api.hinabian.com/study-tour/service-card/wechat-pay/notify',
        'cert_client' => _APP_PATH_ .'/Utility/wechat_pay_cert/apiclient_cert.pem', // optional，退款等情况时用到
        'cert_key' => _APP_PATH_ .'/Utility/wechat_pay_cert/apiclient_key.pem',// optional，退款等情况时用到
        'log' => [ // optional
            'file' => _APP_PATH_ .'/RunCache/log/wechat_pay/yansongda_pay.log',
            'level' => 'debug', // 建议生产环境等级调整为 info，开发环境为 debug
            'type' => 'single', // optional, 可选 daily.
            'max_file' => 30, // optional, 当 type 为 daily 时有效，默认 30 天
        ],
        'http' => [ // optional
            'timeout' => 5.0,
            'connect_timeout' => 5.0,
            'verify' => false
            // 更多配置项请参考 [Guzzle](https://guzzle-cn.readthedocs.io/zh_CN/latest/request-options.html)
        ],
        //'mode' => 'dev', // optional,  dev/hk;当为 `hk` 时，为香港 gateway。
    ];

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

    //发起支付
    public function index(){
        $this->server->task(['action' => 'log', 'type' => 'debug', 'rid' => \One\Facades\Log::getTraceId(), 'data' => [
            ['发起支付', $this->post], 0, 'wechat_pay'
        ]]);
        $cache_key = 'wechat_pay_info_' . md5(json_encode($this->post));
        $info = cache($cache_key);
        if(!$info){
            v::arrayVal()
                ->key('biz_code', v::in(['1v1']))
                ->assert($this->post);
            $method = static::$biz_methods[$this->post['biz_code']]['index'];
            $order = $this->$method();
            $back_data_key = md5(json_encode($order['back_data']));
            cache($back_data_key, $order['back_data'], 3600);
            $pay = Pay::wechat($this->config)->mp([
                'out_trade_no' => $order['order_id'],
                'total_fee' => $order['amount'] * 100,
                'body' => $order['title'],
                'openid' => $this->weixin_access->getOpenid(),
                'attach' => $back_data_key,
            ]);
            $info = [
                'appId' => $pay->appId,
                'timeStamp' => $pay->timeStamp,
                'nonceStr' => $pay->nonceStr,
                'package' => $pay->package,
                'signType' => $pay->signType,
                'paySign' => $pay->paySign,
            ];
            cache($cache_key, $info, 3600);
        }
        $this->response->respond_data['info'] = $info;
        return $this->ok();
    }

    //异步回调
    public function notify()
    {
        $this->server->task(['action' => 'log', 'type' => 'debug', 'rid' => \One\Facades\Log::getTraceId(), 'data' => [
            ['收到支付回调', $this->request->input()], 0, 'wechat_pay'
        ]]);
        $input_data = simplexml_load_string($this->request->input(), null, LIBXML_NOCDATA);
        $input_data = json_decode(json_encode($input_data),true);
        $back_data = cache($input_data['attach']);
        try{
            $pay = Pay::wechat($this->config);
            $data = $pay->verify($this->request->input());
            $back_data = cache($data['attach']);
            v::arrayVal()
                ->key('rid', v::notEmpty())
                ->key('biz_code', v::in(array_keys(static::$biz_methods)))
                ->assert($back_data);
            $method = static::$biz_methods[$back_data['biz_code']]['notify'];
            $this->$method([
                'back_data' => $back_data,
                'order_id' => $data['out_trade_no'],
                'pay_trade_no' => $data['transaction_id'],
                'pay_amount' => $data['cash_fee'] / 100,
            ]);
            $this->server->task(['action' => 'log', 'type' => 'debug', 'rid' => \One\Facades\Log::getTraceId(), 'data' => [
                '支付回调成功', 0, 'wechat_pay'
            ]]);
            $result = $pay->success();
            $this->response->header('Content-type', 'application/xml');
            return $result->getContent();
        } catch (InvalidSignException $e) {
            $this->server->task(['action' => 'log', 'type' => 'debug', 'rid' => $back_data['rid'], 'data' => [
                ['Wechat notify InvalidSignException', $e->getMessage(), $e->raw], 0, 'wechat_pay'
            ]]);
            throw new AppException(sprintf( '微信支付异步回调异常, InvalidSignException, %s, %s %s', $e->getMessage(), $e->getFile(), $e->getLine()));
        } catch (GatewayException $e) {
            $this->server->task(['action' => 'log', 'type' => 'debug', 'rid' => $back_data['rid'], 'data' => [
                ['Wechat notify GatewayException', $e->getMessage(), $e->raw], 0, 'wechat_pay'
            ]]);
            throw new AppException(sprintf( '微信支付异步回调异常, GatewayException, %s, %s %s', $e->getMessage(), $e->getFile(), $e->getLine()));
        } catch (\Throwable $e){
            $this->server->task(['action' => 'log', 'type' => 'debug', 'rid' => \One\Facades\Log::getTraceId(), 'data' => [
                ['支付回调失败', $e->getMessage()], 0, 'wechat_pay'
            ]]);
            return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            //throw $e;
        }
    }
}