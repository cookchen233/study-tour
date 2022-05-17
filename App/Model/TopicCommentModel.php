<?php

namespace App\Model;

use App\Utility\WaitGroup;

class TopicCommentModel extends BasicModel
{
    CONST TABLE = 't_topic_comment';

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
        if (!empty($result['finish_time'])){
            $result['finish_time_fmt'] = date('Y-m-d H:i:s', $result['finish_time']);
        }
    }

    public function filter($filter){
        $this->from(self::TABLE . ' tc')
            ->column('tc.*,u.f_nickname,f_head_url,' . $this::aliasColumn('t', 'title'))
            ->join('t_topic t', 'tc.topic_uuid', 't.uuid')
            ->join('t_user u', 'tc.user_id', 'u.f_id');
        if(!empty($filter['keywords'])){
            if(!empty($filter['keywords'])){
                $this->whereRaw("(tc.content like '%{$filter['keywords']}%' or t.title like '%{$filter['keywords']}%' or u.f_nickname like '%{$filter['keywords']}%')");
            }
        }
        if(isset($filter['topic_uuid'])){
            $this->where('tc.topic_uuid', $filter['topic_uuid']);
        }
        if(isset($filter['is_enabled']) && is_numeric($filter['is_enabled'])){
            $this->where('tc.is_enabled', $filter['is_enabled']);
        }
        return $this;
    }

    public function formatFields( $data = null)
    {
        $data = parent::formatFields($data);
        return $data;
    }
}