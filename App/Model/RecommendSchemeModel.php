<?php

namespace App\Model;

use App\Utility\WaitGroup;

class RecommendSchemeModel extends BasicModel
{
    CONST TABLE = 't_recommend_scheme';

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
    }
    protected function formatAfterGet(& $result)
    {
        parent::formatAfterGet($result);
    }

    public function filter($filter){
        $this->from(self::TABLE . ' rs')
            ->column('rs.*');
        if(!empty($filter['keywords'])){
            if(!empty($filter['keywords'])){
                $this->whereRaw("rs.title like '%{$filter['keywords']}%'");
            }
        }
        if(isset($filter['is_enabled']) && is_numeric($filter['is_enabled'])){
            $this->where('rs.is_enabled', $filter['is_enabled']);
        }
        if(isset($filter['state'])){
            $this->where('rs.state', $filter['state']);
        }
        if(isset($filter['country'])){
            $this->where('rs.country', $filter['country']);
        }
        return $this;
    }

    public function formatFields( $data = null)
    {
        $data = parent::formatFields($data);
        return $data;
    }
}