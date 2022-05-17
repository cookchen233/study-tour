<?php

namespace App\Controllers;

use One\Http\Controller;

class TestController extends Controller
{
    public function weixinJsapi(){
        return $this->response->tpl('test/weixin_jsapi.html');
    }
}




