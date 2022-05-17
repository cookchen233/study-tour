<?php

namespace App\Model;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class UserSourceModel extends BasicModel
{

    CONST TABLE = 't_user_source';

    public function createOne($data)
    {
        $data['f_ctime'] = time();
        return parent::createOne($data);
    }
}