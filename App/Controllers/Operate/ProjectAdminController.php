<?php

namespace App\Controllers\Operate;

use App\Controllers\BasicController;
use App\Model\ProjectModel;
use App\Model\ProjectSortModel;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;

class ProjectAdminController extends BasicController
{
    /**
     * @var ProjectModel
     */
    protected $model;

    public function __construct($request, $response, $server = null)
    {
        parent::__construct($request, $response, $server);
        $this->model = new ProjectModel();
    }

    public function create(){
        v::arrayVal()
            ->key('country', v::in($this->model::COUNTRY))
            ->key('title', v::notEmpty())
            ->key('summary', v::notEmpty())
            ->key('content', v::notEmpty())
            ->key('picture', v::notEmpty())
            ->key('state', v::in(array_keys($this->model::STATE)))
            ->key('price', v::floatVal())
            ->key('is_selection', v::boolVal(), false)
            ->key('is_enabled', v::boolVal(), false)
            ->assert($this->post);
        $this->model->createOne($this->post);
        $this->response->respond_data['info'] = $this->model->getSavedData();
        $country=$this->model::COUNTRY;
        $country[]='';
        foreach($country as $v){
            (new ProjectSortModel())->createOne([
                'query' => json_encode(['country'=>$v]),
                'project_uuid'=>$this->response->respond_data['info']['uuid']
            ]);
        }
        return $this->ok();
    }

    public function update(){
        if($this->get['action'] == 'info'){
            return $this->info();
        }
        v::arrayVal()
            ->key('uuid', v::findCallback(function ($val){
                return $this->model::where('uuid', $val)->find();
            }))
            ->key('country', v::in($this->model::COUNTRY),false)
            ->key('title', v::notEmpty(), false)
            ->key('summary', v::notEmpty(), false)
            ->key('content', v::notEmpty(), false)
            ->key('picture', v::notEmpty(), false)
            ->key('state', v::in(array_keys($this->model::STATE)), false)
            ->key('price', v::floatVal(), false)
            ->key('is_selection', v::boolVal(), false)
            ->key('is_enabled', v::boolVal(), false)
            ->assert($this->post);
        $this->model->updateOne($this->post, ['uuid' => $this->post['uuid']]);
        $this->response->respond_data['info'] = $this->model->getSavedData();
        return $this->ok();
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
            ->key('country', v::in($this->model::COUNTRY), false)
            ->key('state', v::in(array_keys($this->model::STATE)), false)
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
            //$list = ProjectSortModel::sort($list, ['country'=> $this->get['country'] ?? '']);
        }
        $this->response->respond_data['list'] = $list;
        $this->response->respond_data['total'] = $total;
        $this->response->respond_data['page_total'] = $page_total;
        $this->response->respond_data['country'] = $this->model::COUNTRY;
        $this->response->respond_data['state'] = $this->model::STATE;
        return $this->ok();
    }

    public function updateSort(){
        v::arrayVal()
            ->key('uuid_list', v::arrayVal())
            ->key('query', v::arrayVal(), false)
            ->assert($this->post);
        $sort_model = new ProjectSortModel();
        ksort($this->post['query']);
        $query = json_encode($this->post['query']);
        $sort_model->where(['query' => $query])->delete();
        foreach ($this->post['uuid_list'] as $k=>$v){
            $sort_model->createOne(['query' => $query, 'project_uuid' => $v, 'sort'=>$k]);
        }
        return $this->ok();
    }

}




