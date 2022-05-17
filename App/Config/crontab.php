<?php
/*
 *
 * 计划任务
 * condition: 执行条件. 该值为回调函数且返回值为 true 时则执行(后台每秒执行一次该回调函数)callback; 该值为数字值(单位为毫秒)时, 每间隔该时间值执行callback.
 * callback: 满足条件时执行的回调函数.
 */
return [
    /*[
        'condition' => function(){
            if(date('w') == 3){
                return true;
            }
        },
        'callback' => function(){
            \App\Crontab\System::xx();
        }
    ],
    [
        'condition' => 300000,
        'callback' => function(){
            \App\Crontab\System::yy();
        }
    ],*/
    [//每天凌晨发送内存统计报告
        'condition' => function(){
            if(date('H:i') == '23:55' && !cache('memory_used_sent_'.date('ymd'))){
                cache('memory_used_sent_'.date('ymd'), 1);
                return true;
            }
        },
        'callback' => function(){
            \App\Crontab\System::sendMemoryUsedMail();
        }
    ],
    [//每分钟收集一次内存使用数据
        'condition' => 1000 * 60,
        'callback' => function(){
            \App\Crontab\System::collectMemoryUsed();
        }
    ]
];