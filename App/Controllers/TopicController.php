<?php

namespace App\Controllers;

use App\Model\ProjectModel;
use App\Model\TopicCommentLikeModel;
use App\Model\TopicCommentModel;
use App\Model\TopicModel;
use App\Model\TopicVoteModel;
use App\Model\UserKeyOpModel;
use App\Model\UserModel;
use App\Utility\exception\RequestException;
use App\Utility\Sms;
use App\Utility\exception\AppException;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;

class TopicController extends BasicController
{
    /**
     * @var TopicModel
     */
    protected $model;

    public function __construct($request, $response, $server = null)
    {
        parent::__construct($request, $response, $server);
        $this->model = new TopicModel();
        $this->setLogin();
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
                $voted=TopicVoteModel::where(['topic_uuid'=>$v['uuid'],'user_id'=>$this->user_info['f_id']])->find();
                $v['i_voted']=$voted ? $voted['yin_yang'] : '';
            }
        }
        $this->response->respond_data['list'] = $list;
        $this->response->respond_data['total'] = $total;
        $this->response->respond_data['page_total'] = $page_total;
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
        $voted=TopicVoteModel::where(['topic_uuid'=>$info['uuid'],'user_id'=>$this->user_info['f_id']])->find();
        $info['i_voted']=$voted ? $voted['yin_yang'] : '';
        $info->formatFields();
        $project_list = ProjectModel::column('uuid,title,picture,summary,price')->where(['is_enabled' => 1])->whereIn('uuid', $info->project_uuid_list)->findAll();
        foreach ($project_list as $v){
            $v->formatFields();
        }
        $this->response->respond_data['info'] = $info;
        $this->response->respond_data['project_list'] = $project_list;
        return $this->ok();
    }

    public function commentList(){
        v::arrayVal()
            ->key('topic_uuid', v::findCallback(function ($val){
                return $this->model->where('uuid', $val)->find();
            }))
            ->assert($this->get);
        $comment_model=new TopicCommentModel();
        $limit = $this->get['limit'] ?? 7;
        $page = $this->get['page'] ?? 1;
        $page_total = 0;
        $list = [];
        $filter = $this->get;
        $filter['is_enabled'] = 1;
        $total = $comment_model->getFilterTotal($filter);
        if($total > 0){
            $page_total = ceil($total/$limit);
            $page = min($page, $page_total);
            $list = $comment_model->getFilterList($filter, min(100, $page), min(100, $limit));
            foreach ($list as $k => $v){
                $v->formatFields();
                $liked=TopicCommentLikeModel::where(['topic_comment_uuid'=>$v['uuid'],'user_id'=>$this->user_info['f_id']])->find();
                $v['i_liked']=$liked ? 1 : 0;
            }

        }
        $this->response->respond_data['list'] = $list;
        $this->response->respond_data['total'] = $total;
        $this->response->respond_data['page_total'] = $page_total;
        return $this->ok();
    }

    public function postComment(){
        if(!$this->setLogin()){
            return $this->fail('请先登录', 'no_login');
        }
        $topic = null;
        v::arrayVal()
            ->key('topic_uuid', v::findCallback(function ($val) use (&$topic) {
                $topic = $this->model->where('uuid', $val)->find();
                return $topic;
            }))
            ->key('content', v::notEmpty())
            ->assert($this->post);
        $source_data = $this->validSource('comment');
        $source_data['content'] = <<<eot
话题评论 
话题: {$topic['title']}
评论: {$this->post['content']} 
eot;
        $this->user_center->entryCRM($this->user_info['mobile'], $source_data, ['name' => $this->user_info['name'] ?: $this->user_info['nickname']]);
        $comment_model=new TopicCommentModel();
        $this->post['user_id']=$this->user_info['f_id'];
        $comment_model->createOne($this->post);
        $this->response->respond_data['info'] = $comment_model->getSavedData();
        return $this->ok();

    }

    public function likeComment(){
        if(!$this->setLogin()){
            return $this->fail('请先登录', 'no_login');
        }
        v::arrayVal()
            ->key('topic_comment_uuid', v::findCallback(function ($val){
                return TopicCommentModel::where('uuid', $val)->find();
            }))
            ->assert($this->post);
        $like_model=new TopicCommentLikeModel();
        if($like_model->where(['topic_comment_uuid'=>$this->post['topic_comment_uuid'], 'user_id'=>$this->user_info['f_id']])->find()){
            return $this->fail('已赞过', 'liked');
        }
        $this->post['user_id']=$this->user_info['f_id'];
        $like_model->createOne($this->post);
        TopicCommentModel::where(['uuid'=>$this->post['topic_comment_uuid']])->update(['likes'=>['likes+1']]);
        $this->response->respond_data['info'] = $like_model->getSavedData();
        return $this->ok();
    }

    public function vote(){
        if(!$this->setLogin()){
            return $this->fail('请先登录', 'no_login');
        }
        $info=null;
        v::arrayVal()
            ->key('topic_uuid', v::findCallback(function ($val) use (&$info) {
                $info = $this->model->where('uuid', $val)->find();
                return $info;
            }))
            ->key('yin_yang', v::notEmpty())
            ->assert($this->post);
        if(!in_array($this->post['yin_yang'],[$info['yin_title'],$info['yang_title']])){
            return $this->fail('投票选项"yin_yang"有误,应为"'.$info['yin_title'].'"或"'.$info['yang_title'].'"');
        }
        $vote_model=new TopicVoteModel();
        if($vote_model->where(['topic_uuid'=>$this->post['topic_uuid'], 'user_id'=>$this->user_info['f_id']])->find()){
            return $this->fail('已投票', 'voted');
        }
        $this->post['user_id']=$this->user_info['f_id'];
        $vote_model->createOne($this->post);
        $yin_yang=array_search($this->post['yin_yang'], ['yin'=>$info['yin_title'], 'yang'=>$info['yang_title']]);
        TopicModel::where(['uuid'=>$this->post['topic_uuid']])->update(['participants'=>['participants+1'],$yin_yang.'_votes'=>[$yin_yang.'_votes+1']]);
        $this->response->respond_data['info'] = $vote_model->getSavedData();
        return $this->ok();
    }
}




