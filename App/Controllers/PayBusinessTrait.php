<?php

namespace App\Controllers;

use App\Model\ServiceCardOrderModel;
use App\Model\UserKeyOpModel;
use Respect\Validation\Validator as v;
use Yansongda\Pay\Log;

trait PayBusinessTrait
{

    protected static $biz_methods = [
        '1v1' => [
            'index' => 'serviceCardPay',
            'notify' => 'serviceCardNotify'
        ]
    ];

    /**
     * 提供支付需要的业务参数
     * @return array
     */
    protected function serviceCardPay(){
        $order = null;
        v::arrayVal()
            ->key('order_uuid', v::findCallback(function ($val) use (&$order){
                $order = ServiceCardOrderModel::where('uuid', $val)->find();
                return $order;
            }))
            ->assert($this->post);
        return [
            'order_id' => $order['uuid'],
            'amount' => $order['amount'],
            'title' => '鲸舟-1V1会员服务购买',
            'back_data' => ['rid' => \One\Facades\Log::getTraceId(), 'biz_code' => '1v1', 'source' => $this->getUserSourceData()]
        ];
    }

    /**
     * 处理回调业务逻辑
     * @param $data
     */
    protected function serviceCardNotify($data){
        $data['pay_time'] = time();
        $data['effective_time'] = time();
        $data['expire_time'] = time() + (86400 * 30);
        //$data['expire_time'] = time() + 300;
        $order_uuid = $data['order_id'];
        $order_model = new ServiceCardOrderModel();
        $order_model->updateOne($data, ['uuid' => $order_uuid]);
        $source_data = $data['back_data']['source'];
        //创建用户操作日志
        $log_data = $source_data;
        $effective_time = date('Y-m-d H:i:s', $data['effective_time']);
        $expire_time = date('Y-m-d H:i:s', $data['expire_time']);
        $order = $order_model::getByUuid($order_uuid);
        $insure_for = implode('、', $order['insure_for']);
        $log_data['f_action'] = 'buy';
        $log_data['f_content'] = <<<eot
称呼: {$order['call_name']} 
性别: {$order['gender']}
为谁投保: {$insure_for}
订单状态: 生效中
生效时间: {$effective_time}
失效时间: {$expire_time}
说明: {$order['service_card_title']}
备注: {$order['note']}
eot;
        $log_data['f_uid'] = $order['user_id'];
        (new UserKeyOpModel())->createOne($log_data);
    }
}