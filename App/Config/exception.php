<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/4/10
 * Time: 14:41
 * 分布式给每个服务取个名字 不能重复 不能包含 @ 字符
 * 在client里面需要配置这个名字的相应ip信息
 */

return [
    'render' => function(\One\Exceptions\HttpException $e){
        $code = $e->getCode();
        if ($code === 0) {
            $code = 500;
        }
        $e->response->code($code);

        $msg = sprintf("%s in %s:%s ", str_replace('/var/www/one-app/vendor/hinabian/', '', $e->getMessage()), $e->getFile(), $e->getLine());

        if ($e->response->getHttpRequest()->isJson()) {
            return $e->response->json($msg, $code);
        } else {
            $file = _APP_PATH_VIEW_ . '/exceptions/' . $code . '.php';
            if (file_exists($file)) {
                return $e->response->tpl('exceptions/' . $code, ['e' => $e]);
            } else {
                return $e->response->json($msg, $code);
            }
        }
    }
];