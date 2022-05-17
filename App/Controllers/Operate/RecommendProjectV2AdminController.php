<?php

namespace App\Controllers\Operate;

use App\Controllers\BasicController;
use App\Model\ConfigModel;
use App\Model\ProjectModel;
use App\Model\RecommendProjectModel;
use App\Model\RecommendProjectV2Model;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;

class RecommendProjectV2AdminController extends BasicAdminController
{
    /**
     * @var RecommendProjectV2Model
     */
    protected $model;

    public function __construct($request, $response, $server = null)
    {
        parent::__construct($request, $response, $server);
        $this->model = new RecommendProjectV2Model();
    }

    public function create(){
        $question_config_kv = ConfigModel::getQuestionConfigKv();
        v::arrayVal()
            ->key('type', v::arrayVal()->each(v::in(array_keys($question_config_kv['type']['options']))))
            ->key('activity', v::arrayVal()->each(v::in(array_keys($question_config_kv['activity']['options']))))
            ->key('purpose', v::arrayVal()->each(v::in(array_keys($question_config_kv['purpose']['options']))))
            ->key('age_min',v::intVal()->min( 0))
            ->key('age_max',v::intVal()->max(170))
            ->keyValue('age_min', 'max', 'age_max')
            ->key('project_uuid', v::findCallback(function ($val){
                return ProjectModel::where('uuid', $val)->find();
            }))
            ->key('is_enabled', v::boolVal(), false)
            ->assert($this->post);
        $this->model->createOne($this->post);
        $this->response->respond_data['info'] = $this->model->getSavedData();
        return $this->ok();
    }

    public function update(){
        if($this->get['action'] == 'info'){
            return $this->info();
        }
        elseif ($this->get['action'] == 'config'){
            try{
                $questions=json_decode($this->post['key_value']['questions'] ,true);
                v::arrayVal()->length(5)->each(v::arrayVal()
                    ->key('title', v::notEmpty())
                    ->key('options', v::arrayVal()->length(1))
                    ->key('key', v::in(['country', 'age', 'purpose', 'type', 'activity']))
                    ->when(v::key('key', v::equals('country')), v::key('options', v::each(v::in(ProjectModel::COUNTRY))), v::alwaysValid())
                    ->key('maxselect', v::intVal()->min(0))
                    ->key('column', v::intVal()->min(1))
                )->assert($questions);
            } catch (ValidationException $e){
                $msg = $e->getMessage();
                if($e instanceof NestedValidationException){
                    $msgs=$e->getMessages();
                    if(count($msgs) > 1){
                        array_shift($msgs);
                    }
                    $msg = implode(', ', $msgs);
                }
                return $this->fail('配置数据错误, 请检查: '.$msg);
            }
            return $this->updateConifg();
        }

        $question_config_kv = ConfigModel::getQuestionConfigKv();
        v::arrayVal()
            ->key('uuid', v::findCallback(function ($val){
                return $this->model::where('uuid', $val)->find();
            }))
            ->key('type', v::arrayVal()->each(v::in(array_keys($question_config_kv['type']['options']))), false)
            ->key('activity', v::arrayVal()->each(v::in(array_keys($question_config_kv['activity']['options']))), false)
            ->key('purpose', v::arrayVal()->each(v::in(array_keys($question_config_kv['purpose']['options']))), false)
            ->key('age_min',v::intVal()->min( 0), false)
            ->key('age_max',v::intVal()->max(170), false)
            ->key('project_uuid', v::findCallback(function ($val){
                return ProjectModel::where('uuid', $val)->find();
            }), false)
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
            ->key('country', v::in(ProjectModel::COUNTRY),false)
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
        $this->response->respond_data['country'] = ProjectModel::COUNTRY;
        $this->response->respond_data['config'] = ConfigModel::getConfig('recommend_project');
        $this->response->respond_data['question_config_kv'] = ConfigModel::getQuestionConfigKv();
        return $this->ok();
    }

}




