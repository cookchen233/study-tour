<?php
/**
 * Created by PhpStorm.
 * User: tanszhe
 * Date: 2018/8/24
 * Time: 下午4:26
 */

namespace App\Server;

use App\Utility\exception\AppException;
use App\Utility\hilog\HiLogger;
use One\Facades\Log;
use One\Http\Router;
use One\Swoole\Event\Swoole;
use One\Swoole\Server\HttpServer;

class AppHttpServer extends HttpServer
{

    public $consul_service_info = [];

    public function __construct(\swoole_server $server, array $conf)
    {
        parent::__construct($server, $conf);
        $ports = config('protocol.add_listener');
        $service_types = [
            'http' => ['port'=> config('protocol.server.port')],
            'rpc_http' => ['port'=> $ports[0]['port']],
            'rpc_tcp' => ['port'=> $ports[1]['port']],
        ];
        $register_ips = explode(',', env('service_register_host'));
        $check_ip = env('service_check_host');
        $check = "http://$check_ip:{$service_types['http']['port']}/check-service/";
        foreach ($register_ips as  $k => $ip){
            foreach ($service_types as $k1 => $type){
                $id = uniqid();
                $tag = $k1 . ($k ? '_'. $k : '');
                $this->consul_service_info[$id] = ['service_id' => $id, 'check' => $check . $id, 'ip' => $ip, 'port' => $type['port'], 'tag' => $tag ];
            }
        }
    }

    public function onRequest(\swoole_http_request $request, \swoole_http_response $response)
    {
        parent::onRequest($request, $response);
        // 跨域
        if(isset($request->header['origin'])){
            $origin = parse_url($request->header['origin']);
            $allow_host = isset($origin['host']) ? $origin['scheme'] .'://' . $origin['host']. (isset($origin['port']) ? ':'. $origin['port'] : '') : '*';
            //header("Access-Control-Allow-Credentials: true");
            //header('Access-Control-Allow-Headers: Authorization,User-Agent,Keep-Alive,Content-Type,X-Requested-With,X_Requested_With,x-token');
            //header('Access-Control-Allow-Origin:'. $origin['scheme'] .'://' . $allow_host);
            $response->header('Access-Control-Allow-Credentials', 'true');
            $response->header('Access-Control-Allow-Origin', $allow_host);
            $response->header('Access-Control-Allow-Methods', 'GET,POST,DELETE,PUT,PATCH,OPTIONS');
            if(isset($request->header['access-control-request-headers'])){
                $response->header('Access-Control-Allow-Headers', $request->header['access-control-request-headers']);
            }
        }
        if (strtolower($request->server['request_method']) == 'options'){
            $response->end();
            return;
        }

        $this->httpRouter($request,$response);
    }

    // 与worker进程隔离,get_co_id()为-1
    public function onStart(\swoole_server $server){

    }

    public function onWorkerStart(\swoole_server $server, $worker_id)
    {
//        $start_data = $server->start_table;
//        $start_data->get(1, 'service_registered');
//        $start_data->set(1, ['service_registered'=> 1]);
        if($worker_id == 1){
            //注册 consul 服务
            echo 'register with consul ...' . PHP_EOL;
            $successful = true;
            foreach ($this->consul_service_info as $k => $info){
                $service = [
                    'ID'                => $info['service_id'],
                    'Name'              => env('service_name'),
                    'Tags'              => [
                        env('service_desc'), $info['tag']
                    ],
                    'Address'           => $info['ip'],
                    'Port'              => $info['port'],
                    'Meta'              => [
                        'version' => '1.0'
                    ],
                    'EnableTagOverride' => false,
                    'Weights'           => [
                        'Passing' => 10,
                        'Warning' => 1
                    ],
                    "Check" =>["DeregisterCriticalServiceAfter"=>"90m", "HTTP"=>$info['check'], "Interval"=>"10s",/*"TTL"=>"15s",*/],
                ];
                $postdata = json_encode($service);
                $opts = array('http' =>
                    array(
                        'method'  => 'PUT',
                        'timeout' => 6,
                        'header'  => 'Content-Type:application/json',
                        'content' => $postdata
                    )
                );
                $context  = stream_context_create($opts);
                try{
                    echo sprintf('%s:%s, ', $info['ip'], $info['port']);
                    $url = sprintf('http://%s:%s/v1/agent/service/register', env('consul_host'), env('consul_port'));
                    file_get_contents($url, false, $context);
                    echo 'ok'. PHP_EOL;
                }catch (\Throwable $e){
                    $successful = false;
                    $title = '服务注册失败';
                    echo sprintf('%s, %s%s', $title, $e->getMessage(), PHP_EOL);
                    system(sprintf('ping -c 5 -w 3  %s', env('consul_host')));
                    echo PHP_EOL;
                    $error = error_report($e, true);
                    \One\Facades\Log::alert([
                        'title' => $title,
                        'ex_msg' => $error['ex_msg'],
                        'ex_file' => $error['ex_file'],
                        'trace' => $error['trace'],
                    ]);
                    if(env('environment') == 'pro'){
                        HiLogger::getInstance('TaskException')->alert($title .', ' . $error['ex_msg'], [
                            'md5' => md5(json_encode([$error['ex_msg'], $error['ex_file'], $error['ex_source']])),
                            'ex_file' => $error['ex_file'],
                            'ex_source' => $error['ex_source'],
                            'trace' => $error['format_trace'],
                        ]);
                    }
                    break;
                }

            }
            if($successful){
                echo sprintf('successful %s%s', date('Y-m-d H:i:s'), PHP_EOL);
            }
            else{
                echo '关闭服务...' . PHP_EOL;
                $server->shutdown();
            }

            //部署计划任务
            /*$crontabs = config('crontab');
            foreach ($crontabs as $crontab){
                if(is_numeric($crontab['condition'])){
                    swoole_timer_tick($crontab['condition'], function ($timer_id) use($crontab){
                        //取出当前多个实例的ip和端口值, 并选出最小值, 以确保只有一个实例执行任务
                        //$lock = lock('lock_'. env('service_name'). md5(json_encode($crontab)));
                        $services = $this->getConsulServices();
                        $ips  = [];
                        foreach ($services as $v){
                            $ip = str_replace(['.', ':'], '', $v['Service']['Address'].$v['Service']['Port']);
                            if(is_numeric($ip)){
                                $ips[] = $ip;
                            }
                        }
                        $local = str_replace(['.', ':'], '', explode(',', env('service_register_host'))[0] . env('service_port'));
                        if($local == min($ips)){
                            $this->callUserFunc($crontab['callback']);
                        }
                        //unlock($lock);
                    });
                }
                else{
                    swoole_timer_tick(1000, function ($timer_id) use ($crontab){
                        //$lock = lock('lock_'. env('service_name'). md5(json_encode($crontab)));
                        //取出当前多个实例的ip和端口值, 并选出最小值, 以确保只有一个实例执行任务
                        $services = $this->getConsulServices();
                        $ips  = [];
                        foreach ($services as $v){
                            $ip = str_replace(['.', ':'], '', $v['Service']['Address'].$v['Service']['Port']);
                            if(is_numeric($ip)){
                                $ips[] = $ip;
                            }
                        }
                        $local = str_replace(['.', ':'], '', explode(',', env('service_register_host'))[0] . env('service_port'));
                        if($local == min($ips)){
                            if(call_user_func($crontab['condition']) === true){
                                $this->callUserFunc($crontab['callback']);
                            }
                        }
                        //unlock($lock);
                    });
                }
            }*/
        }
        parent::onWorkerStart($server, $worker_id);
        Router::clearCache();
        require _APP_PATH_ ."/config.php";
    }
    
    protected function callUserFunc($callback){
        try{
            call_user_func($callback);
        }catch (\Throwable $e){
            $error = error_report($e, true);
            \One\Facades\Log::alert([
                'title' => '定时器回调函数发生错误',
                'ex_msg' => $error['ex_msg'],
                'ex_file' => $error['ex_file'],
                'trace' => $error['trace'],
            ]);
            if(env('environment') == 'pro'){
                HiLogger::getInstance('TaskException')->alert('回调函数发生错误,'. $error['ex_msg'], [
                    'md5' => md5(json_encode([$error['ex_msg'], $error['ex_file'], $error['ex_source']])),
                    'ex_file' => $error['ex_file'],
                    'ex_source' => $error['ex_source'],
                    'trace' => $error['format_trace'],
                ]);
            }
        }
    }

    public function onShutdown(\swoole_server $server)
    {
        parent::onShutdown($server);
        if($this->consul_service_info){
            $opts = array('http' => array('method'  => 'PUT', 'timeout' => 3));
            $context  = stream_context_create($opts);
            foreach ($this->consul_service_info as $info){
                try{
                    $url = sprintf('http://%s:%s/v1/agent/service/deregister/%s', env('consul_host'), env('consul_port'), $info['service_id']);
                    file_get_contents($url, false, $context);
                }catch (\Throwable $e){
                    echo $e->getMessage(). PHP_EOL;
                }
            }
        }
        $logfile = _APP_PATH_ .'/../console.log' ;
        if(is_file($logfile)){
            $con = file_get_contents($logfile);
            if(strlen($con) > 1024*1024*1024){
                file_put_contents($logfile, substr($con, -1024) . "\n flush " . date('Y-m-d H:i:s'));
            }
        }
    }

    public function onTask(\swoole_server $server, \Swoole\Server\Task $task)
    {
        try{
            Log::setTraceId($task->data['rid']);
            $action = $task->data['action'];
            $data = $task->data['data'];
            if($action == 'log'){
                $type = $task->data['type'] ?? 'alert';
                if(isset($data['ex_source'])){
                    \One\Facades\Log::$type([
                        'ex_msg' => $data['ex_msg'],
                        'ex_file' => $data['ex_file'],
                        'trace' => $data['trace'],
                        'received_data' => $data['received_data'],
                        'run_time' => $data['run_time'] ?? '_',
                    ]);
                    if(env('environment') == 'pro'){
                        HiLogger::getInstance('SystemExceptionTask')->$type($data['ex_msg'], [
                            'md5' => md5(json_encode([$data['ex_msg'], $data['ex_file'], $data['ex_source']])),
                            'ex_file' => $data['ex_file'],
                            'ex_source' => $data['ex_source'],
                            'trace' => $data['format_trace'],
                            'received_data' => explode("\r\n", $data['received_data']),
                            'run_time' => $data['run_time'] ?? '_',
                        ]);
                    }
                }
                else{
                    \One\Facades\Log::$type(...$data);
                }
            }
            elseif ($action == 'mail_log'){
                $data = $task->data['data'];
                HiLogger::getInstance('MailLogTask')->info($data['title'], ['content' => json_encode($data['content'],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES |
                    JSON_UNESCAPED_UNICODE)]);
            }
        }catch (\Throwable $e){
            $error = error_report($e, true);
            $data = json_encode($task->data);
            \One\Facades\Log::alert([
                'title' => '任务处理失败',
                'ex_msg' => $error['ex_msg'],
                'ex_file' => $error['ex_file'],
                'trace' => $error['trace'],
                'data' => $data
            ]);
            if(env('environment') == 'pro'){
                HiLogger::getInstance('TaskException')->alert('任务处理失败,'. $error['ex_msg'], [
                    'md5' => md5(json_encode([$error['ex_msg'], $error['ex_file'], $error['ex_source']])),
                    'ex_file' => $error['ex_file'],
                    'ex_source' => $error['ex_source'],
                    'trace' => $error['format_trace'],
                    'data' => $data,
                ]);
            }
        }
    }

    protected function getConsulServices(){
        $service_name = env('service_name');
        $consul_host = env('consul_host');
        $consul_port = env('consul_port');
        $result = null;
        try{
            $result = unserialize(cache("consul_http_{$service_name}"));
        }catch (\Throwable $e){

        }
        if(!$result){
            $i = 10000;
            while($i--){
                try{
                    $url = "http://$consul_host:$consul_port/v1/health/service/$service_name?passing=1&tag=http";
                    $result = file_get_contents($url);
                    $result = json_decode($result, true);
                }catch (\Exception $e){
                    if(!$i){
                        throw new AppException("The service $service_name is unavailable,". $e->getMessage());
                    }
                }
                if($result){
                    break;
                }
                if(!$i){
                    throw new AppException("The service $service_name is unavailable");
                }
            }
            try{
                cache("consul_http_{$service_name}", serialize($result), 600);
            }catch (\Throwable $e){

            }
        }
        return $result;
    }
}