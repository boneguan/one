<?php
/**
 * Order.php
 *
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
namespace data\service;

use data\model\NsGoodsViewModel;
use data\model\NsPromotionTuangouModel;
use data\model\NsTuangouTypeModel;
use data\model\NsTuangouGroupModel;
use think\Cache;
use data\service\Order\OrderStatus;
use data\model\NsOrderModel;
use data\model\NsOrderGoodsModel;
use data\model\NsGoodsSkuModel;
use data\model\ProvinceModel;
use data\model\CityModel;
use data\model\DistrictModel;
use data\model\AlbumPictureModel;
use data\model\UserModel;
use data\model\NsOrderPresellModel;
use data\service\User;
use data\service\Goods;
use data\service\Order;
use data\service\Order\OrderPintuan;
use data\model\NsGoodsModel;
use Qiniu\json_decode;
use data\model\NsOrderPromotionDetailsModel;
use data\service\Order\Order as OrderBusiness;
use data\service\Member\MemberAccount;
use data\model\BaseModel;

/**
 * 预售订单
 */
class Orderpresell extends Order
{

    function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取拼团列表
     *
     * @param number $page_index            
     * @param number $page_size            
     * @param string $condition            
     * @param string $order            
     * @return Ambigous <\data\model\unknown, unknown>
     */
    public function getGooodsPintuanList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $goods_view = new NsGoodsViewModel();
        $list = $goods_view->getGoodsViewList($page_index, $page_size, $condition, $order);
        if (! empty($list['data'])) {
            // 用户针对商品的收藏
            foreach ($list['data'] as $k => $v) {
                
                $goods_info = $this->getGoodsPintuanDetail($v['goods_id']);
                $list['data'][$k]['tuangou_money'] = $goods_info["tuangou_money"];
                $list['data'][$k]['tuangou_num'] = $goods_info["tuangou_num"];
                $list['data'][$k]['tuangou_time'] = $goods_info["tuangou_time"];
                $list['data'][$k]['tuangou_type'] = $goods_info["tuangou_type"];
                $list['data'][$k]['tuangou_content_json'] = $goods_info["tuangou_content_json"];
                $list['data'][$k]['is_open'] = $goods_info["is_open"];
                $list['data'][$k]['tuangou_type_name'] = $goods_info["tuangou_type_info"]['type_name'];
            }
        }
        return $list;
    }

    /**
     * 获取商品拼团详情
     *
     * @param unknown $goods_id            
     */
    public function getGoodsPintuanDetail($goods_id)
    {
        $promotion_tuangou = new NsPromotionTuangouModel();
        $tuangou_info = $promotion_tuangou->getInfo([
            'goods_id' => $goods_id
        ], 'tuangou_id,goods_id,tuangou_money,tuangou_num,tuangou_time,tuangou_type,tuangou_content_json,is_open,is_show	');
        if (! empty($tuangou_info)) {
            $tuangou_info["tuangou_type_info"] = $this->getPintuanType($tuangou_info["tuangou_type"]);
        }
        return $tuangou_info;
    }

    /**
     * 获取团购的全部类型
     */
    public function getTuangouType()
    {
        $cache = Cache::get("niushop_tuangou_type_list");
        if (empty($cache)) {
            $tuangou_type_model = new NsTuangouTypeModel();
            $res = $tuangou_type_model->getQuery('', '*', '');
            Cache::set("niushop_tuangou_type_list", $res);
            return $res;
        } else {
            return $cache;
        }
    }

    /**
     * 获取拼团类型
     *
     * @param unknown $type_id            
     */
    public function getPintuanType($type_id)
    {
        $cache = Cache::get("niushop_tuangou_type" . $type_id);
        if (empty($cache)) {
            $tuangou_type = new NsTuangouTypeModel();
            $type_info = $tuangou_type->getInfo([
                'type_id' => $type_id
            ], '*');
            Cache::set("niushop_tuangou_type" . $type_id, $type_info);
            return $type_info;
        } else {
            return $cache;
        }
    }
    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::getOrderList()
     */
    public function getOrderList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $order_model = new NsOrderModel();
        // 查询主表
        $order_list = $order_model->pageQuery($page_index, $page_size, $condition, $order, '*');
        if (! empty($order_list['data'])) {
            foreach ($order_list['data'] as $k => $v) {
                
                
                //根据主表订单id查询预售订单表
                $orderpresell = new NsOrderPresellModel();
                $order_presell_info = $orderpresell-> getInfo([
                    "relate_id" => $v["order_id"]
                ], "*");
                $order_list['data'][$k]['presell_order_id'] = $order_presell_info['presell_order_id'];
                $order_list['data'][$k]['presell_money'] = $order_presell_info['presell_money'];
                
                // 查询订单项表
                $order_item = new NsOrderGoodsModel();
                $order_item_list = $order_item->where([
                    'order_id' => $v['order_id']
                ])->select();
                
                foreach ($order_item_list as $key_item => $v_item) {
                    // 通过sku_id查询ns_goods_sku中code
                    $goods_sku = new NsGoodsSkuModel();
                    $goods_sku_info = $goods_sku->getInfo([
                        'sku_id' => $v_item['sku_id']
                    ], 'code');
                    $order_item_list[$key_item]['code'] = $goods_sku_info['code'];
                    
                    $picture = new AlbumPictureModel();
                    $goods_picture = $picture->get($v_item['goods_picture']);
                    if (empty($goods_picture)) {
                        $goods_picture = array(
                            'pic_cover' => '',
                            'pic_cover_big' => '',
                            'pic_cover_mid' => '',
                            'pic_cover_small' => '',
                            'pic_cover_micro' => '',
                            "upload_type" => 1,
                            "domain" => ""
                        );
                    }
                    $order_item_list[$key_item]['picture'] = $goods_picture;
                    if ($v_item['refund_status'] != 0) {
                        $order_refund_status = OrderStatus::getRefundStatus();
                        foreach ($order_refund_status as $k_status => $v_status) {
                            
                            if ($v_status['status_id'] == $v_item['refund_status']) {
                                $order_item_list[$key_item]['refund_operation'] = $v_status['refund_operation'];
                                $order_item_list[$key_item]['status_name'] = $v_status['status_name'];
                            }
                        }
                    } else {
                        $order_item_list[$key_item]['refund_operation'] = null;
                        $order_item_list[$key_item]['status_name'] = '';
                    }
                }
                $order_list['data'][$k]['order_item_list'] = $order_item_list;
                $order_list['data'][$k]['operation'] = '';
                // 订单来源名称
                $order_from = OrderStatus::getOrderFrom($v['order_from']);
                $order_list['data'][$k]['order_from_name'] = $order_from['type_name'];
                $order_list['data'][$k]['order_from_tag'] = $order_from['tag'];
                $order_list['data'][$k]['pay_type_name'] = OrderStatus::getPayType($v['payment_type']);
                
                $order_list['data'][$k]['shipping_type_name'] = array();
                $order_list['data'][$k]['shipping_type_name'] = OrderStatus::getShippingTypeName( $order_list['data'][$k]['shipping_type']);
                // 根据订单类型判断订单相关操作
                
               
                // 预售
                $order_status = OrderStatus::getOrderPresellStatus();
                
                // 查询订单操作
                foreach ($order_status as $k_status => $v_status) {
                    if ($v_status['status_id'] == $v['order_status']) {
                        $order_list['data'][$k]['operation'] = $v_status['operation'];
                        $order_list['data'][$k]['member_operation'] = $v_status['member_operation'];
                        $order_list['data'][$k]['status_name'] = $v_status['status_name'];
                        $order_list['data'][$k]['is_refund'] = $v_status['is_refund'];
                    }
                }
                
               
            }
        }
        return $order_list;
    }

    /**
     * 修改或添加我商品团购
     *
     * @param unknown $tuangou_id            
     * @param unknown $goods_id            
     * @param unknown $is_open            
     * @param unknown $is_show            
     * @param unknown $tuangou_money            
     * @param unknown $tuangou_num            
     * @param unknown $tuangou_time            
     * @param unknown $tuangou_type            
     * @param unknown $colonel_commission            
     * @param unknown $colonel_coupon            
     * @param unknown $colonel_point            
     * @param unknown $tuangou_content_json            
     * @param unknown $remark            
     * @return boolean
     */
    public function addUpdateGoodsPintuan($tuangou_id, $goods_id, $is_open, $is_show, $tuangou_money, $tuangou_num, $tuangou_time, $tuangou_type, $tuangou_content_json, $remark)
    {
        $tuangou = new NsPromotionTuangouModel();
        $data = [
            'goods_id' => $goods_id,
            'is_open' => $is_open,
            'is_show' => $is_show,
            'tuangou_money' => $tuangou_money,
            'tuangou_num' => $tuangou_num,
            'tuangou_time' => $tuangou_time,
            'tuangou_type' => $tuangou_type,
            
            // 'colonel_commission'=>$colonel_commission,
            // 'colonel_coupon'=>$colonel_coupon,
            // 'colonel_point'=>$colonel_point,
            'tuangou_content_json' => $tuangou_content_json,
            'remark' => $remark
        ];
        if (empty($tuangou_id)) {
            $data['create_time'] = time();
            $res = $tuangou->save($data);
            return $res;
        } else {
            $data['modify_time'] = time();
            $res = $tuangou->save($data, [
                'tuangou_id' => $tuangou_id
            ]);
            return $res;
        }
    }

    /**
     * 开关拼团
     *
     * @param unknown $goods_id            
     * @param unknown $is_open            
     * @return boolean
     */
    public function modifyGoodsTuangou($goods_id, $is_open)
    {
        $data = [
            'is_open' => $is_open
        ];
        $tuangou = new NsPromotionTuangouModel();
        $res = $tuangou->save($data, [
            'goods_id' => $goods_id
        ]);
        return $res;
    }

    /**
     * 获取拼团列表
     *
     * @param number $page_index            
     * @param number $page_size            
     * @param string $condition            
     * @param string $order            
     * @param string $field            
     */
    public function getGoodsPintuanStatusList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '')
    {
        $user = new User();
        $goods = new Goods();
        $tuangou_group = new NsTuangouGroupModel();
        $list = $tuangou_group->pageQuery($page_index, $page_size, $condition, $order, $field);
        foreach ($list["data"] as $k => $v) {
            // 剩余团购人数
            $list["data"][$k]["poor_num"] = $v["tuangou_num"] - $v["real_num"];
            $list["data"][$k]["remaining_time"] = $v["end_time"] - $v["create_time"];
        }
        return $list;
    }

    /**
     * 获取拼团组合详情
     *
     * @param unknown $goods_id            
     * @param unknown $status            
     * @return Ambigous <mixed, unknown>
     */
    public function getGoodsGroupDetail($goods_id, $status)
    {
        $tuangou_group = new NsTuangouGroupModel();
        if (empty($status)) {
            $group_info = $tuangou_group->getInfo([
                'goods_id' => $goods_id
            ]);
        } else {
            $group_info = $tuangou_group->getInfo([
                'goods_id' => $goods_id,
                'status' => $status
            ]);
        }
        
        if (! empty($group_info)) {
            $group_info["tuangou_type_info"] = $this->getPintuanType($group_info["tuangou_type"]);
        }
        return $group_info;
    }

    /**
     * 获取拼团组合订单数据
     *
     * @param unknown $tuangou_group_id            
     * @return unknown
     */
    public function getTuangouGroupOrder($tuangou_group_id)
    {
        $order = new NsOrderModel();
        $order_info = $order->getInfo([
            'tuangou_group_id' => $tuangou_group_id
        ], '');
        return $order_info;
    }

    /**
     * 团购商品是否首页显示
     *
     * @param unknown $group_id            
     * @param unknown $is_recommend            
     * @return boolean
     */
    public function modifyTuangouGroupRecommend($group_id, $is_recommend)
    {
        $data = [
            'is_recommend' => $is_recommend
        ];
        $tuangou = new NsTuangouGroupModel();
        $res = $tuangou->save($data, [
            'group_id' => $group_id
        ]);
        return $res;
    }

    /**
     * 拼团订单创建
     *
     * @param unknown $order_type            
     * @param unknown $out_trade_no            
     * @param unknown $pay_type            
     * @param unknown $shipping_type            
     * @param unknown $order_from            
     * @param unknown $buyer_ip            
     * @param unknown $buyer_message            
     * @param unknown $buyer_invoice            
     * @param unknown $shipping_time            
     * @param unknown $receiver_mobile            
     * @param unknown $receiver_province            
     * @param unknown $receiver_city            
     * @param unknown $receiver_district            
     * @param unknown $receiver_address            
     * @param unknown $receiver_zip            
     * @param unknown $receiver_name            
     * @param unknown $point            
     * @param unknown $user_money            
     * @param unknown $goods_sku_list            
     * @param unknown $platform_money            
     * @param unknown $pick_up_id            
     * @param unknown $shipping_company_id            
     * @param unknown $coin            
     * @param unknown $tuangou_group_id            
     */
    public function pintuanOrderCreate($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $coin, $tuangou_group_id)
    {
        $order_pintuan = new OrderPintuan();
        $order_service = new Order();
        $retval = $order_pintuan->orderCreatePinTuan($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $coin, '', $tuangou_group_id);
        runhook("Notify", "orderCreate", array(
            "order_id" => $retval
        ));
        // 针对特殊订单执行支付处理
        if ($retval > 0) {
            hook('orderCreateSuccess', [
                'order_id' => $retval
            ]);
            // 货到付款
            if ($pay_type == 4) {
                $order_service->orderOnLinePay($out_trade_no, 4);
            } else {
                $order_model = new NsOrderModel();
                $order_info = $order_model->getInfo([
                    'order_id' => $retval
                ], '*');
                if (! empty($order_info)) {
                    if ($order_info['user_platform_money'] != 0) {
                        if ($order_info['pay_money'] == 0) {
                            $order_service->orderOnLinePay($out_trade_no, 5);
                        }
                    } else {
            
                        if ($order_info['pay_money'] == 0) {
                            $order_service->orderOnLinePay($out_trade_no, 1); // 默认微信支付
                        }
                    }
                }
            }
        }
        
        return $retval;
    }

    /**
     * 创建拼团
     *
     * @param unknown $uid            
     * @param unknown $group_name            
     * @param unknown $user_tel            
     * @param unknown $goods_sku_list            
     */
    public function tuangouGroupCreate($uid, $user_tel, $goods_sku_list)
    {
        $tuangou_group = new NsTuangouGroupModel();
        $ns_goods_sku = new NsGoodsSkuModel();
        $ns_promotion_pintuan = new NsPromotionTuangouModel();
        $goods_sku = explode(':', $goods_sku_list);
        $goods_sku_info = $ns_goods_sku->getInfo([
            'sku_id' => $goods_sku[0]
        ], 'goods_id');
        
        $goods = new NsGoodsModel();
        $goods_info = $goods->getInfo([
            "goods_id" => $goods_sku_info["goods_id"]
        ], "goods_name");
        // 商品拼团设置
        $promotion_tuangou = new NsPromotionTuangouModel();
        $promotion_tuangou_info = $promotion_tuangou->getinfo([
            "goods_id" => $goods_sku_info["goods_id"]
        ], "*");
        
        $tuangou_type = new NsTuangouTypeModel();
        $tuangou_type_info = $tuangou_type->getinfo([
            "type_id" => $promotion_tuangou_info["tuangou_type"]
        ], "type_name");
        
        $user = new UserModel();
        $user_info = $user->getInfo([
            "uid" => $uid
        ], "nick_name, user_headimg");
        $now_time = time();
        $data = array(
            "group_uid" => $uid,
            "group_name" => $user_info["nick_name"],
            "user_tel" => $user_tel,
            "goods_id" => $goods_sku_info["goods_id"],
            "goods_name" => $goods_info["goods_name"],
            "tuangou_money" => $promotion_tuangou_info["tuangou_money"],
            "tuangou_type" => $promotion_tuangou_info["tuangou_type"],
            "tuangou_type_name" => $tuangou_type_info["type_name"],
            "price" => 0,
            "tuangou_num" => $promotion_tuangou_info["tuangou_num"],
            "real_num" => 0,
            "create_time" => $now_time,
            "end_time" => $now_time + $promotion_tuangou_info["tuangou_time"] * 3600,
            "status" => 1,
            "is_recommend" => 0,
            "group_user_head_img" => $user_info["user_headimg"]
        );
        $retval = $tuangou_group->save($data);
        if ($retval > 0) {
            return $tuangou_group->group_id;
        } else {
            return 0;
        }
    }

    /**
     * 团购增加拼团人数
     *
     * @param unknown $tuangou_group_id            
     * @return number|Ambigous \think\false, string>
     */
    public function tuangouGroupModify($tuangou_group_id)
    {
        $tuangou_group = new NsTuangouGroupModel();
        $tuangou_group->startTrans();
        try {
            $tuangou_group = new NsTuangouGroupModel();
            $tuangou_group_info = $tuangou_group->getInfo([
                "group_id" => $tuangou_group_id
            ], "tuangou_num, create_time, end_time, status, real_num, goods_id, group_uid");
            if (empty($tuangou_group_info)) {
                return 0;
            }
            if ($tuangou_group_info["tuangou_num"] <= $tuangou_group_info["real_num"]) {
                return 0;
            }
            if ($tuangou_group_info["status"] != 1) {
                return 0;
            }
            $time = time();
            if ($tuangou_group_info["create_time"] > $time || $tuangou_group_info["end_time"] < $time) {
                return 0;
            }
            $now_num = $tuangou_group_info["real_num"] + 1;
            $data = array(
                "real_num" => $now_num
            );
            if ($now_num == $tuangou_group_info["tuangou_num"]) {
                $data["status"] = 2;
            }
            $retval = $tuangou_group->save($data, [
                "group_id" => $tuangou_group_id
            ]);
            // 如果拼团已完成,订单状态变为待发货状态
            if ($data["status"] == 2) {
                $order = new NsOrderModel();
                $order_data = array(
                    "order_status" => 1
                );
                $res = $order->save($order_data, [
                    "tuangou_group_id" => $tuangou_group_id,
                    "order_status" => 6
                ]);
                
                // 给团长发送佣金 积分 优惠券
                $goods_pintuan = new NsPromotionTuangouModel();
                $goods_pintuan_info = $goods_pintuan->getInfo([
                    "goods_id" => $tuangou_group_info["goods_id"]
                ], "tuangou_content_json");
                if (! empty($goods_pintuan_info["tuangou_content_json"])) {
                    $tuangou_content_array = json_decode($goods_pintuan_info["tuangou_content_json"], true);
                    $member_account = new MemberAccount();
                    if ($tuangou_content_array["colonel_point"] > 0) {
                        $res = $member_account->addMemberAccountData(0, 1, $tuangou_group_info["group_uid"], 1, $tuangou_content_array["colonel_point"], 21, $tuangou_group_id, "团长拼团成功后赠送积分");
                    }
                    if ($tuangou_content_array["colonel_commission"] > 0) {
                        $member_account->addMemberAccountData(0, 2, $tuangou_group_info["group_uid"], 1, $tuangou_content_array["colonel_commission"], 22, $tuangou_group_id, "团长拼团成功后赠送余额");
                    }
                }
                $tuangou_group->commit();
                return 2;
            }
            $tuangou_group->commit();
            return 1;
        } catch (\Exception $e) {
            $tuangou_group->rollback();
            return 0;
        }
    }

    /**
     * 拼团关闭
     *
     * @param unknown $tuangou_group_id            
     * @return Ambigous <boolean, number, \think\false, string>
     */
    public function tuangouGroupClose($tuangou_group_id)
    {
        $tuangou_group = new NsTuangouGroupModel();
        $data = array(
            "status" => - 1
        );
        $retval = $tuangou_group->save($data, [
            "group_id" => $tuangou_group_id
        ]);
        return $retval;
    }

    /**
     * 拼团关闭改为可发货
     */
    public function pintuanGroupComplete($tuangou_group_id)
    {
        $order = new NsOrderModel();
        $order->startTrans();
        try {
            $tuangou_group = new NsTuangouGroupModel();
            $tuangou_group_count = $tuangou_group->getCount([
                "status" => - 1,
                "group_id" => $tuangou_group_id
            ]);
            if (! $tuangou_group_count > 0) {
                return 0;
            }
            // 改变订单为待发货状态
            $order_condition = array(
                "tuangou_group_id" => $tuangou_group_id,
                "order_status" => 6
            );
            $order_data = array(
                "order_status" => 1
            );
            $retval = $order->save($order_data, $order_condition);
            // 改变拼团状态
            $data = array(
                "status" => 2
            );
            $retval = $tuangou_group->save($data, [
                "group_id" => $tuangou_group_id
            ]);
            $order->commit();
            return 1;
        } catch (\Exception $e) {
            $order->rollback();
            return 0;
        }
    }

    /**
     * 拼团关闭后退款
     */
    public function tuangouGroupRefund($tuangou_group_id)
    {
        $order = new NsOrderModel();
        $order->startTrans();
        try {
            // 循环给订单退款
            
            $order_list = $order->getQuery([
                "tuangou_group_id" => $tuangou_group_id,
                "order_status" => 6
            ], "*", '');
            foreach ($order_list as $k => $v) {
                $order_goods = new NsOrderGoodsModel();
                $order_goods_list = $order_goods->getQuery([
                    "order_id" => $v["order_id"]
                ], "*", '');
                foreach ($order_goods_list as $t => $m) {
                    $this->tuangouOrderGoodsConfirmRefund($v["order_id"], $m["order_goods_id"], $v["pay_money"], 0, $v["payment_type"], '拼团失败后退款');
                }
                // 关闭订单
                $order_service = new \data\service\Order\Order();
                $this->orderClose($v["order_id"]);
            }
            // 订单关闭之后,拼团状态变为
            // $tuangou_group = new NsTuangouGroupModel();
            // $tuangou_group_count = $tuangou_group->getCount(["status" => -1, "group_id" => $tuangou_group_id]);
            // if(!$tuangou_group_count > 0){
            // return 0;
            // }
            
            // $tuangou_group_data = array(
            // "order_status" => -1
            // );
            // $retval = $tuangou_group->save($tuangou_group_data, ["group_id" => $tuangou_group_id]);
            $order->commit();
            return 1;
        } catch (\Exception $e) {
            $order->rollback();
            return 0;
        }
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::getOrderDetail()
     */
    public function getOrderDetail($order_id)
    {
        // 查询主表信息
        $order = new OrderBusiness();
        $detail = $order->getDetail($order_id);
        if (empty($detail)) {
            return array();
        }
        $detail['pay_status_name'] = $this->getPayStatusInfo($detail['pay_status'])['status_name'];
        $detail['shipping_status_name'] = $this->getShippingInfo($detail['shipping_status'])['status_name'];
        
        $express_list = $this->getOrderGoodsExpressList($order_id);
        // 未发货的订单项
        $order_goods_list = array();
        // 已发货的订单项
        $order_goods_delive = array();
        // 没有配送信息的订单项
        $order_goods_exprss = array();
        foreach ($detail["order_goods"] as $order_goods_obj) {
            $shipping_status = $order_goods_obj["shipping_status"];
            if ($shipping_status == 0) {
                // 未发货
                $order_goods_list[] = $order_goods_obj;
            } else {
                $order_goods_delive[] = $order_goods_obj;
            }
        }
        $detail["order_goods_no_delive"] = $order_goods_list;
        // 没有配送信息的订单项
        if (! empty($order_goods_delive) && count($order_goods_delive) > 0) {
            foreach ($order_goods_delive as $goods_obj) {
                $is_have = false;
                $order_goods_id = $goods_obj["order_goods_id"];
                foreach ($express_list as $express_obj) {
                    $order_goods_id_array = $express_obj["order_goods_id_array"];
                    $goods_id_str = explode(",", $order_goods_id_array);
                    if (in_array($order_goods_id, $goods_id_str)) {
                        $is_have = true;
                    }
                }
                if (! $is_have) {
                    $order_goods_exprss[] = $goods_obj;
                }
            }
        }
        $goods_packet_list = array();
        if (count($order_goods_exprss) > 0) {
            $packet_obj = array(
                "packet_name" => "无需物流",
                "express_name" => "",
                "express_code" => "",
                "express_id" => 0,
                "is_express" => 0,
                "order_goods_list" => $order_goods_exprss
            );
            $goods_packet_list[] = $packet_obj;
        }
        if (! empty($express_list) && count($express_list) > 0 && count($order_goods_delive) > 0) {
            $packet_num = 1;
            foreach ($express_list as $express_obj) {
                $packet_goods_list = array();
                $order_goods_id_array = $express_obj["order_goods_id_array"];
                $goods_id_str = explode(",", $order_goods_id_array);
                foreach ($order_goods_delive as $delive_obj) {
                    $order_goods_id = $delive_obj["order_goods_id"];
                    if (in_array($order_goods_id, $goods_id_str)) {
                        $packet_goods_list[] = $delive_obj;
                    }
                }
                $packet_obj = array(
                    "packet_name" => "包裹  + " . $packet_num,
                    "express_name" => $express_obj["express_name"],
                    "express_code" => $express_obj["express_no"],
                    "express_id" => $express_obj["id"],
                    "is_express" => 1,
                    "order_goods_list" => $packet_goods_list
                );
                $packet_num = $packet_num + 1;
                $goods_packet_list[] = $packet_obj;
            }
        }
        $detail["goods_packet_list"] = $goods_packet_list;
        $virtual_goods = new VirtualGoods();
        $virtual_goods_list = $virtual_goods->getVirtualGoodsListByOrderNo($detail['order_no']);
        $detail['virtual_goods_list'] = $virtual_goods_list;
        // 订单优惠类型
        $ns_order_promotion = new NsOrderPromotionDetailsModel();
        $promotion_detail = $ns_order_promotion->getInfo([
            "order_id" => $order_id
        ], "promotion_type");
        $detail['promotion_type'] = $promotion_detail['promotion_type'];
        
        // 关联拼团信息
        $tuangou_group = new NsTuangouGroupModel();
        $goods_pintuan = new NsPromotionTuangouModel();
        $tuangou_group_info = $tuangou_group->getInfo([
            "group_id" => $detail["tuangou_group_id"]
        ], "*");
        if (! empty($tuangou_group_info)) {
            $surplus_num = $tuangou_group_info["tuangou_num"] - $tuangou_group_info["real_num"];
            $tuangou_group_info["poor_num"] = $surplus_num;
            $order = new NsOrderModel();
            $user = new UserModel();
            $order_list = $order->getQuery([
                "tuangou_group_id" => $detail["tuangou_group_id"]
            ], "buyer_id", '');
            $user_list = array();
            foreach ($order_list as $k => $v) {
                $user_info = $user->getInfo([
                    "uid" => $v["buyer_id"]
                ], "user_headimg, nick_name");
                $user_list[$k]["user_name"] = $user_info["nick_name"];
                $user_list[$k]["user_headimg"] = $user_info["user_headimg"];
                $user_list[$k]["uid"] = $v["buyer_id"];
            }
            $tuangou_group_info["user_list"] = $user_list;
            // 商品拼单设置
            $goods_pintuan_info = $goods_pintuan->getInfo([
                "goods_id" => $tuangou_group_info["goods_id"]
            ], "tuangou_content_json");
            if (! empty($goods_pintuan_info["tuangou_content_json"])) {
                $tuangou_content_array = json_decode($goods_pintuan_info["tuangou_content_json"], true);
                $tuangou_group_info["goods_tuangou"] = $tuangou_content_array;
            }
        }
        $detail["tuangou_group_info"] = $tuangou_group_info;
        return $detail;
        // TODO Auto-generated method stub
    }
    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::getOrderList()
     */
    public function getPintuanOrderList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $goods = new NsGoodsModel();
        $order_model = new NsOrderModel();
        $order_goods = new NsOrderGoodsModel();
        $tuangou_group = new NsTuangouGroupModel();
        $list = $tuangou_group->pageQuery($page_index, $page_size, $condition, $order, "*");
        foreach ($list["data"] as $k => $v) {
            // 剩余团购人数
            $order_info = $order_model->getFirstData([
                "tuangou_group_id" => $v["group_id"],
                "buyer_id" => $v["group_uid"]
            ], "pay_time desc");
            if(empty($order_info)){
                $order_id = 0;
            }else{
                $order_id = $order_info["order_id"];
            }
            $goods_info = $goods->getInfo([
                "goods_id" => $v["goods_id"]
            ], "picture");
            $picture = new AlbumPictureModel();
            $picture = $picture->get($goods_info['picture']);
            if (empty($picture)) {
                $picture = array(
                    'pic_cover' => '',
                    'pic_cover_big' => '',
                    'pic_cover_mid' => '',
                    'pic_cover_small' => '',
                    'pic_cover_micro' => '',
                    "upload_type" => 1,
                    "domain" => ""
                );
            }
            $list["data"][$k]["picture_info"] = $picture;
            $list["data"][$k]["order_id"] = $order_id;
        }
        return $list;
    }

    /**
     * 查询商品列表
     *
     * @return Ambigous <\data\model\unknown, \data\model\multitype:unknown, multitype:unknown number >
     */
    public function getTuangouGoodsList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $goods_view = new NsGoodsViewModel();
        $list = $goods_view->getPintuanGoodsViewList($page_index, $page_size, $condition, $order);
        return $list;
    }

    /**
     * 获取拼团详情
     *
     * @param unknown $group_id            
     * @return unknown
     */
    public function getTuangouDetail($group_id, $goods_id)
    {
        $tuangou_group = new NsTuangouGroupModel();
        $tuangou_group_info = $tuangou_group->getInfo([
            "group_id" => $group_id,
            'goods_id' => $goods_id,
            'status' => 1
        ], "*");
        return $tuangou_group_info;
    }

    /**
     * 查询拼团是否真实存在
     *
     * @return \data\model\unknown
     */
    public function getTuangouGroupCount($group_id, $goods_id)
    {
        $tuangou_group = new NsTuangouGroupModel();
        $tuangou_group_count = $tuangou_group->getCount([
            "group_id" => $group_id,
            'goods_id' => $goods_id,
            'status' => 1
        ]);
        return $tuangou_group_count;
    }
    
    /**
     * 根据group_id 获取拼团详情
     * @param unknown $group_id
     */
    public function getGroupDetailByGroupId($group_id){
        $tuangou_group = new NsTuangouGroupModel();
        $goods_pintuan = new NsPromotionTuangouModel();
        $tuangou_group_info = $tuangou_group->getInfo([
            "group_id" => $group_id
        ], "*");
        if (! empty($tuangou_group_info)) {
            $surplus_num = $tuangou_group_info["tuangou_num"] - $tuangou_group_info["real_num"];
            $tuangou_group_info["poor_num"] = $surplus_num;
            $order = new NsOrderModel();
            $user = new UserModel();
            $order_list = $order->getQuery([
                "tuangou_group_id" => $group_id
            ], "buyer_id", '');
            $user_list = array();
            foreach ($order_list as $k => $v) {
                $user_info = $user->getInfo([
                    "uid" => $v["buyer_id"]
                ], "user_headimg, nick_name");
                $user_list[$k]["user_name"] = $user_info["nick_name"];
                $user_list[$k]["user_headimg"] = $user_info["user_headimg"];
                $user_list[$k]["uid"] = $v["buyer_id"];
            }
            $tuangou_group_info["user_list"] = $user_list;
            // 商品拼单设置
            $goods_pintuan_info = $goods_pintuan->getInfo([
                "goods_id" => $tuangou_group_info["goods_id"]
            ], "tuangou_content_json");
            if (! empty($goods_pintuan_info["tuangou_content_json"])) {
                $tuangou_content_array = json_decode($goods_pintuan_info["tuangou_content_json"], true);
                $tuangou_group_info["goods_tuangou"] = $tuangou_content_array;
            }
        }
        return $tuangou_group_info;
    }
}