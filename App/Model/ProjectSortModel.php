<?php

namespace App\Model;

class ProjectSortModel extends BasicModel
{
    CONST TABLE = 't_project_sort';

    public static function sort($project_list, $query){
        ksort($query);
        $query = json_encode($query);
        $project_uuid_list = [];
        foreach ($project_list as $v){
            $project_uuid_list[$v['uuid']] = $v;
        }
        $sort_list= self::where(['query' => $query])->orderBy('sort asc')->findAll();
        $new_list = [];
        foreach ($sort_list as $v){
            if(isset($project_uuid_list[$v['project_uuid']])){
                $new_list[] = $project_uuid_list[$v['project_uuid']];
                unset($project_uuid_list[$v['project_uuid']]);
            }
        }
        $new_list = array_merge(array_values($project_uuid_list), $new_list);
        return $new_list;
    }
}