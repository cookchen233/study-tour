<?php

namespace App\Model;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class UserKeyOpModel extends BasicModel
{

    CONST TABLE = 't_user_key_op';

    public function createOne($data)
    {
        $data['f_ctime'] = time();
        return parent::createOne($data);
    }
}