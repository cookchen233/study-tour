<?php

namespace App\Model;

use App\Utility\WaitGroup;

class RecommendProjectV2Model extends BasicModel
{
    CONST TABLE = 't_recommend_project_v2';

    protected function formatBeforeSave($model, & $data)
    {
        parent::formatBeforeSave($model, $data);
        if(isset($data['type']) && is_array($data['type'])){
            $data['type'] = json_encode($data['type']);
        }
        if(isset($data['activity']) && is_array($data['activity'])){
            $data['activity'] = json_encode($data['activity']);
        }
        if(isset($data['purpose']) && is_array($data['purpose'])){
            $data['purpose'] = json_encode($data['purpose']);
        }
    }
    protected function formatAfterGet(& $result)
    {
        parent::formatAfterGet($result);
        if (isset($result['type'])){
            $result['type'] = json_decode($result['type'], true);
        }
        if (isset($result['activity'])){
            $result['activity'] = json_decode($result['activity'], true);
        }
        if (isset($result['purpose'])){
            $result['purpose'] = json_decode($result['purpose'], true);
        }
    }

    public function filter($filter){
        $this->from(self::TABLE . ' rp')
            ->column('rp.*,' . $this::aliasColumn('p', 'title,country,is_enabled'))
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
            $this->where('p.country', $filter['country']);
        }
        return $this;
    }


    public function formatFields( $data = null)
    {
        $data = parent::formatFields($data);
        if(isset($data['type']) && is_array($data['type'])){
            $text = [];
            foreach ($data['type'] as $v){
                $text[]=ConfigModel::getQuestionConfigKv()['type']['options'][$v];
            }
            $data['type_text']=implode(', ',$text);
        }

        return $data;
    }
}