<?php
/**
 * 拼团订单.php
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
namespace data\service\Order;
use data\service\Order\Order;
use data\model\AlbumPictureModel;
use data\model\ConfigModel;
use data\model\NsGoodsModel;
use data\model\NsGoodsSkuModel;
use data\model\NsOrderActionModel as NsOrderActionModel;
use data\model\NsOrderExpressCompanyModel;
use data\model\NsOrderGoodsExpressModel;
use data\model\NsOrderGoodsModel;
use data\model\NsOrderGoodsPromotionDetailsModel;
use data\model\NsOrderModel;
use data\model\NsOrderPickupModel;
use data\model\NsOrderPromotionDetailsModel;
use data\model\NsOrderRefundAccountRecordsModel;
use data\model\NsPickupPointModel;
use data\model\NsPromotionFullMailModel;
use data\model\NsPromotionMansongRuleModel;
use data\model\UserModel as UserModel;
use data\service\Address;
use data\service\BaseService;
use data\service\Config;
use data\service\Member\MemberAccount;
use data\service\Member\MemberCoupon;
use data\service\Order\OrderStatus;
use data\service\promotion\GoodsExpress;
use data\service\promotion\GoodsMansong;
use data\service\promotion\GoodsPreference;
use data\service\UnifyPay;
use data\service\WebSite;
use think\Log;
use data\service\VirtualGoods;
use data\service\Promotion;
use data\model\NsPromotionTuangouModel;
use data\model\BaseModel;
use data\service\GoodsCalculate\GoodsCalculate;
use data\model\NsPromotionGroupBuyModel;
use data\model\NsPromotionGroupBuyLadderModel;
/**
 * 订单操作类
 */
class OrderGroupBuy extends Order
{
    // 订单主表
    function __construct()
    {
        parent::__construct();
        $this->order_goods = new NsOrderGoodsModel();
    }
    /**
     * 订单创建（团购）
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
     * @param unknown $coupon_id
     * @param unknown $user_money
     * @param unknown $goods_sku_list
     * @param unknown $platform_money
     * @param unknown $pick_up_id
     * @param unknown $shipping_company_id
     * @param unknown $coin
     * @param string $fixed_telephone
     */
    public function orderCreateGroupBuy($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $coin, $fixed_telephone = "", $distribution_time_out)
    {
        $this->order->startTrans();
    
        try {
            // 设定不使用会员余额支付
            $user_money = 0;
            // 查询商品对应的店铺ID
            $order_goods_preference = new GoodsPreference();
            $shop_id = $order_goods_preference->getGoodsSkuListShop($goods_sku_list);
            // 单店版查询网站内容
            $web_site = new WebSite();
            $web_info = $web_site->getWebSiteInfo();
            $shop_name = $web_info['title'];
            $order_type = 1;
    
            // 获取购买人信息
            $buyer = new UserModel();
            $buyer_info = $buyer->getInfo([
                'uid' => $this->uid
            ], 'nick_name');
    
            $goods_money = $this->getGoodsSkuGroupBuyPrice($goods_sku_list);
            //如果价格为false则不符合团购活动规则
            if($goods_money === false){
                $this->order->rollback();
                return 0;
            }
            // 获取订单邮费,订单自提免除运费
            $order_goods_express = new GoodsExpress();
            if ($shipping_type == 1) {
                $deliver_price = $order_goods_express->getSkuListExpressFee($goods_sku_list, $shipping_company_id, $receiver_province, $receiver_city, $receiver_district);
                if ($deliver_price < 0) {
                    $this->order->rollback();
                    return $deliver_price;
                }
            }elseif($shipping_type == 2) {
                // 根据自提点服务费用计算
                $deliver_price = $order_goods_preference->getPickupMoney($goods_money);
            }elseif($shipping_type == 3){
                // 本地自提运费
                $deliver_price = $order_goods_express -> getGoodsO2oPrice($goods_money, $shop_id, $receiver_province, $receiver_city, $receiver_district, 0);
            }else{
                return 0;
            } 
    
            // 积分兑换抵用金额
            $account_flow = new MemberAccount();
            $point_money = $order_goods_preference->getPointMoney($point, 0);
           
            if($point_money < 0)
            {
                $this->order->rollback();
                return $point_money;
            }
            
            $promotion_money = 0;//优惠金额
          
                     // 订单来源
                     if (isWeixin()) {
                         $order_from = 1; // 微信
                     } elseif (request()->isMobile()) {
                         $order_from = 2; // 手机
                     } else {
                         $order_from = 3; // 电脑
                     }
    
                     // 订单待支付
                     $order_status = 0;
                     // 购买商品获取积分数
                     $give_point = $order_goods_preference->getGoodsSkuListGivePointNew($goods_sku_list);

                     $full_mail_array = array();
                     // 计算订单的满额包邮
                     $full_mail_model = new NsPromotionFullMailModel();
                     // 店铺的满额包邮
                     $full_mail_obj = $full_mail_model->getInfo([
                         "shop_id" => $shop_id
                     ], "*");
                     $no_mail = checkIdIsinIdArr($receiver_city, $full_mail_obj['no_mail_city_id_array']);
                     if ($no_mail) {
                         $full_mail_obj['is_open'] = 0;
                     }
                     if (! empty($full_mail_obj)) {
                         $is_open = $full_mail_obj["is_open"];
                         $full_mail_money = $full_mail_obj["full_mail_money"];
                         $order_real_money = $goods_money - $promotion_money;
                         if ($is_open == 1 && $order_real_money >= $full_mail_money && $deliver_price > 0) {
                             // 符合满额包邮 邮费设置为0
                             $full_mail_array["promotion_id"] = $full_mail_obj["mail_id"];
                             $full_mail_array["promotion_type"] = 'MANEBAOYOU';
                             $full_mail_array["promotion_name"] = '满额包邮';
                             $full_mail_array["promotion_condition"] = '满' . $full_mail_money . '元,包邮!';
                             $full_mail_array["discount_money"] = $deliver_price;
                             $deliver_price = 0;
                         }
                     }
    
                     // 订单费用(具体计算)
                     $order_money = $goods_money + $deliver_price;
    
                     if ($order_money < 0) {
                         $order_money = 0;
                         $user_money = 0;
                         $platform_money = 0;
                     }
    
                     if (! empty($buyer_invoice)) {
                         // 添加税费
                         $config = new Config();
                         $tax_value = $config->getConfig(0, 'ORDER_INVOICE_TAX');
                         if (empty($tax_value['value'])) {
                             $tax = 0;
                         } else {
                             $tax = $tax_value['value'];
                         }
                         $tax_money = $order_money * $tax / 100;
                     } else {
                         $tax_money = 0;
                     }
                     $order_money = $order_money + $tax_money;
    
                     if ($order_money < $platform_money) {
                         $platform_money = $order_money;
                     }
                     
                     if($order_money < $point_money){
                         $point_money = $order_money;
                     }
                     
                     $pay_money = $order_money - $user_money - $platform_money - $point_money;
                     if ($pay_money <= 0) {
                         $pay_money = 0;
                         $order_status = 0;
                         $pay_status = 0;
                     } else {
                         $order_status = 0;
                         $pay_status = 0;
                     }
    
                     // 积分返还类型
                     $config = new ConfigModel();
                     $config_info = $config->getInfo([
                         "instance_id" => $shop_id,
                         "key" => "SHOPPING_BACK_POINTS"
                     ], "value");
                     $give_point_type = $config_info["value"];
    
                     // 店铺名称
                     $data_order = array(
                         'order_type' => $order_type,
                         'order_no' => $this->createOrderNo($shop_id),
                         'out_trade_no' => $out_trade_no,
                         'payment_type' => $pay_type,
                         'shipping_type' => $shipping_type,
                         'order_from' => $order_from,
                         'buyer_id' => $this->uid,
                         'user_name' => $buyer_info['nick_name'],
                         'buyer_ip' => $buyer_ip,
                         'buyer_message' => $buyer_message,
                         'buyer_invoice' => $buyer_invoice,
                         'shipping_time' => $shipping_time, // datetime NOT NULL COMMENT '买家要求配送时间',
                         'receiver_mobile' => $receiver_mobile, // varchar(11) NOT NULL DEFAULT '' COMMENT '收货人的手机号码',
                         'receiver_province' => $receiver_province, // int(11) NOT NULL COMMENT '收货人所在省',
                         'receiver_city' => $receiver_city, // int(11) NOT NULL COMMENT '收货人所在城市',
                         'receiver_district' => $receiver_district, // int(11) NOT NULL COMMENT '收货人所在街道',
                         'receiver_address' => $receiver_address, // varchar(255) NOT NULL DEFAULT '' COMMENT '收货人详细地址',
                         'receiver_zip' => $receiver_zip, // varchar(6) NOT NULL DEFAULT '' COMMENT '收货人邮编',
                         'receiver_name' => $receiver_name, // varchar(50) NOT NULL DEFAULT '' COMMENT '收货人姓名',
                         'shop_id' => $shop_id, // int(11) NOT NULL COMMENT '卖家店铺id',
                         'shop_name' => $shop_name, // varchar(100) NOT NULL DEFAULT '' COMMENT '卖家店铺名称',
                         'goods_money' => $goods_money, // decimal(19, 2) NOT NULL COMMENT '商品总价',
                         'tax_money' => $tax_money, // 税费
                         'order_money' => $order_money, // decimal(10, 2) NOT NULL COMMENT '订单总价',
                         'point' => $point, // int(11) NOT NULL COMMENT '订单消耗积分',
                         'point_money' => $point_money, // decimal(10, 2) NOT NULL COMMENT '订单消耗积分抵多少钱',
                         'coupon_money' => "", // _money decimal(10, 2) NOT NULL COMMENT '订单代金券支付金额',
                         'coupon_id' => "", // int(11) NOT NULL COMMENT '订单代金券id',
                         'user_money' => $user_money, // decimal(10, 2) NOT NULL COMMENT '订单预存款支付金额',
                         'promotion_money' => $promotion_money, // decimal(10, 2) NOT NULL COMMENT '订单优惠活动金额',
                         'shipping_money' => $deliver_price, // decimal(10, 2) NOT NULL COMMENT '订单运费',
                         'pay_money' => $pay_money, // decimal(10, 2) NOT NULL COMMENT '订单实付金额',
                         'refund_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单退款金额',
                         'give_point' => $give_point, // int(11) NOT NULL COMMENT '订单赠送积分',
                         'order_status' => $order_status, // tinyint(4) NOT NULL COMMENT '订单状态',
                         'pay_status' => $pay_status, // tinyint(4) NOT NULL COMMENT '订单付款状态',
                         'shipping_status' => 0, // tinyint(4) NOT NULL COMMENT '订单配送状态',
                         'review_status' => 0, // tinyint(4) NOT NULL COMMENT '订单评价状态',
                         'feedback_status' => 0, // tinyint(4) NOT NULL COMMENT '订单维权状态',
                         'user_platform_money' => $platform_money, // 平台余额支付
                         'coin_money' => $coin,
                         'create_time' => time(),
                         "give_point_type" => $give_point_type,
                         'shipping_company_id' => $shipping_company_id,
                         'fixed_telephone' => $fixed_telephone, //固定电话
                         'distribution_time_out' => $distribution_time_out
                     ); // datetime NOT NULL DEFAULT 'CURRENT_TIMESTAMP' COMMENT '订单创建时间',
                     if ($pay_status == 2) {
                         $data_order["pay_time"] = time();
                     }
                     $order = new NsOrderModel();
                     $order->save($data_order);
                     $order_id = $order->order_id;
                     $pay = new UnifyPay();
                     $pay->createPayment($shop_id, $out_trade_no, $shop_name . "订单", $shop_name . "订单", $pay_money, 1, $order_id);
                      
               
                      
                    
                     // 如果是订单自提需要添加自提相关信息
                     if ($shipping_type == 2) {
                         if (! empty($pick_up_id)) {
                             $pickup_model = new NsPickupPointModel();
                             $pickup_point_info = $pickup_model->getInfo([
                                 'id' => $pick_up_id
                             ], '*');
                             $order_pick_up_model = new NsOrderPickupModel();
                             $data_pickup = array(
                                 'order_id' => $order_id,
                                 'name' => $pickup_point_info['name'],
                                 'address' => $pickup_point_info['address'],
                                 'contact' => $pickup_point_info['address'],
                                 'phone' => $pickup_point_info['phone'],
                                 'city_id' => $pickup_point_info['city_id'],
                                 'province_id' => $pickup_point_info['province_id'],
                                 'district_id' => $pickup_point_info['district_id'],
                                 'supplier_id' => $pickup_point_info['supplier_id'],
                                 'longitude' => $pickup_point_info['longitude'],
                                 'latitude' => $pickup_point_info['latitude'],
                                 'create_time' => time()
                             );
                             $order_pick_up_model->save($data_pickup);
                         }
                     }
                     // 满额包邮活动
                     if (! empty($full_mail_array)) {
                         $order_promotion_details = new NsOrderPromotionDetailsModel();
                         $data_promotion_details = array(
                             'order_id' => $order_id,
                             'promotion_id' => $full_mail_array["promotion_id"],
                             'promotion_type_id' => 2,
                             'promotion_type' => $full_mail_array["promotion_type"],
                             'promotion_name' => $full_mail_array["promotion_name"],
                             'promotion_condition' => $full_mail_array["promotion_condition"],
                             'discount_money' => $full_mail_array["discount_money"],
                             'used_time' => time()
                         );
                         $order_promotion_details->save($data_promotion_details);
                     }
    
                     // 使用积分
                     if ($point > 0) {
                         $retval_point = $account_flow->addMemberAccountData($shop_id, 1, $this->uid, 0, $point * (- 1), 1, $order_id, '商城订单');
                         if ($retval_point < 0) {
                             $this->order->rollback();
                             return ORDER_CREATE_LOW_POINT;
                         }
                     }
                     if ($coin > 0) {
                         $retval_point = $account_flow->addMemberAccountData($shop_id, 3, $this->uid, 0, $coin * (- 1), 1, $order_id, '商城订单');
                         if ($retval_point < 0) {
                             $this->order->rollback();
                             return LOW_COIN;
                         }
                     }
                     if ($user_money > 0) {
                         $retval_user_money = $account_flow->addMemberAccountData($shop_id, 2, $this->uid, 0, $user_money * (- 1), 1, $order_id, '商城订单');
                         if ($retval_user_money < 0) {
                             $this->order->rollback();
                             return ORDER_CREATE_LOW_USER_MONEY;
                         }
                     }
                     if ($platform_money > 0) {
                         $retval_platform_money = $account_flow->addMemberAccountData(0, 2, $this->uid, 0, $platform_money * (- 1), 1, $order_id, '商城订单');
                         if ($retval_platform_money < 0) {
                             $this->order->rollback();
                             return ORDER_CREATE_LOW_PLATFORM_MONEY;
                         }
                     }
                     // 添加订单项
                     $res_order_goods = $this->addOrderGoods($order_id, $goods_sku_list);
                     if (! ($res_order_goods > 0)) {
                         $this->order->rollback();
                         return $res_order_goods;
                     }
                     $this->addOrderAction($order_id, $this->uid, '创建订单');
    
                     $this->order->commit();
                     return $order_id;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e->getMessage();
        }
    }
    /**
     * 获取拼团价格
     * @param unknown $goods_id
     * @param unknown $num
     * @param unknown $tuangou_group_id
     */
    public function getGoodsGroupBuyPrice($goods_id,$num)
    {
        $ns_promotion_group_buy = new NsPromotionGroupBuyModel();
        $group_buy_info = $ns_promotion_group_buy->getFirstData(['goods_id' => $goods_id, "status" =>0], "create_time desc");
        
        if(!empty($group_buy_info)){
            $ns_promotion_group_buy_ladder = new NsPromotionGroupBuyLadderModel();
            $ns_promotion_group_buy_ladder_info = $ns_promotion_group_buy_ladder->getFirstData(['group_id' => $group_buy_info["group_id"], "num"=>["elt" , $num]], "num desc");
            if(!empty($ns_promotion_group_buy_ladder_info)){
                $money = $ns_promotion_group_buy_ladder_info["group_price"] * $num;
                return $money;
            }else{
                return false;
            }
        }else{
            return false;
        }
        
    }
    
    public function getGoodsGroupBuyUnitPrice($goods_id, $num)
    {
        $ns_promotion_group_buy = new NsPromotionGroupBuyModel();
        $group_buy_info = $ns_promotion_group_buy->getFirstData(['goods_id' => $goods_id, "status" =>0], "create_time desc");
    
        if(!empty($group_buy_info)){
            $ns_promotion_group_buy_ladder = new NsPromotionGroupBuyLadderModel();
            $ns_promotion_group_buy_ladder_info = $ns_promotion_group_buy_ladder->getFirstData(['group_id' => $group_buy_info["group_id"], "num"=>["elt" , $num]], "num desc");
            if(!empty($ns_promotion_group_buy_ladder_info)){
                $money = $ns_promotion_group_buy_ladder_info["group_price"] ;
                return $money;
            }else{
                return false;
            }
        }else{
            return false;
        }
    
    }
    
    
    /**
     * 获取团购价格
     * @param unknown $goods_sku_list
     * @param unknown $tuangou_group_id
     */
    public function getGoodsSkuGroupBuyPrice($goods_sku_list)
    {
        $ns_goods_sku = new NsGoodsSkuModel();
        
        $goods_sku_list_array = explode(",", $goods_sku_list);
        $price = 0;
        foreach ($goods_sku_list_array as $k => $goods_sku_array) {
            $goods_sku = explode(':', $goods_sku_array);
            $num += $goods_sku[1];
            $goods_sku_info = $ns_goods_sku->getInfo(['sku_id' => $goods_sku[0]], 'goods_id');
            $goods_id = $goods_sku_info["goods_id"];
            //计算团购价格
            $temp_price = $this->getGoodsGroupBuyPrice($goods_sku_info['goods_id'], $goods_sku[1]);
            if($temp_price !== false){
                $price += $temp_price;
            }else{
                return false;
            }
        }
        return $price;
        
    }
    /**
     * 添加订单项
     * @param unknown $order_id
     * @param unknown $goods_sku_list
     * @param number $adjust_money
     * @return string|number
     */
    public function addOrderGoods($order_id, $goods_sku_list)
    {
        $this->order_goods->startTrans();
        try {
            $order_goods_service = new OrderGoods();
            $err = 0;
            $goods_sku_list_array = explode(",", $goods_sku_list);
            foreach ($goods_sku_list_array as $k => $goods_sku_array) {
    
                $goods_sku = explode(':', $goods_sku_array);
                $goods_sku_model = new NsGoodsSkuModel();
                $goods_sku_info = $goods_sku_model->getInfo([
                    'sku_id' => $goods_sku[0]
                ], 'sku_id,goods_id,cost_price,stock,sku_name,attr_value_items');
    
                // 如果当前商品有SKU图片，就用SKU图片。没有则用商品主图 2017年9月19日 15:46:38（王永杰）
                $picture = $order_goods_service->getSkuPictureBySkuId($goods_sku_info);
    
                $goods_model = new NsGoodsModel();
                $goods_info = $goods_model->getInfo([
                    'goods_id' => $goods_sku_info['goods_id']
                ], 'goods_name,price,goods_type,picture,promotion_type,promote_id,point_exchange_type,give_point,integral_give_type');
    
                $goods_promote = new GoodsPreference();
                $sku_price = $this->getGoodsGroupBuyUnitPrice($goods_sku_info['goods_id'], $goods_sku[1]);
                $goods_promote_info = '';
                if (empty($goods_promote_info)) {
                    $goods_info['promotion_type'] = 0;
                    $goods_info['promote_id'] = 0;
                }
                if ($goods_sku_info['stock'] < $goods_sku[1] || $goods_sku[1] <= 0) {
                    $this->order_goods->rollback();
                    return LOW_STOCKS;
                }
                if($goods_info['integral_give_type'] == 0){
                    $give_point = $goods_sku[1] * $goods_info["give_point"];
                }elseif($goods_info['integral_give_type'] == 1){
                    $give_point = $goods_sku[1] * round($sku_price * ($goods_info['give_point'] * 0.01));
                }
                //调整金额0
                $adjust_money = 0;
                // 库存减少销量增加
                $goods_calculate = new GoodsCalculate();
                $goods_calculate->subGoodsStock($goods_sku_info['goods_id'], $goods_sku_info['sku_id'], $goods_sku[1], '');
                $goods_calculate->addGoodsSales($goods_sku_info['goods_id'], $goods_sku_info['sku_id'], $goods_sku[1]);
                $data_order_sku = array(
                    'order_id' => $order_id,
                    'goods_id' => $goods_sku_info['goods_id'],
                    'goods_name' => $goods_info['goods_name'],
                    'sku_id' => $goods_sku_info['sku_id'],
                    'sku_name' => $goods_sku_info['sku_name'],
                    'price' => $sku_price,
                    'num' => $goods_sku[1],
                    'adjust_money' => $adjust_money,
                    'cost_price' => $goods_sku_info['cost_price'],
                    'goods_money' => $sku_price * $goods_sku[1] - $adjust_money,
                    'goods_picture' => $picture != 0 ? $picture : $goods_info['picture'], // 如果当前商品有SKU图片，就用SKU图片。没有则用商品主图
                    'shop_id' => $this->instance_id,
                    'buyer_id' => $this->uid,
                    'goods_type' => $goods_info['goods_type'],
                    'promotion_id' => $goods_info['promote_id'],
                    'promotion_type_id' => $goods_info['promotion_type'],
                    'point_exchange_type' => $goods_info['point_exchange_type'],
                    'order_type' => 1, // 订单类型默认1
                    'give_point' => $give_point
                ); // 积分数量默认0
    
                if ($goods_sku[1] == 0) {
                    $err = 1;
                }
                $order_goods = new NsOrderGoodsModel();
    
                $order_goods->save($data_order_sku);
            }
            if ($err == 0) {
                $this->order_goods->commit();
                return 1;
            } elseif ($err == 1) {
                $this->order_goods->rollback();
                return ORDER_GOODS_ZERO;
            }
        } catch (\Exception $e) {
            $this->order_goods->rollback();
            return $e->getMessage();
        }
    }
    
    
    

   
}