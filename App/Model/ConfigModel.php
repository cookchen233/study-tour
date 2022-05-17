<?php

namespace App\Model;

use App\Utility\WaitGroup;

class ConfigModel extends BasicModel
{
    CONST TABLE = 't_config';

    protected function formatBeforeSave($model, & $data)
    {
        parent::formatBeforeSave($model, $data);
        if(isset($data['type']) && is_array($data['type'])){
            $data['type'] = json_encode($data['type']);
        }
        cache(__CLASS__.'::getConfig', null);
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
        return $data;
    }

    public static function getConfig($category){
        $config=cache(__METHOD__) ?: [];
        if(!isset($config[$category])){
            $config[$category]=[];
            $config_list=self::where(['category'=>$category])->findAll();
            if(!$config_list){
                if($category=='recommend_project'){
                    $config_list[$category]= ['category'=> $category,'key'=> 'questions', 'value'=><<<eot
[
  {
    "key": "country",
    "title": "请问您的孩子想去哪个地区游学？（可多选）",
    "options": [
      "美国",
      "英国",
      "澳大利亚",
      "新加坡",
      "中国"
    ],
    "maxselect": 0,
    "column": 1
  },
  {
    "key": "age",
    "title": "各个年龄阶段适合的游学项目不同，您的孩子属于哪个年龄段？",
    "options": [
      "10岁以下",
      "11-15岁",
      "25岁+"
    ],
    "maxselect": 1,
    "column": 1
  },
  {
    "key": "purpose",
    "title": "您希望孩子通过游学获得哪些收获呢？(可多选)",
    "options": [
      "提高外语交流水平",
      "锻炼TA的独立自主能力",
      "开阔视野增长见识",
      "体验国外的文化"
    ],
    "maxselect": 0,
    "column": 1
  },
  {
    "key": "type",
    "title": "请问您的孩子喜欢以下哪种类型的游学？（可多选）",
    "options": [
      "国外课堂体验",
      "百年户外营地",
      "留学背景提升"
    ],
    "maxselect": 0,
    "column": 1
  },
  {
    "key": "activity",
    "title": "相同的兴趣更容易找到志同道合的朋友，请问您的孩子喜欢以下哪些活动？（可多选）",
    "options": [
      "体育运动，如冲浪、皮划艇、户外生存、球类等",
      "文化艺术，如电影制作，戏剧、音乐等",
      "科技类，如STEAM，编程，机器人等"
    ],
    "maxselect": 0,
    "column": 1
  }
]
eot
                    ];
                }
            }
            foreach ($config_list as $v){
                $config[$category][$v['key']]=$v['value'];
            }
        }
        cache(__METHOD__, $config, 3600*24);
        return $config[$category];
    }

    public static function getQuestionConfigKv(){
        $question_config_kv=[];
        foreach (json_decode(self::getConfig('recommend_project')['questions'],true) as $v){
            $question_config_kv[$v['key']]=$v;
        }
        return $question_config_kv;
    }
}