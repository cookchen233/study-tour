<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/4/23
 * Time: 10:40
 */

namespace App\Rpc;

use App\Model\ProjectModel;
use App\Model\ProjectSortModel;
use App\Utility\WaitGroup;
use One\Swoole\RpcData;
use Respect\Validation\Validator as v;

/**
 * 游学项目
 */
class ProjectRpc extends BasicRpc
{

    /**
     * @var ProjectModel
     */
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ProjectModel();
    }


    /**
     * 获取总记录数
     * @param $filter,过滤条件
     * @return int
     */
    public function getTotal(array $filter = []){
        $total = $this->model->getFilterTotal($filter);
        return $total;
    }

    /**
     * 获取列表
     * @param $filter,过滤条件
     * @param int $page, //页码
     * @param int $limit //每页数量
     * @return array
     */
    public function getList(array $filter = [], $page = 1, $limit = 30, $sort = 'ps.sort asc, p.sys_id desc'){
        $list = $this->model->getFilterList($filter, $page, $limit, $sort);
        if($list){
            $list = $this->formatList($list);
        }
        return $list;
    }

    protected function formatList($list){
        $wg = new WaitGroup();
        foreach ($list as $k => $v) {
            $wg->add();
            one_go(function () use ($wg, &$v) {
                $v->formatFields();
                $wg->done();
            });
        }
        $wg->wait();
        return $list->toArray();
    }

    /**
     * 排序
     * @param $list 列表数据
     * @param $query 排序条件
     * @return array
     */
    public function sort($list, $query){
        return ProjectSortModel::sort($list, ['country'=> $query['country'] ?? '']);
    }


}

