<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/12/17
 * Time: 10:15
 * rpc中间件
 */

namespace App\Middleware;

use App\Model\OperateLogModel;
use App\Utility\exception\RequestException;
use App\Utility\HttpOutputTrait;
use App\Utility\UserCenter;
use App\Utility\UserCenterException;
use App\Utility\WeixinAccess;
use App\Utility\exception\AppException;
use One\Facades\Cache;
use One\Facades\Log;
use \One\Swoole\Response;
use One\Swoole\Server\HttpServer;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;

class HttpMiddleware
{
    use HttpOutputTrait;

    public function output($next, Response $response, HttpServer $server)
    {
        $this->request = $response->getHttpRequest();
        $this->response = $response;
        $result = $next();
        if(strpos($this->response->content_type, 'application/json') !== false){
            if($this->response->out_put_format=='hinabian_app_ios' || $this->request->get('format')  == 'ios'){
                return $this->hinabianAppIOSOutput($result);
            }
            if($this->response->out_put_format=='hinabian_app_android' || $this->request->get('format')  == 'android'){
                return $this->hinabianAppAndroidOutput($result);
            }
            if($this->response->out_put_format=='hinabian_old' || $this->request->get('format')  == 'pc'){
                return $this->hinabianOldOutput($result);
            }
            if(!is_scalar($result)){
                return json_encode($result);
            }
            return $result;
        }
        return $result;
    }

    public function handleException($next, Response $response, HttpServer $server){
        $this->request = $response->getHttpRequest();
        $this->response = $response;
        $log_data = [
            'ip' =>  $this->request->ip(),
            'url' =>  sprintf('%s%s?%s', $this->request->server('HTTP_HOST'), $this->request->server('REQUEST_URI'), $this->request->server('QUERY_STRING')),
        ];
        if($this->request->method() == 'post'){
            $log_data['post'] = $this->request->post() ?: ($this->request->json() ?: $this->request->input());
            $log_data['post'] = cut_data($log_data['post'], 0, 265);
        }
        try{
            $result = $next();
            return $result;
        }catch (\Throwable $e){
            if ($e instanceof UserCenterException){
                return $this->fail($e->getMessage());
            }
            $error = error_report($e, true);
            $ref = "{$error['ex_msg']} in {$error['ex_file']}";
            $error_type = 'error';
            if($e instanceof ValidationException){
                $ref = $e->getMessage();
                if($e instanceof NestedValidationException){
                    $refs=$e->getMessages();
                    if(count($refs) > 1){
                        array_shift($refs);
                    }
                    $ref = implode(', ', $refs);
                }
                $error['ex_msg'] = "$ref [Request {$this->request->uri()}]";
                $this->response->code(400);
                $result = $this->fail('请求错误, 请检查数据', 'param_err', $ref);
            }
            elseif ($e instanceof RequestException){
                $this->response->code(400);
                $result = $this->fail('请求异常, 请检查数据', 'request_err', $ref);
            }
            elseif ($e instanceof AppException){
                $this->response->code(500);
                $result = $this->fail('意外错误, 请稍后再试', 'unexpected_err', $ref);
            }
            else{
                $error_type = 'alert';
                $this->response->code(500);
                $result = $this->fail('系统错误, 请稍候再试', 'fatal_err', $ref);
            }
            $raw_data = $this->request->swoole_http_request->getData();
            $log_data['raw_data'] =  str_repeat('=', (90)) . PHP_EOL . $raw_data . PHP_EOL . str_repeat('=', 100);
            $log_data['response'] = $result;
            if(!is_scalar($result)){
                $log_data['response'] = json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
            if(env('environment') == 'pro'){
                $log_data['response'] = cut_data($log_data['response'], 0, 620, true);
            }
            $log_data['response'] = $this->response->respond_stauts_code .' '. $log_data['response'];

            $error['received_data'] = $raw_data;
            $error['run_time'] = mstime() - $server->run_time_start;
            //请求日志
            $server->task(['action' => 'log', 'type' => $error_type, 'rid' => Log::getTraceId(), 'data' => [$log_data, 0, 'request']]);
            //异常日志
            $server->task(['action' => 'log', 'type' => $error_type, 'rid' => Log::getTraceId(), 'data' => $error]);
            return $result;
        }
    }

    public function setJsonContentType($next, Response $response, HttpServer $server)
    {
        $this->request = $response->getHttpRequest();
        $this->response = $response;
        $this->response->header('Content-type', 'application/json;charset=utf-8');
        return $next();
    }

    public function logRequest($next, Response $response, HttpServer $server){
        $this->request = $response->getHttpRequest();
        $this->response = $response;
        $log_data = [
            'ip' =>  $this->request->ip(),
            'url' =>  sprintf('%s%s?%s', $this->request->server('HTTP_HOST'), $this->request->server('REQUEST_URI'), $this->request->server('QUERY_STRING')),
        ];
        if($this->request->method() == 'post'){
            $log_data['post'] = $this->request->post() ?: ($this->request->json() ?: $this->request->input());
            $log_data['post'] = cut_data($log_data['post'], 0, 265);
        }
        if(env('environment') != 'pro'){
            $raw_data = $this->request->swoole_http_request->getData();
            $log_data['raw_data'] = str_repeat('=', (90)) . PHP_EOL . $raw_data . PHP_EOL . str_repeat('=', 100);
        }
        $result = $next();
        $log_data['response'] = $result;
        if(!is_scalar($result)){
            $log_data['response'] = json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        if(env('environment') == 'pro'){
            $log_data['response'] = cut_data($log_data['response'], 0, 620, true);
        }
        $log_data['response'] = $this->response->respond_stauts_code .' '. $log_data['response'];
        $log_data['run_time'] = mstime() - $server->run_time_start;
        $server->task(['action' => 'log', 'type' => 'debug', 'rid' => Log::getTraceId(), 'data' => [$log_data, 0, 'request']]);
        return $result;
    }

    public function checkWeixinAppAccess($next, Response $response, HttpServer $server)
    {
        $this->request = $response->getHttpRequest();
        $this->response = $response;
        $this->get = $this->request->get();
        $this->session_id = $this->get['sessionid'] ?? $this->request->cookie(UserCenter::user_cookie_name);
        //微信授权初始化
        v::arrayVal()
            ->key('app_code', v::in(array_keys(WeixinAccess::apps_config)), false)
            ->assert($this->get);
        $app_code = $this->get['app_code'] ?? ($this->get['app'] ?? ($this->session()->get('app_code') ?: 'faq_app'));
        $this->session()->set('app_code', $app_code);
        $this->weixin_access = new WeixinAccess($app_code, $this->session_id ?: $this->session()->getId());
        if(!$this->weixin_access->getOpenid()){
            throw new RequestException('请先进行微信会话授权');
        }
        $result = $next();
        return $result;
    }

    public function checkWeixinOauth2Authorize($next, Response $response, HttpServer $server)
    {
        $this->request = $response->getHttpRequest();
        $this->response = $response;
        $this->get = $this->request->get();
        $this->session_id = $this->get['sessionid'] ?? $this->request->cookie(UserCenter::user_cookie_name);
        //微信授权初始化
        v::arrayVal()
            ->key('app_code', v::in(array_keys(WeixinAccess::apps_config)), false)
            ->assert($this->get);
        $app_code = $this->get['app_code'] ?? ($this->get['app'] ?? ($this->session()->get('app_code') ?: 'faq_app'));
        $this->session()->set('app_code', $app_code);
        $this->weixin_access = new WeixinAccess($app_code, $this->session_id ?: $this->session()->getId());
        if(!$this->weixin_access->getOpenid()){
            return $this->fail('未进行微信网页授权', 'no_weixin_oauth2');
        }

        $result = $next();
        return $result;
    }

    public function checkOperateLogin($next, Response $response, HttpServer $server){
        $this->request = $response->getHttpRequest();
        $this->response = $response;
        $this->get = $this->request->get();
        $admin = $this->response->session()->get('admin_info');
        $res_content = file_get_contents('https://operate.hinabian.com/index/sso/verify?ticket='.($admin['ticket']??''));
        $ret = json_decode($res_content, true);
        if(!$admin || empty($ret['data']['id'])){
            return $this->response->redirect('https://operate.hinabian.com/index/sso/login?redirect='. urlencode('https://api.hinabian.com/study-tour/Operate/index/unionLogin?redirect='.urlencode('https://api.hinabian.com'.$this->request->uri())));
            //return $this->response->redirect('https://operate.hinabian.com/index/index/logout');
            //return $this->fail('会话已失效, 请重新登录');//
        }

        if($this->request->get('action') == 'vue_compnent'){
            $this->response->header('Content-type', 'text/html;charset=utf-8');
            return $this->response->tpl(str_replace(['/study-tour/', ], ['', ], $this->request->uri()) . '.vue');
        }

        return $next();
    }

    public function checkOperatePermission($next, Response $response, HttpServer $server){
        $this->request = $response->getHttpRequest();
        $this->response = $response;
        $this->get = $this->request->get();
        $admin = $this->response->session()->get('admin_info');
        $res_content = file_get_contents('https://operate.hinabian.com/index/sso/verify?ticket='.($admin['ticket']??''));
        $ret = json_decode($res_content, true);
        if(!$admin || empty($ret['data']['id'])){
            return $this->response->redirect('https://operate.hinabian.com/index/sso/login?redirect='. urlencode('https://api.hinabian.com/study-tour/Operate/index/unionLogin?redirect='.urlencode('https://api.hinabian.com'.$this->request->uri())));
            //return $this->response->redirect('https://operate.hinabian.com/index/index/logout');
            //return $this->fail('会话已失效, 请重新登录');//
        }

        if($this->request->get('action') == 'vue_compnent'){
            $this->response->header('Content-type', 'text/html;charset=utf-8');
            return $this->response->tpl(str_replace(['/study-tour/', ], ['', ], $this->request->uri()) . '.vue');
        }

        if($admin['role_id'] != 1){
            if(!in_array($this->request->uri(), $this->response->session()->get('admin_permissions'))){
                if(!in_array(str_replace(strrchr($this->request->uri(), '/'), '', $this->request->uri()), $this->response->session()->get('module_admin_permissions'))){
                    return $this->fail('你没有该资源权限');
                }
            }
        }

        (new OperateLogModel())->createOne([
            'f_uid' => $admin['id'],
            'f_name' => $admin['name_cn'],
            'f_ctime' => time(),
            'f_method' => strtoupper($this->request->method()),
            'f_url' => $this->request->uri() .($this->request->server('QUERY_STRING') ? '?' : ''). $this->request->server('QUERY_STRING'),
            'f_data' => json_encode($this->request->post() ?: ($this->request->json() ?: [])),
        ]);

        if(!isset($this->get['action'])){
            $this->response->header('Content-type', 'text/html;charset=utf-8');
            return $this->response->tpl(str_replace('/study-tour/', '', $this->request->uri()) . '.html');

        }
        return $next();
    }

    protected function hinabianAppIOSOutput($result){
        $code = 0;
        if($result['code'] != 'ok'){
            $codes = str_split($result['code']);
            $code  = ord(array_shift($codes));
            $code = (int)($code . ord(array_pop($codes)));
            $codes[] = array_shift($codes);
            foreach ($codes as $v){
                $code = $code + ord($v);
            }
        }
        $result = [
            'rid' => $result['rid'],
            'ref' => $result['ref'],
            'state' => (string)$code,
            'errorCode' => $code,
            'data' => $result['code'] == 'ok' ? $result['data'] : $result['msg'],
        ];
        $this->response->code(200);
        return json_encode($result);
    }

    public function setHinabianAppIOSOutput($next, Response $response, HttpServer $server){
        $this->request = $response->getHttpRequest();
        $this->response = $response;
        if(strpos($this->response->content_type, 'application/json')!== false){
            $this->response->out_put_format='hinabian_app_ios';
        }
        return $next();
    }

    protected function hinabianAppAndroidOutput($result){
        $code = 0;
        if($result['code'] != 'ok'){
            $codes = str_split($result['code']);
            $code  = ord(array_shift($codes));
            $code = (int)($code . ord(array_pop($codes)));
            $codes[] = array_shift($codes);
            foreach ($codes as $v){
                $code = $code + ord($v);
            }
        }
        $result = [
            'rid' => $result['rid'],
            'ref' => $result['ref'],
            'state' => $code,
            'msg' => $result['msg'],
            'data' => $result['data'],
        ];
        $this->response->code(200);
        return json_encode($result);
    }

    public function setHinabianAppAndroidOutput($next, Response $response, HttpServer $server){
        $this->request = $response->getHttpRequest();
        $this->response = $response;
        if(strpos($this->response->content_type, 'application/json')!== false){
            $this->response->out_put_format='hinabian_app_android';
        }
        return $next();
    }

    protected function hinabianOldOutput($result){
        $code = 0;
        if($result['code'] != 'ok'){
            $codes = str_split($result['code']);
            $code  = ord(array_shift($codes));
            $code = (int)($code . ord(array_pop($codes)));
            $codes[] = array_shift($codes);
            foreach ($codes as $v){
                $code = $code + ord($v);
            }
        }
        $result = [
            'rid' => $result['rid'],
            'ref' => $result['ref'],
            'state' => $code,
            'errorCode' => $code,
            'data' => $result['code'] == 'ok' ? $result['data'] : $result['msg'],
        ];
        $this->response->code(200);
        return json_encode($result);
    }

    public function setHinabianOldOutput($next, Response $response, HttpServer $server){
        $this->request = $response->getHttpRequest();
        $this->response = $response;
        if(strpos($this->response->content_type, 'application/json')!== false){
            $this->response->out_put_format='hinabian_old';
        }
        return $next();
    }


}
