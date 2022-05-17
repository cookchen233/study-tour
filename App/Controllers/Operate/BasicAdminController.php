<?php

namespace App\Controllers\Operate;

use App\Controllers\BasicController;
use App\Model\ConfigModel;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;

class BasicAdminController extends BasicController
{
    protected $admin;

    public function __construct($request, $response, $server = null)
    {
        parent::__construct($request, $response, $server);
        $admin = $this->session()->get('admin_info');
        $this->admin = $admin;
        $this->admin['name'] = $admin['name_cn'];
    }

    protected function info(){
        $info = null;
        v::arrayVal()
            ->key('uuid', v::findCallback(function ($val) use(&$info){
                $info = $this->model::where('uuid', $val)->find();
                return $info;
            }))
            ->assert($this->get);
        $this->response->respond_data['info'] = $info;
        return $this->ok();
    }

    public function index(){
        v::arrayVal()
            ->key('keywords', v::notEmpty(), false)
            ->assert($this->get);
        $limit = $this->get['limit'] ?? 7;
        $page = $this->get['page'] ?? 1;
        $page_total = 0;
        $list = [];
        $filter = $this->get;
        $total = $this->model->getFilterTotal($filter);
        if($total > 0){
            $page_total = ceil($total/$limit);
            $page = min($page, $page_total);
            $list = $this->model->getFilterList($filter, min(100, $page), min(100, $limit), $this->get['sort']??'');
            foreach ($list as $k => $v){
                $v->formatFields();
            }
        }
        $this->response->respond_data['list'] = $list;
        $this->response->respond_data['total'] = $total;
        $this->response->respond_data['page_total'] = $page_total;
        return $this->ok();
    }

    protected function updateConifg(){
        v::arrayVal()
            ->key('key_value', v::arrayVal()->length(1))
            ->key('category', v::notEmpty())
            ->assert($this->post);
        $config_model=new ConfigModel();
        foreach ($this->post['key_value'] as $k=>$v){
            $config=$config_model->where(['category'=>$this->post['category'],'key'=>$k])->find();
            if($config){
                $config_model->updateOne(['value'=>$v], ['uuid'=>$config['uuid']]);
            }
            else{
                $config_model->createOne([
                    'key'=>$k,
                    'value'=>$v,
                    'category'=>$this->post['category'],
                ]);
            }
        }
        return $this->ok();
    }

}




