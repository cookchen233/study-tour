<?php

namespace App\Model;

use App\Utility\WaitGroup;

class StoryModel extends BasicModel
{
    CONST TABLE = 't_story';

    protected function formatBeforeSave($model, & $data)
    {
        parent::formatBeforeSave($model, $data);
        if(isset($data['start_time']) && !is_numeric($data['start_time'])){
            $data['start_time'] = strtotime($data['start_time']);
        }
        if(isset($data['end_time']) && !is_numeric($data['end_time'])){
            $data['end_time'] = strtotime($data['end_time']);
        }
        if(isset($data['project_uuid_list']) && is_array($data['project_uuid_list'])){
            $data['project_uuid_list'] = json_encode($data['project_uuid_list']);
        }
    }
    protected function formatAfterGet(& $result)
    {
        parent::formatAfterGet($result);
        if (isset($result['project_uuid_list'])){
            $result['project_uuid_list'] = json_decode($result['project_uuid_list'], true) ?: [];
        }
    }

    public function filter($filter){
        $this->from(self::TABLE . ' s')
            ->column('s.*');
        if(!empty($filter['keywords'])){
            if(!empty($filter['keywords'])){
                $this->whereRaw("s.title like '%{$filter['keywords']}%'");
            }
        }
        if(isset($filter['is_enabled']) && is_numeric($filter['is_enabled'])){
            $this->where('s.is_enabled', $filter['is_enabled']);
        }
        if(isset($filter['state'])){
            $this->where('s.state', $filter['state']);
        }
        if(isset($filter['country'])){
            $this->where('s.country', $filter['country']);
        }
        return $this;
    }

    public function formatFields( $data = null)
    {
        $data = parent::formatFields($data);
        return $data;
    }
}