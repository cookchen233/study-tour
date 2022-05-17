<?php

namespace App\Model;

use App\Utility\WaitGroup;

class TopicModel extends BasicModel
{
    CONST TABLE = 't_topic';

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
        if(isset($data['project_uuid_list']) && is_array($data['project_uuid_list'])){
            $data['project_uuid_list'] = json_encode($data['project_uuid_list']);
        }
    }
    protected function formatAfterGet(& $result)
    {
        parent::formatAfterGet($result);
        if (!empty($result['finish_time'])){
            $result['finish_time_fmt'] = date('Y-m-d H:i:s', $result['finish_time']);
            $result['status_text'] = $result['finish_time'] < time() ? '已结束' : '进行中';
        }
        if (isset($result['project_uuid_list'])){
            $result['project_uuid_list'] = json_decode($result['project_uuid_list'], true) ?: [];
        }
    }

    public function filter($filter){
        $this->from(self::TABLE . ' t')
            ->column('t.*');
        if(!empty($filter['keywords'])){
            if(!empty($filter['keywords'])){
                $this->whereRaw("t.title like '%{$filter['keywords']}%'");
            }
        }
        if(isset($filter['is_enabled']) && is_numeric($filter['is_enabled'])){
            $this->where('t.is_enabled', $filter['is_enabled']);
        }
        if(!empty($filter['state'])){
            if($filter['state'] == 'processing'){
                $this->where('t.finish_time', '>',  time());
            }
            elseif($filter['state'] == 'finished'){
                $this->where('t.finish_time', '<=',  time());
            }
        }
        return $this;
    }

    public function formatFields( $data = null)
    {
        $data = parent::formatFields($data);
        $data['comments'] = TopicCommentModel::where(['topic_uuid' => $data['uuid'], 'is_enabled'=>1])->count();
        return $data;
    }
}