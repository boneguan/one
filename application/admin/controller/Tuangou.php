<?php
/**
 * tuangou.php
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
namespace app\admin\controller;

use data\service\Pintuan;
use data\service\Order\OrderStatus;
use data\service\Express;
use think\Cache;

/**
 * 团购
 */
class Tuangou extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 团购列表
     */
    public function tuangouList()
    {
        if (request()->isAjax()) {
            $pintuan = new Pintuan();
            $page_index = request()->post("pageIndex",1);
            $start_date = request()->post('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
            $end_date = request()->post('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_date'));
            $goods_name = request()->post("goods_name","");
            $is_open = request()->post("is_open","");
            $page_size = request()->post('page_size', 0);
            
            if ($start_date != 0 && $end_date != 0) {
                $condition["ng.create_time"] = [
                    [
                        ">",
                        $start_date
                    ],
                    [
                        "<",
                        $end_date
                    ]
                ];
            } elseif ($start_date != 0 && $end_date == 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ]
                ];
            } elseif ($start_date == 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        "<",
                        $end_date
                    ]
                ];
            }
            if (! empty($goods_name)) {
                $condition["ng.goods_name"] = array(
                    "like",
                    "%" . $goods_name . "%"
                );
            }
           
            $condition["ng.shop_id"] = $this->instance_id;
            $condition["ng.goods_type"] = 1; // 目前实物商品参与拼团 2017年12月25日16:27:50
            $list = $pintuan->getGooodsPintuanList($page_index, $page_size, $condition, 'ng.create_time desc');
            return $list;
        } else {
            $pintuan = new Pintuan();
            $data = $pintuan->getTuangouType();
            $this->assign('tuangou_type', $data);

            $child_menu_list = array(
                array(
                    'url' => "tuangou/pintuanlist",
                    'menu_name' => "拼团列表",
                    "active" => 0
                ),
                array(
                    'url' => "tuangou/tuangouList",
                    'menu_name' => "拼团设置",
                    "active" => 1
                )
            );
            $this->assign("child_menu_list", $child_menu_list);
            
            return view($this->style . "Tuangou/tuangouList");
        }
    }

    /**
     * 拼团订单列表
     */
    public function pintuanOrderList()
    {
        // 获取物流公司
        $express = new Express();
        $expressList = $express->expressCompanyQuery();
        $this->assign('expressList', $expressList);
         
        $action = Cache::get("orderAction");
        if(empty($action)){
            $action = array(
                "orderAction" => $this->fetch($this->style . "Order/orderAction"),
                "orderPrintAction" => $this->fetch($this->style . "Order/orderPrintAction"),
                "orderRefundAction" => $this->fetch($this->style . "Order/orderRefundAction")
            );
            Cache::set("orderAction", $action);
        }
        
        if (request()->isAjax()) {
            $condition = array();
            $page_index = request()->post('page_index', 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $start_date = request()->post('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
            $end_date = request()->post('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_date'));
            $user_name = request()->post('user_name', '');
            $order_no = request()->post('order_no', '');
            $order_status = request()->post('order_status', '');
            $receiver_mobile = request()->post('receiver_mobile', '');
            $payment_type = request()->post('payment_type', 1);
            $shipping_type = request()->post('shipping_type', 0); //配送类型
            // 拼团id
            $tuangou_group_id = request()->post('tuangou_group_id', 0);
            $condition['order_type'] = 4; // 订单类型
            $condition['is_deleted'] = 0; // 未删除订单
                                          
            // 拼团id加入条件
            if ($tuangou_group_id > 0) {
                $condition['tuangou_group_id'] = $tuangou_group_id; // 未删除订单
            }
            
            if ($start_date != 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ],
                    [
                        "<",
                        $end_date
                    ]
                ];
            } elseif ($start_date != 0 && $end_date == 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ]
                ];
            } elseif ($start_date == 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        "<",
                        $end_date
                    ]
                ];
            }
            if ($order_status != '') {
                // $order_status 1 待发货
                if ($order_status == 1) {
                    // 订单状态为待发货实际为已经支付未完成还未发货的订单
                    $condition['shipping_status'] = 0; // 0 待发货
                    $condition['pay_status'] = 2; // 2 已支付
                    $condition['order_status'] = array(
                        'neq',
                        4
                    ); // 4 已完成
                    $condition['order_status'] = array(
                        'neq',
                        5
                    ); // 5 关闭订单
                } else
                    $condition['order_status'] = $order_status;
            }
            if (! empty($payment_type)) {
                $condition['payment_type'] = $payment_type;
            }
            if (! empty($user_name)) {
                $condition['receiver_name'] = $user_name;
            }
            if (! empty($order_no)) {
                $condition['order_no'] = $order_no;
            }
            if (! empty($receiver_mobile)) {
                $condition['receiver_mobile'] = $receiver_mobile;
            }
            if($shipping_type != 0){
                $condition['shipping_type'] = $shipping_type;
            }
            $condition['shop_id'] = $this->instance_id;
            $order_service = new Pintuan();
            $list = $order_service->getOrderList($page_index, $page_size, $condition, 'create_time desc');
            $list['action'] = $action;
            return $list;
        } else {
            $tuangou_group_id = request()->get("tuangou_group_id", 0);
            $this->assign("tuangou_group_id", $tuangou_group_id);
            $status = request()->get('status', '');
            $this->assign("status", $status);
            $all_status = OrderStatus::getOrderPintuanStatus();
            $child_menu_list = array();
            $child_menu_list[] = array(
                'url' => "tuangou/pintuanOrderList",
                'menu_name' => '全部',
                "active" => $status == '' ? 1 : 0
            );
            foreach ($all_status as $k => $v) {
                // 针对发货与提货状态名称进行特殊修改
                /*
                 * if($v['status_id'] == 1)
                 * {
                 * $status_name = '待发货/待提货';
                 * }elseif($v['status_id'] == 3){
                 * $status_name = '已收货/已提货';
                 * }else{
                 * $status_name = $v['status_name'];
                 * }
                 */
                $child_menu_list[] = array(
                    'url' => "tuangou/pintuanOrderList?status=" . $v['status_id'],
                    'menu_name' => $v['status_name'],
                    "active" => $status == $v['status_id'] ? 1 : 0
                );
            }
            $this->assign('child_menu_list', $child_menu_list);
            
            return view($this->style . "Tuangou/orderList");
        }
    }

    /**
     * 拼团设置
     */
    public function updateGoodsPintuan()
    {
        if (request()->isAjax()) {
            $tuangou_id = request()->post('tuangou_id', 0);
            $goods_id = request()->post('goods_id', 0);
            $is_open = request()->post('is_open', 0);
            $is_show = request()->post('is_show', 0);
            $tuangou_num = request()->post('tuangou_num', 0);
            $tuangou_money = request()->post('tuangou_money', 0);
            $tuangou_time = request()->post('tuangou_time', 0);
            $tuangou_type = request()->post('tuangou_type', 0);
            $colonel_commission = request()->post('colonel_commission', 0);
            $colonel_coupon = request()->post('colonel_coupon', 0);
            $colonel_point = request()->post('colonel_point', 0);
            $remark = request()->post('remark', '');
            $colonel_content = request()->post('colonel_content', '');
            
            // 转化拼团设置
            $tuangou_array = array(
                "colonel_commission" => $colonel_commission,
                "colonel_coupon" => $colonel_coupon,
                "colonel_point" => $colonel_point,
                "colonel_content" => $colonel_content
            );
            $tuangou_content_json = json_encode($tuangou_array);
            if (empty($goods_id)) {
                return AjaxReturn(- 1);
            }
            $pintuan = new Pintuan();
            $res = $pintuan->addUpdateGoodsPintuan($tuangou_id, $goods_id, $is_open, $is_show, $tuangou_money, $tuangou_num, $tuangou_time, $tuangou_type, $tuangou_content_json, $remark);
            return AjaxReturn($res);
        }
    }

    /**
     * 拼团的详细信息
     */
    public function getPintuanDetail()
    {
        if (request()->isAjax()) {
            $goods_id = request()->post('goods_id', 0);
            if (! empty($goods_id)) {
                $pintuan = new Pintuan();
                $list = $pintuan->getGoodsPintuanDetail($goods_id);
                return $list;
            } else {
                return $this->error('未获取到信息');
            }
        }
    }

    /**
     * 开关拼团
     */
    public function modifyGoodsPintuan()
    {
        if (request()->isAjax()) {
            $goods_id = request()->post('goods_id', 0);
            $is_open = request()->post('is_open', 0);
            if (! empty($goods_id)) {
                $pintuan = new Pintuan();
                $res = $pintuan->modifyGoodsTuangou($goods_id, $is_open);
                return AjaxReturn($res);
            } else {
                return AjaxReturn(- 1);
            }
        }
    }

    /**
     * 拼团列表
     */
    public function pintuanList()
    {
        if (request()->isAjax()) {
            $pintuan = new Pintuan();
            $page_index = request()->post("pageIndex",1);
            $start_date = request()->post('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
            $end_date = request()->post('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_date'));
            $group_name = request()->post("group_name","");
            $status = request()->post('status', 0);
            $page_size = request()->post('page_size', 0);
            
            if ($start_date != 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ],
                    [
                        "<",
                        $end_date
                    ]
                ];
            } elseif ($start_date != 0 && $end_date == 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ]
                ];
            } elseif ($start_date == 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        "<",
                        $end_date
                    ]
                ];
            }
            if (! empty($group_name)) {
                $condition["group_name"] = array(
                    "like",
                    "%" . $group_name . "%"
                );
            }
            if (! empty($status)) {
                $condition["status"] = $status;
            }
            $list = $pintuan->getGoodsPintuanStatusList($page_index, $page_size, $condition, 'create_time desc', '');
            return $list;
        } else {
            $child_menu_list = array(
                array(
                    'url' => "tuangou/pintuanlist",
                    'menu_name' => "拼团列表",
                    "active" => 1
                ),
                array(
                    'url' => "tuangou/tuangouList",
                    'menu_name' => "拼团设置",
                    "active" => 0
                )
            );
            $this->assign("child_menu_list", $child_menu_list);
            return view($this->style . 'Tuangou/pintuanList');
        }
    }

    public function tuangouGoodsIsRecommend()
    {
        if (request()->isAjax()) {
            $group_id = request()->post('group_id', 0);
            $is_recommend = request()->post('is_recommend', 0);
            if (! empty($group_id)) {
                $tuangou = new Pintuan();
                $res = $tuangou->modifyTuangouGroupRecommend($group_id, $is_recommend);
                return AjaxReturn($res);
            } else {
                return AjaxReturn(- 1);
            }
        }
    }

    /**
     * 拼团完成(未达到条件)
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function pintuanGroupComplete()
    {
        if (request()->isAjax()) {
            $group_id = request()->post('group_id', 0);
            if (! empty($group_id)) {
                $tuangou = new Pintuan();
                $res = $tuangou->pintuanGroupComplete($group_id);
                return AjaxReturn($res);
            } else {
                return AjaxReturn(- 1);
            }
        }
    }

    /**
     * 拼团关闭后 退款(未达到条件)
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function tuangouGroupRefund()
    {
        if (request()->isAjax()) {
            $group_id = request()->post('group_id', 0);
            if (! empty($group_id)) {
                $tuangou = new Pintuan();
                $res = $tuangou->tuangouGroupRefund($group_id);
                return AjaxReturn($res);
            } else {
                return AjaxReturn(- 1);
            }
        }
    }
}