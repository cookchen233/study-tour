<?php

namespace App\Model;

use App\Utility\WaitGroup;

class PageConfigModel extends BasicModel
{
    CONST TABLE = 't_page_config';

    //页面位置
    CONST LOCATION = [
        'banner',
        'pcBanner',
        'icon',
        'hotProject',//热门项目
        'pcHotProject',//热门项目
        'story',//游学故事
        'hotTopic',///热门话题
        'recommendSchemePop',//首页获取游学方案弹窗
        'storyListProject',//PC游学故事列表页推荐项目
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
        if(isset($data['extra']) && is_array($data['extra'])){
            $data['extra'] = json_encode($data['extra']);
        }
    }
    protected function formatAfterGet(& $result)
    {
        parent::formatAfterGet($result);
        if (isset($result['extra'])){
            $result['extra'] = json_decode($result['extra'], true);
        }
    }

    public function filter($filter){
        $this->from(self::TABLE . ' pc')
            ->column('pc.*');
        if(isset($filter['is_enabled']) && is_numeric($filter['is_enabled'])){
            $this->where('pc.is_enabled', $filter['is_enabled']);
        }
        if(isset($filter['location'])){
            $this->where('pc.location', $filter['location']);
            if(in_array($filter['location'], ['hotProject', 'pcHotProject', 'storyListProject'])){
                $this->column($this::aliasColumn('p', 'uuid,is_enabled,title,picture,summary,price'))
                    ->join('t_project p', 'pc.correlation', 'p.uuid');
                if(isset($filter['correlation_is_enabled'])){
                    $this->where('p.is_enabled', $filter['correlation_is_enabled']);
                }
                if(!empty($filter['keywords'])){
                    if(!empty($filter['keywords'])){
                        $this->whereRaw("(p.title like '%{$filter['keywords']}%' or p.summary like '%{$filter['keywords']}%')");
                    }
                }
            }
            elseif($filter['location'] == 'story'){
                $this->column($this::aliasColumn('s', 'uuid,is_enabled,title,picture,views,nickname,avatar'))
                    ->join('t_story s', 'pc.correlation', 's.uuid');
                if(isset($filter['correlation_is_enabled'])){
                    $this->where('s.is_enabled', $filter['correlation_is_enabled']);
                }
                if(!empty($filter['keywords'])){
                    if(!empty($filter['keywords'])){
                        $this->whereRaw("s.title like '%{$filter['keywords']}%'");
                    }
                }
            }
            elseif($filter['location'] == 'hotTopic'){
                $this->column($this::aliasColumn('t', 'uuid,is_enabled,title,summary,yin_title,yang_title,participants,comments'))
                    ->join('t_topic t', 'pc.correlation', 't.uuid');
                if(isset($filter['correlation_is_enabled'])){
                    $this->where('t.is_enabled', $filter['correlation_is_enabled']);
                }
                if(!empty($filter['keywords'])){
                    if(!empty($filter['keywords'])){
                        $this->whereRaw("t.title like '%{$filter['keywords']}%'");
                    }
                }
            }
            else{
                if(!empty($filter['keywords'])){
                    if(!empty($filter['keywords'])){
                        $this->whereRaw("(pc.title like '%{$filter['keywords']}%' or pc.summary like '%{$filter['keywords']}%')");
                    }
                }
            }
        }
        return $this;
    }

    public function formatFields( $data = null)
    {
        $data = parent::formatFields($data);
        return $data;
    }
}