<?php

namespace App\Controllers;

use App\Model\ConfigModel;
use App\Model\ProjectModel;
use App\Model\ProjectSortModel;
use App\Model\RecommendProjectModel;
use App\Model\RecommendProjectV2Model;
use App\Model\RecommendSchemeModel;
use App\Model\UserKeyOpModel;
use App\Model\UserModel;
use App\Utility\exception\RequestException;
use App\Utility\Sms;
use App\Utility\exception\AppException;
use App\Utility\umeng\Umeng;
use Overtrue\Pinyin\Pinyin;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;

class ProjectController extends BasicController
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

    public function index(){
        $limit = $this->get['limit'] ?? 7;
        $page = $this->get['page'] ?? 1;
        $page_total = 0;
        $list = [];
        $filter = $this->get;
        $filter['is_enabled'] = 1;
        if(isset($filter['country']) && in_array($filter['country'], array_keys($this->model::KEY_COUNTRY))){
            $filter['country'] = $this->model::KEY_COUNTRY[$filter['country']];
        }
        $total = $this->model->getFilterTotal($filter);
        if($total > 0){
            $page_total = ceil($total/$limit);
            $page = min($page, $page_total);
            $list = $this->model->getFilterList($filter, min(100, $page), min(100, $limit), 'ps.sort asc,p.sys_id desc');
            foreach ($list as $k => $v){
                $v->formatFields();
            }
            //$list = ProjectSortModel::sort($list, ['country'=> $this->get['country'] ?? '']);
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
        $this->response->respond_data['info'] = $info->formatFields();
        return $this->ok();
    }

    public function country(){
        $this->response->respond_data['list'] = $this->model::COUNTRY;
        $this->response->respond_data['struct_list'] = $this->model::KEY_COUNTRY;
        return $this->ok();
    }

    public function validateCaptcha(){
        $this->user_center->verifyVcode($this->get['mobile'],$this->get['captcha']);
        return $this->ok();
    }

    public function recommendScheme(){
        v::arrayVal()
            ->key('name',v::notEmpty())
            ->key('address',v::notEmpty())
            ->key('age',v::intVal())
            ->key('country',v::in($this->model::COUNTRY))
            ->key('type',v::in(array_keys(RecommendProjectModel::TYPE)))
            ->key('qa',v::arrayVal()->length(4, null)->each(v::arrayVal()
                ->key('q', v::notEmpty())
                ->key('a', v::notEmpty())
            ))
            ->assert($this->post);
        $source_data = $this->validSource('assess');
        if(!$this->setLogin()){
            v::arrayVal()
                ->key('mobile',v::mobile())
                ->key('captcha',v::notEmpty())
                ->assert($this->post);
            if(!cache('had_recommendScheme_'.$this->post['mobile'])){
                if(!$this->validCaptcha($this->post['mobile'],$this->post['captcha'], $source_data)){
                    return $this->fail('验证码错误');
                }
            }
        }
        try{
            $this->model->beginTransaction();
            //创建用户
            /*$source_data = $this->getUserSourceData();
            $uid = (new UserModel())->createNxAndCrmUser([
                'name' => $this->post['name'],
                'mobile' => $this->post['mobile'],
            ], $source_data);
            //创建用户操作日志
            $log_data = $source_data;
            $qa = '';
            foreach ($this->post['qa'] as $k=>$v){
                $kk=$k+1;
                $qa .= <<<eot
{$v['q']} 答: {$v['a']}

eot;
            }
            $log_data['f_content'] = <<<eot
评测及获取推荐方案
称呼: {$this->post['name']}
地区: {$this->post['address']}
年龄: {$this->post['age']}
问答内容:
{$qa} 
eot;
            $log_data['f_uid'] = $this->user_info['f_id'];
            (new UserKeyOpModel())->createOne($log_data);*/
            $qa = '';
            foreach ($this->post['qa'] as $k=>$v){
                $kk=$k+1;
                $qa .= <<<eot
{$v['q']} 答: {$v['a']}

eot;
            }
            $source_data['content'] = <<<eot
评测及获取推荐方案
称呼: {$this->post['name']}
地区: {$this->post['address']}
年龄: {$this->post['age']}
问答内容:
{$qa} 
eot;
            $this->user_center->setUserInfo($this->post);
            $this->user_center->entryCRM($this->user_info['mobile'], $source_data, $this->post);

            $project_list=$this->model->from($this->model::TABLE . ' p')
                ->column('p.*')
                ->join('t_recommend_project rp', 'rp.project_uuid', 'p.uuid')
                ->where('rp.age_min', '<=', $this->post['age'])
                ->where('rp.age_max', '>=', $this->post['age'])
                ->where([
                    'rp.country'=>$this->post['country'],
                    'rp.is_enabled'=> 1,
                    'p.is_enabled'=> 1,
                ])
                ->whereRaw("locate({$this->post['type']},rp.type) > 0")
                ->findAll();
            foreach ($project_list as $v){
                $v->formatFields();
            }
            cache('had_recommendScheme_'.$this->post['mobile'], 1);
            $scheme=RecommendSchemeModel::where(['country'=>$this->post['country'], 'is_enabled'=>1])->find();
            if($scheme){
                $scheme->formatFields();
            }
            $this->response->respond_data['project_list'] = $project_list;
            $this->response->respond_data['scheme'] = $scheme;
            $this->model->commit();
            return $this->ok();
        }catch (\Throwable $e){
            $this->model->rollBack();
            throw $e;
        }
    }

    public function questions(){
        $this->response->respond_data['list'] = json_decode(ConfigModel::getConfig('recommend_project')['questions'], true);
        return $this->ok();
    }

    public function recommendSchemeV2(){
        $this->request->header = array_merge($this->post, $this->request->header);
        $this->validSource('assess');
        $this->validAnswers();
        if(!$this->setLogin()){
            v::arrayVal()
                ->when(
                    v::key('verifyId', v::notEmpty()),
                    v::key('token', v::notEmpty()),
                    v::key('mobile', v::mobile())->key('captcha', v::notEmpty())
                )
                ->assert($this->post);
            $source_data = $this->validSource('assess');
            if(!empty($this->post['mobile'])){
                if(!$this->validCaptcha($this->post['mobile'],$this->post['captcha'], $source_data)){
                    return $this->fail('验证码错误');
                }
            }
            else{
                $this->user_center->umengLogin($this->post['token'], $this->post['verifyId'], $source_data);
                $this->setLogin();
            }
        }

        try{
            $this->model->beginTransaction();
            //创建用户
            /*$source_data = $this->getUserSourceData();
            $uid = (new UserModel())->createNxAndCrmUser([
                'name' => $this->post['name'] ?? '',
                'mobile' => $this->post['mobile'],
            ], $source_data);*/
            $this->_recommendSchemeV2($this->user_info['f_id']);
            $this->model->commit();
            return $this->ok();
        }catch (\Throwable $e){
            $this->model->rollBack();
            throw $e;
        }
    }

    public function recommendSchemeV2Again(){
        $this->request->header = array_merge($this->post, $this->request->header);
        $this->validSource('assess');
        $this->validAnswers();
        $user = null;
        if(!$this->setLogin()){
            v::arrayVal()
                ->key('user_id', v::findCallback(function ($val) use (&$user) {
                    $user = UserModel::where('f_id', $val)->find();
                    return $user;
                }))
                ->assert($this->post);
        }
        else{
            $user = $this->user_info;
        }
        try{
            $this->_recommendSchemeV2($user['f_id']);
            $this->model->commit();
            return $this->ok();
        }catch (\Throwable $e){
            $this->model->rollBack();
            throw $e;
        }
    }

    protected function validAnswers(){
        if(isset($this->post['answers']) && !is_array($this->post['answers'])){//ios端问题,无法传送数组类型
            v::json()->assert($this->post['answers']);
            $this->post['answers'] = json_decode($this->post['answers'], true);
        }
        $question_config_kv = ConfigModel::getQuestionConfigKv();
        v::arrayVal()
            ->key('answers', v::arrayVal()
                ->key('country', v::arrayVal()->length(1, $question_config_kv['country']['maxselect'] ?: null )->each(v::in(array_keys($question_config_kv['country']['options']))))
                ->key('type', v::arrayVal()->length(1, $question_config_kv['type']['maxselect'] ?: null)->each(v::in(array_keys($question_config_kv['type']['options']))))
                ->key('activity', v::arrayVal()->length(1, $question_config_kv['activity']['maxselect'] ?: null)->each(v::in(array_keys($question_config_kv['activity']['options']))))
                ->key('purpose', v::arrayVal()->length(1,$question_config_kv['purpose']['maxselect'] ?: null)->each(v::in(array_keys($question_config_kv['purpose']['options']))))
                ->key('age', v::arrayVal()->length(1,$question_config_kv['age']['maxselect'] ?: null)->each(v::in(array_keys($question_config_kv['age']['options']))))
            )
            ->assert($this->post);
    }
    protected function _recommendSchemeV2($uid){
        $question_config_kv = ConfigModel::getQuestionConfigKv();

        $source_data = $this->validSource('assess');
        //创建用户操作日志
        /*$log_data = $source_data;
        $qa = '';
        foreach ($this->post['answers'] as $k => $v){
            $answer = implode('; ', array_intersect_key($question_config_kv[$k]['options'], $v));
            $qa .= <<<eot
{$question_config_kv[$k]['title']}
答: {$answer}

eot;
        }
        $name = !empty($this->post['name']) ? "称呼: {$this->post['name']}" : '';
        $log_data['f_content'] = <<<eot
评测及获取推荐方案V2
{$name}
问答内容:
{$qa}
eot;
        $log_data['f_uid'] = $uid;
        (new UserKeyOpModel())->createOne($log_data);*/
        $qa = '';
        foreach ($this->post['answers'] as $k => $v){
            $answer = implode('; ', array_intersect_key($question_config_kv[$k]['options'], array_flip($v)));
            $qa .= <<<eot
{$question_config_kv[$k]['title']}
答: {$answer}

eot;
        }
        $name = !empty($this->post['name']) ? "称呼: {$this->post['name']}" : '';
        $source_data['content'] = <<<eot
评测及获取推荐方案V2
{$name}
问答内容:
{$qa}
eot;
        $this->user_center->setUserInfo($this->post);
        $this->user_center->entryCRM($this->user_info['mobile'], $source_data, $this->post);


        //获取年龄配置键对应的最大与最小值
        $age=$question_config_kv['age']['options'][$this->post['answers']['age'][0]];
        preg_match_all('/\d+/', $age, $age_match);
        if(count($age_match[0]) < 2){
            $age_pattern=[0, $age_match[0][0]];
            if(strpos($age, '以上') || strpos($age, '+')){
                $age_pattern=[$age_match[0][0], 100];
            }
        }
        else{
            $age_pattern=$age_match[0];
        }


        $scheme=null;
        $project_list=RecommendProjectV2Model::from(RecommendProjectV2Model::TABLE . ' rp')
            ->column('rp.*,p.*')
            ->leftJoin('t_project p', 'rp.project_uuid', 'p.uuid')
            ->where([
                'rp.is_enabled'=> 1,
                'p.is_enabled'=> 1,
            ])
            ->findAll();
        foreach ($project_list as $v){
            $v->formatFields();

            //匹配国家交集, 若最终分值相同的两个项目,加0.1以实现国家优先
            $score=['country'=>0];
            $full_score = 50;
            if(array_intersect([array_search($v['country'], $question_config_kv['country']['options'])], $this->post['answers']['country'])){
                $score['country']=50.1;
            }

            $full_score += 100;
            //年龄区间子集匹配
            if($age_pattern[0] >= $v['age_min'] && $age_pattern[1] <= $v['age_max']){
                $score['age']=100;
            }//年龄区间交集匹配
            elseif($age_pattern[0] >= $v['age_min'] || $age_pattern[1] <= $v['age_max']){
                $score['age']=60;
            }


            //游学目的交集匹配, n(n>0)个交集得分为 10+(n-1)
            $full_score += 10 + (count($v['purpose']) - 1);
            $in_count=count(array_intersect($v['purpose'], $this->post['answers']['purpose']));
            $score ['purpose']= $in_count ? (10 + ($in_count - 1)) : 0;

            //游学类型交集匹配, n(n>0)个交集得分为 10+(n-1)
            $full_score += 10 + (count($v['type']) - 1);
            $in_count=count(array_intersect($v['type'], $this->post['answers']['type']));
            $score ['type']= $in_count ? (10 + ($in_count - 1)) : 0;

            //活动类型交集匹配, n(n>0)个交集得分为 10+(n-1)
            $full_score += 10 + (count($v['activity']) - 1);
            $in_count=count(array_intersect($v['activity'], $this->post['answers']['activity']));
            $score ['activity']= $in_count ? (10 + ($in_count - 1)) : 0;
            $v['score_info']=$score;
            $v['score']=array_sum($score);
            $v['full_score']=$full_score;
            $v['full_score']=$full_score;
            $v['match']= (int)bcmul(round((int)$v['score']/$full_score, 2), 100);
        }
        if($project_list){
            $project_list=sort2($project_list->toArray(), 'score');
            $project_list=array_slice($project_list, 0, 4);
            $scheme=RecommendSchemeModel::where(['country'=>$project_list[0]['country'], 'is_enabled'=>1])->find();
            if($scheme){
                $scheme->formatFields()->toArray();
            }
        }
        $this->response->respond_data['project_list'] = $project_list;
        $this->response->respond_data['scheme'] = $scheme;
        $this->response->respond_data['user_id'] = $uid;
        cache('hasRecommendedScehme_'.$uid, $this->response->respond_data, 86400*365);
    }

    public function hasRecommendedScehme(){
        if(!$this->setLogin()){
            return $this->fail('未评测', 'no_recommended');
        }
        $data = cache('hasRecommendedScehme_'.$this->user_info['f_id']);
        if(!$data){
            return $this->fail('未评测', 'no_recommended');
        }
        $this->response->respond_data=$data;
        return $this->ok();
    }
}




