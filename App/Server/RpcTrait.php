<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/12/6
 * Time: 17:32
 */

namespace App\Server;

use App\Utility\exception\AppException;
use App\Utility\exception\RequestException;
use App\Utility\exception\RpcUserException;
use One\Facades\Log;
use One\Swoole\RpcServer;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Exceptions\ValidationException;

trait RpcTrait
{
    //写入异常日志
    protected function logException(\Throwable $e, $type, $server, $data){
        $error = error_report($e, true);
        $msg = "{$error['ex_msg']} in {$error['ex_file']}";
        if($e instanceof ValidationException){
            $msg = $e->getMessage();
            if($e instanceof NestedValidationException){
                $msg = implode(', ', $e->getMessages());
            }
            $error['ex_msg'] = "$msg [Call to {$data['c']}:{$data['f']}()]";
        }
        $extra = ['received_data' => $data];
        $extra['run_time'] = mstime() - $server->run_time_start;
        $server->task(['action' => 'log', 'type' => $type, 'rid' => Log::getTraceId(), 'data' => array_merge($extra, $error)]);
        return $msg;
    }

    protected function retExceptionResult($msg, $code){
        //msgpack
        return json_encode([
            'err' => $code,
            'id' => Log::getTraceId(),
            'msg' => $msg
        ]);
    }

    private function callRpc($data, $ide = 0, $host = '', $px = '')
    {
        try {
            $arr = json_decode($data,true); //msgpack
            if (isset($arr['c'])) {
                $go_id = Log::setTraceId($arr['i'] . '.' . uuid());
                $str   = json_encode(RpcServer::call($arr, $this->server)); //msgpack
                Log::flushTraceId($go_id);
            } else if ($ide === 1) {
                $str = RpcServer::ideHelper($host, $px);
            } else {
                $str = json_encode('params error'); //msgpack
            }
            return $str;
        } catch (RpcUserException $e){
            return $this->retExceptionResult($e->getMessage(), $e->getCode() > 599 ? $e->getCode() : 600);
        } catch (AppException | ValidationException | RequestException $e) {
            $msg = $this->logException($e, 'error', $this->server, $data);
            $code = $e->getCode();
            if($e instanceof ValidationException){
                $code = $code > 699 ? $code : 700;
            }
            return $this->retExceptionResult($msg, $code);
        } catch (\Throwable $e) {
            $msg = $this->logException($e, 'alert', $this->server, $data);
            return $this->retExceptionResult($msg, $e->getCode());
        }

    }
}