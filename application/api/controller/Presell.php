<?php
/**
 * Order.php
 * Niushop商城系统 - 团队十年电商经验汇集巨献!
 * =========================================================
 * Copy right 2015-2025 山西牛酷信息科技有限公司, 保留所有权利。
 * ----------------------------------------------
 * 官方网址: http://www.niushop.com.cn
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用。
 * 任何企业和个人不允许对程序代码以任何形式任何目的再发布。
 * =========================================================
 * @author : niuteam
 * @date : 2015.1.17
 * @version : v1.0.0.0
 */
namespace app\api\controller;

use data\service\Config;
use data\service\Order as OrderService;
use data\service\WebSite;

/**
 * 拼团订单控制器
 *
 * @author Administrator
 *        
 */
class Presell extends Order
{
    /**
     * 获取当前会员的订单列表
     */
    public function myPresellOrderList()
    {
        $title = "获取会员订单列表";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $page_index = request()->post("page", 1);
        $status = request()->post('status', 'all');
        $condition['buyer_id'] = $this->uid;
        $condition['is_deleted'] = 0;
        $condition['order_type'] = 6; // ("in", "4");
        if (! empty($this->shop_id)) {
            $condition['shop_id'] = $this->shop_id;
        }
        
        if ($status != 'all') {
            switch ($status) {
                case -1:
                    $condition['order_status'] = array(
                    'in',
                    [
                        0,6,7
                    ]
                    );
                    break;
                case 1:
                    $condition['order_status'] = 1;
                    break;
                case 2:
                    $condition['order_status'] = 2;
                    break;
                case 4:
                    $condition['order_status'] = array(
                        'in',
                        [
                            - 1,
                            - 2
                        ]
                    );
                    break;
                default:
                    break;
            }
        }
        // 还要考虑状态逻辑
        $orderService = new OrderService();
        $order_list = $orderService->getOrderList($page_index, PAGESIZE, $condition, 'create_time desc');
        return $this->outMessage($title, $order_list);
    }

    /**
     * 订单详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function orderDetail()
    {
        $title = "获取订单详情";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $order_id = request()->post('order_id', 0);
        $order_service = new OrderService();
        $detail = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            return $this->outMessage($title, "", '-20', "无法获取订单信息");
        }
        // 通过order_id判断该订单是否属于当前用户
        $condition['order_id'] = $order_id;
        $condition['buyer_id'] = $this->uid;
        $condition['order_type'] = 6;
        
        $order_count = $order_service->getOrderCount($condition);
        if ($order_count == 0) {
            return $this->outMessage($title, "", '-20', "无法获取订单信息");
        }
        
        $count = 0; // 计算包裹数量（不包括无需物流）
        $express_count = count($detail['goods_packet_list']);
        $express_name = "";
        $express_code = "";
        if ($express_count) {
            foreach ($detail['goods_packet_list'] as $v) {
                if ($v['is_express']) {
                    $count ++;
                    if (! $express_name) {
                        $express_name = $v['express_name'];
                        $express_code = $v['express_code'];
                    }
                }
            }
            $data['express_name'] = $express_name;
            $data['express_code'] = $express_code;
        }
        $data['express_count'] = $express_count;
        $data['is_show_express_code'] = $count; // 是否显示运单号（无需物流不显示）
        $data["order"] = $detail;
        
        // 美洽客服
        $config_service = new Config();
        $list = $config_service->getcustomserviceConfig($this->instance_id);
        $web_config = new WebSite();
        $web_info = $web_config->getWebSiteInfo();
        
        $list['value']['web_phone'] = '';
        if (empty($list)) {
            $list['id'] = '';
            $list['value']['service_addr'] = '';
        }
        
        if (! empty($web_info['web_phone'])) {
            $list['value']['web_phone'] = $web_info['web_phone'];
        }
        $data["list"] = $list;
        
        return $this->outMessage($title, $data);
    }
}