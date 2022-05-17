<?php

namespace App\Controllers\Operate;

use App\Controllers\BasicController;
use App\Model\ProjectModel;
use App\Model\TopicModel;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;

class TopicAdminController extends BasicController
{
    /**
     * @var TopicModel
     */
    protected $model;

    public function __construct($request, $response, $server = null)
    {
        parent::__construct($request, $response, $server);
        $this->model = new TopicModel();
    }

    public function create(){
        v::arrayVal()
            ->key('title', v::notEmpty())
            ->key('summary', v::notEmpty())
            ->key('yin_title', v::notEmpty())
            ->key('yang_title', v::notEmpty())
            ->key('yin_votes', v::floatVal(), false)
            ->key('yang_votes', v::floatVal(), false)
            ->key('is_enabled', v::boolVal(), false)
            ->assert($this->post);
        $this->post['participants']=$this->post['yin_votes']+$this->post['yang_votes'];
        $this->model->createOne($this->post);
        $this->response->respond_data['info'] = $this->model->getSavedData();
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
            ->key('title', v::notEmpty(),false)
            ->key('summary', v::notEmpty(),false)
            ->key('yin_title', v::notEmpty(),false)
            ->key('yang_title', v::notEmpty(),false)
            ->key('yin_votes', v::floatVal(), false)
            ->key('yang_votes', v::floatVal(), false)
            ->key('is_enabled', v::boolVal(), false)
            ->assert($this->post);
        if(!empty($this->post['yin_votes']) || !empty($this->post['yang_votes'])){
            $this->post['participants']=$this->post['yin_votes']+$this->post['yang_votes'];
        }
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
        $info->project_list = [];
        foreach ($info['project_uuid_list'] as $v){
            $project = ProjectModel::getByUuid($v);
            if($project){
                $info->project_list[] = $project;
            }
        }
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
            $list = $this->model->getFilterList($filter, min(100, $page), min(100, $limit));
            foreach ($list as $k => $v){
                $v->formatFields();
            }

        }
        $this->response->respond_data['list'] = $list;
        $this->response->respond_data['total'] = $total;
        $this->response->respond_data['page_total'] = $page_total;
        return $this->ok();
    }

}




