<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/12/17
 * Time: 10:15
 * rpc中间件
 */

namespace App\Middleware;

use App\Utility\exception\AppException;
use App\Utility\exception\RequestException;
use App\Utility\exception\RpcUserException;
use One\Facades\Log;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Exceptions\ValidationException;

class RpcMiddleware
{

    //写入异常返回结果到调用日志
    protected function logException(\Throwable $e, $type, $server, $log_data, $data){
        $error = error_report($e, true);
        $msg = "{$error['ex_msg']} in {$error['ex_file']}";
        if($e instanceof ValidationException){
            $msg = $e->getMessage();
            if($e instanceof NestedValidationException){
                $msgs=$e->getMessages();
                if(count($msgs) > 1){
                    array_shift($msgs);
                }
                $msg = implode(', ', $msgs);
            }
        }
        $log_data['received_data'] = $data;
        $log_data['return'] = 'exception, ' . $msg;
        $log_data['run_time'] = mstime() - $server->run_time_start;
        $server->task(['action' => 'log', 'type' => $type, 'rid' => Log::getTraceId(), 'data' => [$log_data, 0, 'rpc_call']]);
    }

    public function logCall($next, $id, $ip, $token, $class, $method, $args, $ctor_args, $server, $data){
        $call = sprintf('%s::%s(%s)', $class, $method, implode(', ', array_map(function ($val){return var_export(cut_data($val, 0, 265), true);}, $args)));
        $log_data = ['ip' => $ip, 'call' => $call];
        try {
            $result = $next();
            $log_data['return'] = cut_data(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 620, true);
            $server->task(['action' => 'log', 'type' => 'debug', 'rid' => Log::getTraceId(), 'data' => [$log_data, 0, 'rpc_call']]);
            if(method_exists($result, 'toArray')){
                $result = $result->toArray();
            }
            return $result;
        }catch (AppException | ValidationException | RequestException | RpcUserException $e){
            $this->logException($e, 'error', $server,  $log_data, $data);
            throw $e;
        }catch (\Throwable $e){
            $this->logException($e, 'alert', $server, $log_data, $data);
            throw $e;
        }
    }

    public function checkSign($next, $id, $ip, $token, $class, $method, $args, $ctor_args, $server, $data){
        $token = explode('|', $token);
        $time = $token[1];
        $token = $token[0];
        $dt = time() - $time;
        if ($dt > 300 || $dt < -300){
            throw new RequestException('The token has expired');
        }
        $c_token = md5(env('rpc_secret') . json_encode([$id, $ip, $class, $method, $args, $ctor_args]) . $time);
        if(!hash_equals($c_token, $token)){
            Log::error(['token' => $c_token, 'data' => [$id, $ip, $class, $method, $args, $ctor_args]], 0, 'rpc_call');
            throw new RequestException('The token is invalid');
        }
        $result = $next();
        return $result;
    }
}
