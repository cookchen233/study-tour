<?php

namespace App\Controllers\Operate;

use App\Controllers\BasicController;
use App\Model\PageConfigModel;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;

class PageConfigAdminController extends BasicController
{
    /**
     * @var PageConfigModel
     */
    protected $model;

    public function __construct($request, $response, $server = null)
    {
        parent::__construct($request, $response, $server);
        $this->model = new PageConfigModel();
    }

    public function create(){
        v::arrayVal()
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
        v::arrayVal()
            ->key('uuid', v::findCallback(function ($val){
                return $this->model::where('uuid', $val)->find();
            }))
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
            $list = $this->model->getFilterList($filter, min(100, $page), min(100, $limit), 'sort asc');
            foreach ($list as $k => $v){
                $v->formatFields();
            }

        }
        $this->response->respond_data['list'] = $list;
        $this->response->respond_data['total'] = $total;
        $this->response->respond_data['page_total'] = $page_total;
        return $this->ok();
    }

    public function banner(){
        return $this->index();
    }
    public function pcBanner(){
        return $this->index();
    }
    public function icon(){
        return $this->index();
    }
    public function hotProject(){
        return $this->index();
    }
    public function pcHotProject(){
        return $this->index();
    }
    public function story(){
        return $this->index();
    }
    public function hotTopic(){
        return $this->index();
    }
    public function recommendSchemePop(){
        return $this->index();
    }
    public function storyListProject(){
        return $this->index();
    }

}




