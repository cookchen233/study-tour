<?php
namespace App\Model;

class OperateLogModel extends BasicModel
{
    protected $_connection = 'd_hnb';
    CONST TABLE = 't_operate_log';

    public function __construct($relation = null)
    {
        parent::__construct($relation);
        $this->_cache_time = 0;
    }
}