<?php

namespace App\Model;

use App\Utility\WaitGroup;

class ProjectModel extends BasicModel
{
    CONST TABLE = 't_project';

    //状态
    CONST STATE = [
        100 => ['value' => 100, 'text' => '未开始'],
        200 => ['value' => 200, 'text' => '进行中'],
        300 => ['value' => 300, 'text' => '已结束'],
    ];

    //所属国家
    CONST COUNTRY = [
        '美国',
        '英国',
        '中国',
        '澳大利亚',
        '新加坡',
    ];
    //所属国家
    CONST KEY_COUNTRY = [
        'US' => '美国',
        'UK' => '英国',
        'CN' => '中国',
        'AU' => '澳大利亚',
        'SG' => '新加坡',
    ];

    protected function formatBeforeSave($model, & $data)
    {
        parent::formatBeforeSave($model, $data);
        if(isset($data['start_time']) && !is_numeric($data['start_time'])){
            $data['start_time'] = strtotime($data['start_time']);
        }
        if(isset($data['end_time']) && !is_numeric($data['end_time'])){
            $data['end_time'] = strtotime($data['end_time']);
        }
        if(isset($data['multi_content']) && is_array($data['multi_content'])){
            $data['multi_content'] = json_encode($data['multi_content']);
        }
        if(isset($data['picture_list']) && is_array($data['picture_list'])){
            $data['picture_list'] = json_encode($data['picture_list']);
        }
    }
    protected function formatAfterGet(& $result)
    {
        parent::formatAfterGet($result);
        if (isset($result['multi_content'])){
            $result['multi_content'] = json_decode($result['multi_content'], true) ?: [];
        }
        if (isset($result['picture_list'])){
            $result['picture_list'] = json_decode($result['picture_list'], true) ?: [];
        }
    }

    public function filter($filter){
        $this->from(self::TABLE . ' p')
            ->column('p.*')
            ->leftJoin('t_project_sort ps', 'ps.project_uuid', 'p.uuid')
            ->where('ps.query', json_encode(['country'=> $filter['country']??'']));
        if(!empty($filter['keywords'])){
            if(!empty($filter['keywords'])){
                $this->whereRaw("(p.title like '%{$filter['keywords']}%' or p.summary like '%{$filter['keywords']}%')");
            }
        }
        if(isset($filter['is_selection']) && is_numeric($filter['is_selection'])){
            $this->where('p.is_selection', $filter['is_selection']);
        }
        if(isset($filter['is_enabled']) && is_numeric($filter['is_enabled'])){
            $this->where('p.is_enabled', $filter['is_enabled']);
        }
        if(isset($filter['state'])){
            $this->where('p.state', $filter['state']);
        }
        if(isset($filter['country'])){
            $this->where('p.country', $filter['country']);
        }
        return $this;
    }

    public function formatFields( $data = null)
    {
        $data = parent::formatFields($data);
        if(!empty($data['state'])){
            $data['state_text'] = self::STATE[$data['state']]['text'];
        }
        return $data;
    }
}