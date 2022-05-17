<?php

namespace App\Controllers;

use App\Model\PageConfigModel;
use App\Model\ProjectModel;
use App\Model\StoryModel;
use App\Model\UserKeyOpModel;
use App\Model\UserModel;
use App\Utility\exception\RequestException;
use App\Utility\Sms;
use App\Utility\exception\AppException;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;

class StoryController extends BasicController
{
    /**
     * @var StoryModel
     */
    protected $model;

    public function __construct($request, $response, $server = null)
    {
        parent::__construct($request, $response, $server);
        $this->model = new StoryModel();
    }

    public function index(){
        $limit = $this->get['limit'] ?? 7;
        $page = $this->get['page'] ?? 1;
        $page_total = 0;
        $list = [];
        $filter = $this->get;
        $filter['is_enabled'] = 1;
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
        //推荐项目
        $project_list = (new PageConfigModel)->getFilterList(['is_enabled' => 1, 'correlation_is_enabled' => 1, 'location' => 'storyListProject'], 1, 30, 'sort asc');
        foreach ($project_list as $v){
            $v->formatFields();
        }
        $this->response->respond_data['storyListProject'] = $project_list;
        return $this->ok();
    }

    public function info()
    {
        $info = null;
        v::arrayVal()
            ->key('uuid', v::findCallback(function ($val) use (&$info) {
                $info = $this->model->where('uuid', $val)->find();
                return $info;
            }))
            ->assert($this->get);
        $this->model->where(['uuid'=>$this->get['uuid']])->update(['views'=>['views+1']]);
        $info->formatFields();
        $project_list = ProjectModel::column('uuid,title,picture,summary,price')->where(['is_enabled' => 1])->whereIn('uuid', $info->project_uuid_list)->findAll();
        foreach ($project_list as $v){
            $v->formatFields();
        }
        $this->response->respond_data['info'] = $info;
        $this->response->respond_data['project_list'] = $project_list;
        return $this->ok();
    }
}




