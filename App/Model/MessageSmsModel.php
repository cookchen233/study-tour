<?php

namespace App\Model;

use App\Utility\WaitGroup;

class MessageSmsModel extends BasicModel
{
    CONST TABLE = 't_message_sms';

    const PLATFORM=[
        'aliyun'=>1,
        'yunpian'=>2,
        'sendcloud'=>3,
        'telecom'=>4,
    ];
}