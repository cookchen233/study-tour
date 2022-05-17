<?php
namespace App\Model;

class CrmUserContact extends BasicModel
{
    CONST TABLE = 't_crm_user_contact';

    public function __construct($relation = null)
    {
        parent::__construct($relation);
        $this->_cache_time = 0;
    }

    protected function formatBeforeSave($model, & $data)
    {
        parent::formatBeforeSave($model, $data);
        if (!empty($data['f_mobile'])){
            $data['f_mobile'] = $this::encryptData($data['f_mobile']);
        }
    }
}