<?php
namespace App\Model;

class OperateRoleModel extends BasicModel
{
    protected $_connection = 'd_hnb';
    CONST TABLE = 't_operate_role';

    public function __construct($relation = null)
    {
        parent::__construct($relation);
        $this->_cache_time = 0;
    }
}