<?php

namespace App\Controllers\Operate;

use App\Controllers\BasicController;
use App\Model\OperateAdminModel;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;

class IndexAdminController extends BasicController
{

    /*public function unionLogin(){
        try{
            v::arrayVal()
                ->key('sign', v::notEmpty())
                ->key('admin_id', v::notEmpty())
                ->key('time', v::intVal())
                ->key('callback', v::notEmpty())
                ->assert($this->get);
        }catch (ValidationException $e){
            return;
        }
        if(!hash_equals($this->get['sign'], md5($this->get['admin_id'].$this->get['time'].'cmdi3k5sz2ewse23bmzejasjvj5he3fvj572d'))){
            return $this->fail('签名错误');
        }
        $this->session()->set('admin_info', OperateAdminModel::find($this->get['admin_id']));
        $menus = (new OperateAdminModel())->getPermissionMenus($this->get['admin_id']);
        $permissions = [];
        $admin_permissions = [];
        foreach ($menus as $v){
            $permissions[] = $v['module'];
            $admin_permissions[] = str_replace(strrchr($v['module'], '/'), '', $v['module']);
        }
        $this->session()->set('admin_permissions', $permissions);
        $this->session()->set('module_admin_permissions', $admin_permissions);
        return $this->response->redirect(base64_decode($this->get['callback']));
    }*/
    public function unionLogin(){
        try{
            v::arrayVal()
                ->key('ticket', v::notEmpty())
                ->key('redirect', v::notEmpty())
                ->assert($this->get);
        }catch (ValidationException $e){
            return;
        }
        $res_content = file_get_contents('https://operate.hinabian.com/index/sso/verify?ticket='.$this->get['ticket']);
        $ret = json_decode($res_content, true);
        if(empty($ret['data']['id'])){
            return $res_content;
        }

        $this->get['admin_id'] = $ret['data']['id'];
        $admin_info = OperateAdminModel::find($this->get['admin_id'])->toArray();
        $admin_info['ticket'] = $this->get['ticket'];
        $this->session()->set('admin_info', $admin_info);
        $menus = (new OperateAdminModel())->getPermissionMenus($this->get['admin_id']);
        $permissions = [];
        $admin_permissions = [];
        foreach ($menus as $v){
            $permissions[] = $v['module'];
            $admin_permissions[] = str_replace(strrchr($v['module'], '/'), '', $v['module']);
        }
        $this->session()->set('admin_permissions', $permissions);
        $this->session()->set('module_admin_permissions', $admin_permissions);
        //return $this->response->redirect(base64_decode($this->get['callback']));
        return $this->response->redirect($this->get['redirect']);
    }

    public function unionLogout(){
        try{
            v::arrayVal()
                ->key('callback', v::notEmpty())
                ->assert($this->get);
        }catch (ValidationException $e){
            return;
        }
        $this->session()->set('admin_info', null);
        $this->session()->set('admin_permissions', null);
        $this->session()->set('module_admin_permissions', null);
        return $this->response->redirect(base64_decode($this->get['callback']));
    }

    public function commonCss(){
        return $this->response->tpl('Operate/common.css', [], 'text/css;charset=utf-8');
    }

    public function tinymceUpload(){
        return $this->response->tpl('Operate/tinymceUpload.html');
    }


}




