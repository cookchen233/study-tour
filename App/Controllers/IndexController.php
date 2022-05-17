<?php

namespace App\Controllers;

use App\Crontab\System;
use App\Utility\exception\AppException;
use Hisune\EchartsPHP\ECharts;
use One\Http\Controller;
use One\Swoole\RpcServer;
use OneRpcClient\Tcp\App\Rpc\StudyTour\ProjectRpc;
use PHPMailer\PHPMailer\PHPMailer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class IndexController extends Controller
{

    public function index(){
        return "It's OK";
    }

    /**
     * 服务检测
     * @param string $consul_service_id
     * @return string
     */
    public function checkService($consul_service_id = ""){
        if(isset($this->server->consul_service_info[$consul_service_id])){
            return "OK";
        }
        $this->response->code(400);
        return "No service $consul_service_id";
    }


    /**
     * rpc客户端类文件
     * @param string $type
     * @return string
     */
    public function rpcClientHelper($type = 'tcp')
    {
        if(!in_array(substr($this->request->ip(), 0, 3), ['127', '192', '172', '000', '111'])){
            return 'Forbidden';
        }
        $this->response->header('Content-type', 'text/plain;charset=utf-8');
        $px = "OneRpcClient\\". ucwords($type);
        $r = '';
        $func_count = 0;
        $class_count = 0;
        $service_name = trim(str_replace(' ', '', ucwords(str_replace('_', ' ', env('service_name')))));
        foreach (RpcServer::$class as $class => $fs) {
            $class_count++;
            $class = new \ReflectionClass($class);
            $class_name = str_replace($class->getNamespaceName() . '\\', '', $class->getName());
            $funcs = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
            // 增加类注释
            $doc = preg_replace(['/^\/\*\*/', '/\n.*?\*\//'], [""], $class->getDocComment());
            $r .= "\n/*". str_repeat('*', 103) . "*/\n\n";
            $r .= "namespace $px\\{$class->getNamespaceName()}\\$service_name {\n\n";
            $r .= str_repeat(' ', 3) . "/**\n";
            $r .= " * $class_name $doc \n";
            $methods = array_keys($fs);
            foreach ($funcs as $func) {
                if (!isset($fs['*']) && !in_array($func->name, $methods)) {
                    continue;
                }
                if(in_array($func->name, ['rollBack', 'commit', '__construct'])){
                    continue;
                }
                $func_count++;
                // 增加方法注释
                $doc = preg_replace(['/^\/\*\*/', '/\n.*?\*\//'], [""], $func->getDocComment());
                preg_match('/@return ([a-zA-Z\|]+).*? */', $doc, $return_match);
                $return = isset($return_match[1]) ? $return_match[1]:$func->getReturnType();
                $r .=  str_repeat('-', 78). "\n$doc\n\n" . str_repeat(' ', 4) . "* @method {$return} {$func->name}(";
                $params = [];
                foreach ($func->getParameters() as $param) {
                    if ($param->getType()) {
                        $params[] = $param->getType() . ' $' . $param->getName();
                    } else {
                        $params[] = '$' . $param->getName();
                    }
                }
                $r .= implode(', ', $params) . ")";
                if ($func->isStatic()) {
                    $r .= ' static';
                }
                $r .= "\n\n";
            }

            $r .= str_repeat('-', 78) . "\n\n";
            $r    .= str_repeat(' ', 4) . "*/\n";
            if ($type == 'http') {
                $r .= str_repeat(' ', 4) . "class {$class_name} extends \\OneRpcClient\\RpcClientHttp { \n";
            } else {
                $r .= str_repeat(' ', 4) . "class {$class_name} extends \\OneRpcClient\\RpcClientTcp { \n";
            }
            $secret = env('rpc_secret');
            $r .= str_repeat(' ', 8) . "protected \$secret = '{$secret}';\n";
            $r .= str_repeat(' ', 8) . "protected \$service_name = '". env('service_name') ."';\n";
            $r .= str_repeat(' ', 8) . "protected \$remote_class_name = '{$class->getName()}';\n";
            $r .= str_repeat(' ', 4) . "} \n";
            $r .= "} \n";
        }
        return "<?php\n/**** $class_count Classes, $func_count Methods ****/" . $r;
    }
}




