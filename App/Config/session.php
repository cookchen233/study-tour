<?php
return [
    'drive' => 'file', //file,redis 保存session的驱动
    'name' => 'STUDY_API_SESSION', // session_id 名字 也就是发给客户端cookie的名字
    'domain' => 'm.com',
    'expire' => 86400 * 365,
];


