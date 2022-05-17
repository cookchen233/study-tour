<?php
namespace App\Model;

use App\Utility\exception\AppException;

class OperateAdminModel extends BasicModel
{
    protected $_connection = 'd_hnb';
    CONST TABLE = 't_operate_user';

    public function __construct($relation = null)
    {
        parent::__construct($relation);
        $this->_cache_time = 0;
    }

    public function getPermissionMenus($admin_id){
        $per = $this->from(self::TABLE . ' u')
            ->column('r.permissions')
            ->join('t_operate_role r', 'r.id', 'u.role_id')
            ->where(['u.id'=>$admin_id])
            ->find();
        if(!$per){
            throw new AppException('管理员角色信息异常, '. $admin_id);
        }
        $menu = (new OperateMenuModel())->column('id,module,controller,function')->where('id', 'in', explode(',', $per['permissions']))->findAll();
        if(!$menu){
            throw new AppException('管理员权限信息异常, '. $per['permissions']);
        }
        return $menu;
    }
}