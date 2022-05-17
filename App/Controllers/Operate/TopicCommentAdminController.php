<?php

namespace App\Controllers\Operate;

use App\Controllers\BasicController;
use App\Model\TopicCommentModel;
use App\Model\TopicModel;
use App\Model\UserModel;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;

class TopicCommentAdminController extends BasicController
{
    /**
     * @var TopicCommentModel
     */
    protected $model;

    public function __construct($request, $response, $server = null)
    {
        parent::__construct($request, $response, $server);
        $this->model = new TopicCommentModel();
    }

    public function create(){
        v::arrayVal()
            ->key('user_id', v::findCallback(function ($val){
                return UserModel::where('f_id', $val)->find();
            }))
            ->key('topic_uuid', v::findCallback(function ($val){
                return TopicModel::where('uuid', $val)->find();
            }))
            ->key('content', v::notEmpty())
            ->key('likes', v::floatVal(), false)
            ->key('is_enabled', v::boolVal(), false)
            ->assert($this->post);
        $this->model->createOne($this->post);
        //更新话题评论数
        TopicModel::where(['uuid'=>$this->post['topic_uuid']])->update(['comments'=>['comments+'. (int)$this->post['is_enabled']]]);
        $this->response->respond_data['info'] = $this->model->getSavedData();
        return $this->ok();
    }

    public function update(){
        if($this->get['action'] == 'info'){
            return $this->info();
        }
        $info = null;
        v::arrayVal()
            ->key('uuid', v::findCallback(function ($val) use (&$info){
                $info=$this->model::where('uuid', $val)->find();
                return $info;
            }))
            ->key('user_id', v::findCallback(function ($val){
                return UserModel::where('f_id', $val)->find();
            }),false)
            ->key('topic_uuid', v::findCallback(function ($val){
                return TopicModel::where('uuid', $val)->find();
            }),false)
            ->key('content', v::notEmpty(),false)
            ->key('likes', v::floatVal(), false)
            ->key('is_enabled', v::boolVal(), false)
            ->assert($this->post);
        $this->model->updateOne($this->post, ['uuid' => $this->post['uuid']]);
        //更新话题评论数
        TopicModel::where(['uuid'=>$info['topic_uuid']])->update(['comments'=>['comments+'. (int)($this->post['is_enabled'] - $info['is_enabled'])]]);
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
        if($this->get['action'] =='userList'){
            $filter = $this->get;
            $filter['type']='internal';
            $list=(new UserModel())->getFilterList($filter,1,200,'f_id desc');
            $this->response->respond_data['list'] = $list;
            return $this->ok();
        }
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

    public function delete(){
        v::arrayVal()
            ->key('uuid', v::findCallback(function ($val) use(&$info){
                return $this->model::where('uuid', $val)->find();
            }))
            ->assert($this->post);
        $this->model->where('uuid', $this->post['uuid'])->delete();
        TopicModel::where(['uuid'=>$this->post['topic_uuid']])->update(['comments'=>['comments-1']]);
        return $this->ok();
    }

}




