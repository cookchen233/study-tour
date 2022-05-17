<?php

namespace App\Model;

use App\Utility\WaitGroup;

class RecommendProjectModel extends BasicModel
{
    CONST TABLE = 't_recommend_project';

    //活动类型
    CONST TYPE = [
        100 => ['value' => 100, 'text' => '领导力'],
        200 => ['value' => 200, 'text' => '体育运动'],
        300 => ['value' => 300, 'text' => '文化艺术'],
        400 => ['value' => 400, 'text' => '科技类'],
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
        if(isset($data['finish_time']) && !is_numeric($data['finish_time'])){
            $data['finish_time'] = strtotime($data['finish_time']);
        }
        if(isset($data['type']) && is_array($data['type'])){
            $data['type'] = json_encode($data['type']);
        }
    }
    protected function formatAfterGet(& $result)
    {
        parent::formatAfterGet($result);
        if (isset($result['type'])){
            $result['type'] = json_decode($result['type'], true);
        }
    }

    public function filter($filter){
        $this->from(self::TABLE . ' rp')
            ->column('rp.*,' . $this::aliasColumn('p', 'title'))
            ->join('t_project p', 'rp.project_uuid', 'p.uuid');
        if(!empty($filter['keywords'])){
            if(!empty($filter['keywords'])){
                $this->whereRaw("(p.title like '%{$filter['keywords']}%' or p.summary like '%{$filter['keywords']}%')");
            }
        }
        if(isset($filter['is_enabled']) && is_numeric($filter['is_enabled'])){
            $this->where('rp.is_enabled', $filter['is_enabled']);
        }
        if(isset($filter['state'])){
            $this->where('rp.state', $filter['state']);
        }
        if(isset($filter['country'])){
            $this->where('rp.country', $filter['country']);
        }
        return $this;
    }

    public function formatFields( $data = null)
    {
        $data = parent::formatFields($data);
        if(isset($data['type']) && is_array($data['type'])){
            $text = [];
            foreach ($data['type'] as $v){
                $text[]=self::TYPE[$v]['text'];
            }
            $data['type_text']=implode(',',$text);
        }

        return $data;
    }
}