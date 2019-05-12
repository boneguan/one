<?php
/**
 * OrderAccount.php
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
use data\model\NsTuangouGroupModel;
use think\Log;
use data\service\VirtualGoods;
use data\service\Promotion;
use data\model\NsPromotionGiftModel;
use data\model\NsPromotionGiftGoodsModel;
use data\model\NsVirtualGoodsModel;
use data\model\NsOrderCustomerAccountRecordsModel;
use data\service\Pintuan;
use data\model\NsOrderPresellModel;
use think\helper\Time;
use data\service\Goods;
use data\service\Order as OrderService;
use think\Cache;
use data\model\NsOrderPaymentModel;
use data\model\NsPickedUpAuditorViewModel;
use data\model\NsPromotionTuangouModel;

/**
 * 订单操作类
 */
class Order extends BaseService
{

    public $order;
    // 订单主表
    function __construct()
    {
        parent::__construct();
        $this->order = new NsOrderModel();
    }

    /**
     * 订单创建
     * （订单传入积分系统默认为使用积分兑换商品）
     *
     * @param unknown $order_type
     *            1正常 6预售
     * @param unknown $out_trade_no            
     * @param unknown $pay_type            
     * @param unknown $shipping_type
     *            1. 物流 2. 自提 3. 本地配送
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
     * @param unknown $point_money            
     * @param unknown $coupon_money            
     * @param unknown $coupon_id            
     * @param unknown $user_money            
     * @param unknown $promotion_money            
     * @param unknown $shipping_money            
     * @param unknown $pay_money            
     * @param unknown $give_point            
     * @param unknown $goods_sku_list            
     * @return number|Exception
     */
    public function orderCreate($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, $coupon_id, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $coin, $fixed_telephone = "", $presell_money = 0, $distribution_time_out)
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
            // 获取优惠券金额
            $coupon = new MemberCoupon();
            $coupon_money = $coupon->getCouponMoney($coupon_id);
            
            // 获取购买人信息
            $buyer = new UserModel();
            $buyer_info = $buyer->getInfo([
                'uid' => $this->uid
            ], 'nick_name');
            // 订单商品费用
            
            $goods_money = $order_goods_preference->getGoodsSkuListPrice($goods_sku_list);
            $order_goods_express = new GoodsExpress();
            // 获取订单邮费,订单自提免除运费
            
            if ($shipping_type == 1) {
                
                $deliver_price = $order_goods_express->getSkuListExpressFee($goods_sku_list, $shipping_company_id, $receiver_province, $receiver_city, $receiver_district);
                if ($deliver_price < 0) {
                    $this->order->rollback();
                    return $deliver_price;
                }
            } elseif ($shipping_type == 2) {
                // 根据自提点服务费用计算
                $deliver_price = $order_goods_preference->getPickupMoney($goods_money);
            } elseif ($shipping_type == 3) {
                $deliver_price = $order_goods_express->getGoodsO2oPrice($goods_money, $shop_id, $receiver_province, $receiver_city, $receiver_district, 0);
            } else {
                return 0;
            }
            
            // 积分兑换抵用金额
            $account_flow = new MemberAccount();
            
            $point_money = $order_goods_preference->getPointMoney($point, 0);
            if ($point_money < 0) {
                $this->order->rollback();
                return $point_money;
            }
            // 订单来源
            if (isWechatApplet($this->uid)) {
                $order_from = 4; // 微信小程序
            } elseif (isWeixin()) {
                $order_from = 1; // 微信
            } elseif (request()->isMobile()) {
                $order_from = 2; // 手机
            } else {
                $order_from = 3; // 电脑
            }
            // 订单支付方式
            
            // 订单待支付
            $order_status = 0;
            // 购买商品获取积分数
            $give_point = $order_goods_preference->getGoodsSkuListGivePointNew($goods_sku_list);
            // 订单满减送活动优惠
            $goods_mansong = new GoodsMansong();
            $mansong_array = $goods_mansong->getGoodsSkuListMansong($goods_sku_list);
            $promotion_money = 0;
            $mansong_rule_array = array();
            $mansong_discount_array = array();
            $manson_gift_array = array(); // 赠品[id]=>数量
            
            if (! empty($mansong_array)) {
                $manson_gift_temp_array = array();
                foreach ($mansong_array as $k_mansong => $v_mansong) {
                    foreach ($v_mansong['discount_detail'] as $k_rule => $v_rule) {
                        $rule = $v_rule[1];
                        $discount_money_detail = explode(':', $rule);
                        $mansong_discount_array[] = array(
                            $discount_money_detail[0],
                            $discount_money_detail[1],
                            $v_rule[0]['rule_id']
                        );
                        $promotion_money += $discount_money_detail[1]; // round($discount_money_detail[1],2);
                                                                       // 添加优惠活动信息
                        $mansong_rule_array[] = $v_rule[0];
                        
                        $gift_id = $v_rule[0]['gift_id'];
                        if ($gift_id > 0) {
                            array_push($manson_gift_temp_array, $gift_id);
                            break;
                        }
                    }
                }
                $promotion_money = round($promotion_money, 2);
                $manson_gift_array = array_count_values($manson_gift_temp_array);
            }
            if ($shipping_type == 1) {
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
                    $order_real_money = $goods_money - $promotion_money - $coupon_money;
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
            }
            
            // 订单费用(具体计算)
            $order_money = $goods_money + $deliver_price - $promotion_money - $coupon_money;
            
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
            
            $pay_money = $order_money - $user_money - $platform_money - $presell_money  - $point_money;
            if ($pay_money <= 0) {
                $pay_money = 0;
                $order_status = 0;
                $pay_status = 0;
            } else {
                $order_status = 0;
                $pay_status = 0;
            }
            // 如果是预售订单 默认状态为6 预售金待支付
            $order_status = $order_type == 6 ? 6 : $order_status;
            
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
                'coupon_money' => $coupon_money, // _money decimal(10, 2) NOT NULL COMMENT '订单代金券支付金额',
                'coupon_id' => $coupon_id, // int(11) NOT NULL COMMENT '订单代金券id',
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
                'fixed_telephone' => $fixed_telephone,
                'distribution_time_out' => $distribution_time_out
            ); // 固定电话
               // datetime NOT NULL DEFAULT 'CURRENT_TIMESTAMP' COMMENT '订单创建时间',
            if ($pay_status == 2) {
                $data_order["pay_time"] = time();
            }
            $order = new NsOrderModel();
            $order->save($data_order);
            $order_id = $order->order_id;
            $pay = new UnifyPay();
            $pay_body = $this->getPayBodyContent($shop_name, $goods_sku_list);
            $pay->createPayment($shop_id, $out_trade_no, $pay_body, $shop_name . "订单", $pay_money, 1, $order_id);
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
                        'contact' => $pickup_point_info['contact'],
                        'phone' => $pickup_point_info['phone'],
                        'city_id' => $pickup_point_info['city_id'],
                        'province_id' => $pickup_point_info['province_id'],
                        'district_id' => $pickup_point_info['district_id'],
                        'supplier_id' => $pickup_point_info['supplier_id'],
                        'longitude' => $pickup_point_info['longitude'],
                        'latitude' => $pickup_point_info['latitude'],
                        'create_time' => time(),
                        'picked_up_id' => $pick_up_id
                    );
                    if($pay_money == 0){
                        $data_pickup['picked_up_code'] = $this->getPickupCode($shop_id);
                    }
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
            
            // 添加到对应商品项优惠优惠券使用详情
            if ($coupon_id > 0) {
                $coupon_details_array = $order_goods_preference->getGoodsCouponPromoteDetail($coupon_id, $coupon_money, $goods_sku_list);
                foreach ($coupon_details_array as $k => $v) {
                    $order_goods_promotion_details = new NsOrderGoodsPromotionDetailsModel();
                    $data_details = array(
                        'order_id' => $order_id,
                        'promotion_id' => $coupon_id,
                        'sku_id' => $v['sku_id'],
                        'promotion_type' => 'COUPON',
                        'discount_money' => $v['money'],
                        'used_time' => time()
                    );
                    $order_goods_promotion_details->save($data_details);
                }
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
            
            // 使用优惠券
            if ($coupon_id > 0) {
                $retval = $coupon->useCoupon($this->uid, $coupon_id, $order_id);
                if (! ($retval > 0)) {
                    $this->order->rollback();
                    return $retval;
                }
            }
           
            // 添加订单项
            $order_goods = new OrderGoods();
            $res_order_goods = $order_goods->addOrderGoods($order_id, $goods_sku_list);
            if($res_order_goods == LOW_STOCKS){
                $this->order->rollback();
                return LOW_STOCKS;
            }           
            // 满减送详情，添加满减送活动优惠情况
            if (! empty($mansong_rule_array)) {           	
                $mansong_rule_array = array_unique($mansong_rule_array);
                foreach ($mansong_rule_array as $k_mansong_rule => $v_mansong_rule) {
                    $order_promotion_details = new NsOrderPromotionDetailsModel();
                    $data_promotion_details = array(
                        'order_id' => $order_id,
                        'promotion_id' => $v_mansong_rule['rule_id'],
                        'promotion_type_id' => 1,
                        'promotion_type' => 'MANJIAN',
                        'promotion_name' => '满减送活动',
                        'promotion_condition' => '满' . $v_mansong_rule['price'] . '元，减' . $v_mansong_rule['discount'],
                        'discount_money' => $v_mansong_rule['discount'],
                        'used_time' => time()
                    );
                    $order_promotion_details->save($data_promotion_details);
                }
                // 添加到对应商品项优惠满减
                if (! empty($mansong_discount_array)) {
                    foreach ($mansong_discount_array as $k => $v) {
                        $order_goods_promotion_details = new NsOrderGoodsPromotionDetailsModel();
                        $data_details = array(
                            'order_id' => $order_id,
                            'promotion_id' => $v[2],
                            'sku_id' => $v[0],
                            'promotion_type' => 'MANJIAN',
                            'discount_money' => $v[1],
                            'used_time' => time()
                        );
                        $order_goods_promotion_details->save($data_details);
                    }
                }
                
                // 添加赠品
                if (! empty($manson_gift_array)) {
                    $promotion = new Promotion();
                    $order_goods = new OrderGoods();
                    foreach ($manson_gift_array as $gift_id => $num) {
                        $maoson_gift_goods_sku = $promotion->getGoodsSkuByGiftId($gift_id, $num);
                        if (! empty($maoson_gift_goods_sku)) {
                            // 添加订单赠品项
                            $res_order_goods = $order_goods->addOrderGiftGoods($order_id, $maoson_gift_goods_sku);
                        }
                    }
                }
            }
           
            if (! ($res_order_goods > 0)) {
                $this->order->rollback();
                return $res_order_goods;
            }
            
            $this->addOrderAction($order_id, $this->uid, '创建订单');
            
            $this->order->commit();
            return $order_id;
        } catch (\Exception $e) {
            $this->order->rollback();
            dump($e->getMessage());
            return $e->getMessage();
        }
    }

    /**
     * 砍价订单创建
     */
    public function orderCreateBragain($out_trade_no, $goods_sku_list, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $bragain_money, $uid, $launch_id, $shipping_type, $pick_up_id){
        
        $this->order->startTrans();
        try {
        
            // 查询商品对应的店铺ID
            $order_goods_preference = new GoodsPreference();
            $shop_id = $order_goods_preference->getGoodsSkuListShop($goods_sku_list);
            
            // 单店版查询网站内容
            $web_site = new WebSite();
            $web_info = $web_site->getWebSiteInfo();
            $shop_name = $web_info['title'];
            
            // 订单来源
            if (isWechatApplet($this->uid)) {
                $order_from = 4; // 微信小程序
            } elseif (isWeixin()) {
                $order_from = 1; // 微信
            } elseif (request()->isMobile()) {
                $order_from = 2; // 手机
            } else {
                $order_from = 3; // 电脑
            }
            
            // 获取购买人信息
            $buyer = new UserModel();
            $buyer_info = $buyer->getInfo([
                'uid' => $uid
            ], 'nick_name');
            
            // 订单商品费用
            $goods_sku = new NsGoodsSkuModel();
            $goods_sku_list_arr = explode(':', $goods_sku_list); 
            $goods_sku_info = $goods_sku -> getInfo(["sku_id"=>$goods_sku_list_arr[0]], "price");
            $goods_money = $goods_sku_info["price"];
            
            // 订单待支付
            $order_status = 0;
            
            // 订单费用(具体计算)
            $order_money = $goods_money - $bragain_money;
            
            if ($order_money < 0) {
                $order_money = 0;
            }
            
            $pay_money = $order_money;
            $deliver_price = 0;
            $pay_status = 0;
            
            // 订单待支付
            $order_status = 0;
            // 购买商品获取积分数
            $give_point = $order_goods_preference->getGoodsSkuListGivePointNew($goods_sku_list);
            
            // 积分返还类型
            $config = new ConfigModel();
            $config_info = $config->getInfo([
                "instance_id" => $shop_id,
                "key" => "SHOPPING_BACK_POINTS"
            ], "value");
            $give_point_type = $config_info["value"];
            
            $data_order = array(
                'order_type' => 7,
                'order_no' => $this->createOrderNo($shop_id),
                'out_trade_no' => $out_trade_no,
                'payment_type' => 1,
                'shipping_type' => $shipping_type,
                'order_from' => $order_from,
                'buyer_id' => $uid,
                'user_name' => $buyer_info['nick_name'],
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
                'tax_money' => 0, // 税费
                'order_money' => $order_money, // decimal(10, 2) NOT NULL COMMENT '订单总价',
                'point' => 0, // int(11) NOT NULL COMMENT '订单消耗积分',
                'point_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单消耗积分抵多少钱',
                'coupon_money' => 0, // _money decimal(10, 2) NOT NULL COMMENT '订单代金券支付金额',
                'coupon_id' => 0, // int(11) NOT NULL COMMENT '订单代金券id',
                'user_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单预存款支付金额',
                'promotion_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单优惠活动金额',
                'shipping_money' => $deliver_price, // decimal(10, 2) NOT NULL COMMENT '订单运费',
                'pay_money' => $pay_money, // decimal(10, 2) NOT NULL COMMENT '订单实付金额',
                'refund_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单退款金额',
                'give_point' => $give_point, // int(11) NOT NULL COMMENT '订单赠送积分',
                'order_status' => $order_status, // tinyint(4) NOT NULL COMMENT '订单状态',
                'pay_status' => $pay_status, // tinyint(4) NOT NULL COMMENT '订单付款状态',
                'shipping_status' => 0, // tinyint(4) NOT NULL COMMENT '订单配送状态',
                'review_status' => 0, // tinyint(4) NOT NULL COMMENT '订单评价状态',
                'feedback_status' => 0, // tinyint(4) NOT NULL COMMENT '订单维权状态',
                'user_platform_money' => 0, // 平台余额支付
                'coin_money' => 0,
                'create_time' => time(),
                "give_point_type" => $give_point_type,
                'shipping_company_id' => 0,
                'fixed_telephone' => ""
            );
            
            $order = new NsOrderModel();
            $order->save($data_order);
            
            $order_id = $order->order_id;
            $pay = new UnifyPay();
            $pay_body = $this->getPayBodyContent($shop_name, $goods_sku_list);
            $pay->createPayment($shop_id, $out_trade_no, $pay_body, $shop_name . "订单", $pay_money, 1, $order_id);
            
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
                        'create_time' => time(),
                        'picked_up_id' => $pick_up_id
                    );
                    if($pay_money == 0){
                        $data_pickup['picked_up_code'] = $this->getPickupCode($shop_id);
                    }
                    $order_pick_up_model->save($data_pickup);
                }
            }
            
            $order_promotion_details = new NsOrderPromotionDetailsModel();
            $data_promotion_details = array(
                'order_id' => $order_id,
                'promotion_id' => $launch_id,
                'promotion_type_id' => 4,
                'promotion_type' => 'BARGAIN',
                'promotion_name' => '砍价活动活动',
                'promotion_condition' => '',
                'discount_money' => $bragain_money,
                'used_time' => time()
            );
            $order_promotion_details->save($data_promotion_details);
            
            $order_goods_promotion_details = new NsOrderGoodsPromotionDetailsModel();
            $data_details = array(
                'order_id' => $order_id,
                'promotion_id' => $launch_id,
                'sku_id' => $goods_sku_list_arr[0],
                'promotion_type' => 'BARGAIN',
                'discount_money' => $bragain_money,
                'used_time' => time()
            );
            $order_goods_promotion_details->save($data_details);
            
            // 添加订单项
            $order_goods = new OrderGoods();
            $res_order_goods = $order_goods->addBragainOrderGoods($order_id, $goods_sku_list);
            if($res_order_goods == LOW_STOCKS){
                $this->order->rollback();
                return LOW_STOCKS;
            }
            
            if($res_order_goods == LOW_STOCKS){
            	$this->order->rollback();
            	return LOW_STOCKS;
            }
            
            $this->addOrderAction($order_id, $uid, '创建订单');
            
            $this->order->commit();
            return $order_id;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e->getMessage();
        }
    }
    /**
     * 订单创建（虚拟商品）
     */
    public function orderCreateVirtual($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $point, $coupon_id, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $user_telephone, $coin)
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
            // 获取优惠券金额
            $coupon = new MemberCoupon();
            $coupon_money = $coupon->getCouponMoney($coupon_id);
            
            // 获取购买人信息
            $buyer = new UserModel();
            $buyer_info = $buyer->getInfo([
                'uid' => $this->uid
            ], 'nick_name');
            // 订单商品费用
            
            $goods_money = $order_goods_preference->getGoodsSkuListPrice($goods_sku_list);
            
            // 积分抵用金额
            $account_flow = new MemberAccount();
            $point_money = $order_goods_preference->getPointMoney($point, 0);
            if ($point_money < 0) {
                $this->order->rollback();
                return $point_money;
            }
            // 订单来源
            if (isWechatApplet($this->uid)) {
                $order_from = 4; // 微信小程序
            } elseif (isWeixin()) {
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
            // 订单满减送活动优惠
            $goods_mansong = new GoodsMansong();
            $mansong_array = $goods_mansong->getGoodsSkuListMansong($goods_sku_list);
            $promotion_money = 0;
            $mansong_rule_array = array();
            $mansong_discount_array = array();
            if (! empty($mansong_array)) {
                foreach ($mansong_array as $k_mansong => $v_mansong) {
                    foreach ($v_mansong['discount_detail'] as $k_rule => $v_rule) {
                        $rule = $v_rule[1];
                        $discount_money_detail = explode(':', $rule);
                        $mansong_discount_array[] = array(
                            $discount_money_detail[0],
                            $discount_money_detail[1],
                            $v_rule[0]['rule_id']
                        );
                        $promotion_money += $discount_money_detail[1]; // round($discount_money_detail[1],2);
                        $mansong_rule_array[] = $v_rule[0];
                    }
                }
                $promotion_money = round($promotion_money, 2);
            }
            
            // 订单费用(具体计算)
            $order_money = $goods_money - $promotion_money - $coupon_money;
            
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
                'shipping_time' => getTimeTurnTimeStamp($shipping_time), // datetime NOT NULL COMMENT '买家要求配送时间',
                'receiver_mobile' => $user_telephone, // varchar(11) NOT NULL DEFAULT '' COMMENT '收货人的手机号码',
                'receiver_province' => '', // int(11) NOT NULL COMMENT '收货人所在省',
                'receiver_city' => '', // int(11) NOT NULL COMMENT '收货人所在城市',
                'receiver_district' => '', // int(11) NOT NULL COMMENT '收货人所在街道',
                'receiver_address' => '', // varchar(255) NOT NULL DEFAULT '' COMMENT '收货人详细地址',
                'receiver_zip' => '', // varchar(6) NOT NULL DEFAULT '' COMMENT '收货人邮编',
                'receiver_name' => '', // varchar(50) NOT NULL DEFAULT '' COMMENT '收货人姓名',
                'shop_id' => $shop_id, // int(11) NOT NULL COMMENT '卖家店铺id',
                'shop_name' => $shop_name, // varchar(100) NOT NULL DEFAULT '' COMMENT '卖家店铺名称',
                'goods_money' => $goods_money, // decimal(19, 2) NOT NULL COMMENT '商品总价',
                'tax_money' => $tax_money, // 税费
                'order_money' => $order_money, // decimal(10, 2) NOT NULL COMMENT '订单总价',
                'point' => $point, // int(11) NOT NULL COMMENT '订单消耗积分',
                'point_money' => $point_money, // decimal(10, 2) NOT NULL COMMENT '订单消耗积分抵多少钱',
                'coupon_money' => $coupon_money, // _money decimal(10, 2) NOT NULL COMMENT '订单代金券支付金额',
                'coupon_id' => $coupon_id, // int(11) NOT NULL COMMENT '订单代金券id',
                'user_money' => $user_money, // decimal(10, 2) NOT NULL COMMENT '订单预存款支付金额',
                'promotion_money' => $promotion_money, // decimal(10, 2) NOT NULL COMMENT '订单优惠活动金额',
                'shipping_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单运费',
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
                'fixed_telephone' => ""
            ); // 固定电话
               // datetime NOT NULL DEFAULT 'CURRENT_TIMESTAMP' COMMENT '订单创建时间',
            if ($pay_status == 2) {
                $data_order["pay_time"] = time();
            }
            $order = new NsOrderModel();
            $order->save($data_order);
            $order_id = $order->order_id;
            $pay = new UnifyPay();
            $pay_body = $this->getPayBodyContent($shop_name, $goods_sku_list);
            $pay->createPayment($shop_id, $out_trade_no, $pay_body, $shop_name . "虚拟订单", $pay_money, 1, $order_id);
            // 满减送详情，添加满减送活动优惠情况
            if (! empty($mansong_rule_array)) {
                
                $mansong_rule_array = array_unique($mansong_rule_array);
                foreach ($mansong_rule_array as $k_mansong_rule => $v_mansong_rule) {
                    $order_promotion_details = new NsOrderPromotionDetailsModel();
                    $data_promotion_details = array(
                        'order_id' => $order_id,
                        'promotion_id' => $v_mansong_rule['rule_id'],
                        'promotion_type_id' => 1,
                        'promotion_type' => 'MANJIAN',
                        'promotion_name' => '满减送活动',
                        'promotion_condition' => '满' . $v_mansong_rule['price'] . '元，减' . $v_mansong_rule['discount'],
                        'discount_money' => $v_mansong_rule['discount'],
                        'used_time' => time()
                    );
                    $order_promotion_details->save($data_promotion_details);
                }
                // 添加到对应商品项优惠满减
                if (! empty($mansong_discount_array)) {
                    foreach ($mansong_discount_array as $k => $v) {
                        $order_goods_promotion_details = new NsOrderGoodsPromotionDetailsModel();
                        $data_details = array(
                            'order_id' => $order_id,
                            'promotion_id' => $v[2],
                            'sku_id' => $v[0],
                            'promotion_type' => 'MANJIAN',
                            'discount_money' => $v[1],
                            'used_time' => time()
                        );
                        $order_goods_promotion_details->save($data_details);
                    }
                }
            }
            // 添加到对应商品项优惠优惠券使用详情
            if ($coupon_id > 0) {
                $coupon_details_array = $order_goods_preference->getGoodsCouponPromoteDetail($coupon_id, $coupon_money, $goods_sku_list);
                foreach ($coupon_details_array as $k => $v) {
                    $order_goods_promotion_details = new NsOrderGoodsPromotionDetailsModel();
                    $data_details = array(
                        'order_id' => $order_id,
                        'promotion_id' => $coupon_id,
                        'sku_id' => $v['sku_id'],
                        'promotion_type' => 'COUPON',
                        'discount_money' => $v['money'],
                        'used_time' => time()
                    );
                    $order_goods_promotion_details->save($data_details);
                }
            }
            // 使用积分
            if ($point > 0) {
                $retval_point = $account_flow->addMemberAccountData($shop_id, 1, $this->uid, 0, $point * (- 1), 1, $order_id, '商城虚拟订单');
                if ($retval_point < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_POINT;
                }
            }
            if ($coin > 0) {
                $retval_point = $account_flow->addMemberAccountData($shop_id, 3, $this->uid, 0, $coin * (- 1), 1, $order_id, '商城虚拟订单');
                if ($retval_point < 0) {
                    $this->order->rollback();
                    return LOW_COIN;
                }
            }
            if ($user_money > 0) {
                $retval_user_money = $account_flow->addMemberAccountData($shop_id, 2, $this->uid, 0, $user_money * (- 1), 1, $order_id, '商城虚拟订单');
                if ($retval_user_money < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_USER_MONEY;
                }
            }
            if ($platform_money > 0) {
                $retval_platform_money = $account_flow->addMemberAccountData(0, 2, $this->uid, 0, $platform_money * (- 1), 1, $order_id, '商城虚拟订单');
                if ($retval_platform_money < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_PLATFORM_MONEY;
                }
            }
            // 使用优惠券
            if ($coupon_id > 0) {
                $retval = $coupon->useCoupon($this->uid, $coupon_id, $order_id);
                if (! ($retval > 0)) {
                    $this->order->rollback();
                    return $retval;
                }
            }
            // 添加订单项
            $order_goods = new OrderGoods();
            $res_order_goods = $order_goods->addOrderGoods($order_id, $goods_sku_list);
            if (! ($res_order_goods > 0)) {
                $this->order->rollback();
                return $res_order_goods;
            }
            $this->addOrderAction($order_id, $this->uid, '创建虚拟订单');
            
            $this->order->commit();
            return $order_id;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 订单创建（组合商品）
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
     * @param unknown $coupon_id            
     * @param unknown $user_money            
     * @param unknown $goods_sku_list            
     * @param unknown $platform_money            
     * @param unknown $pick_up_id            
     * @param unknown $shipping_company_id            
     * @param unknown $coin            
     * @param string $fixed_telephone            
     */
    public function orderCreateComboPackage($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $coin, $fixed_telephone = "", $combo_package_id, $buy_num, $distribution_time_out)
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
            
            // 获取组合套餐详情
            $promotion = new Promotion();
            $combo_package_detail = $promotion->getComboPackageDetail($combo_package_id);
            
            // 获取购买人信息
            $buyer = new UserModel();
            $buyer_info = $buyer->getInfo([
                'uid' => $this->uid
            ], 'nick_name');
            
            // 订单商品费用
            $goods_money = $order_goods_preference->getComboPackageGoodsSkuListPrice($goods_sku_list);
            
            // 购买套餐费用
            $combo_package_price = $combo_package_detail["combo_package_price"] * $buy_num;
            
            // 积分兑换抵用金额
            $account_flow = new MemberAccount();
            $point_money = $order_goods_preference->getPointMoney($point, 0);
            if ($point_money < 0) {
                $this->order->rollback();
                return $point_money;
            }
            
            // 获取订单邮费,订单自提免除运费
            $order_goods_express = new GoodsExpress();
            if ($shipping_type == 1) {
                $deliver_price = $order_goods_express->getSkuListExpressFee($goods_sku_list, $shipping_company_id, $receiver_province, $receiver_city, $receiver_district);
                
                if ($deliver_price < 0) {
                    $this->order->rollback();
                    return $deliver_price;
                }
            } elseif ($shipping_type == 2) {
                // 根据自提点服务费用计算
                $deliver_price = $order_goods_preference->getPickupMoney($combo_package_price);
            } elseif ($shipping_type == 3) {
                // 本地自提运费
                $deliver_price = $order_goods_express->getGoodsO2oPrice($combo_package_price, $shop_id, $receiver_province, $receiver_city, $receiver_district, 0);
            } else {
                return 0;
            }
            
            // 积分兑换抵用金额
            $account_flow = new MemberAccount();
            $point_money = $order_goods_preference->getPointMoney($point, 0);
            if ($point_money < 0) {
                $this->order->rollback();
                return $point_money;
            }
            /*
             * if($point > 0)
             * {
             * //积分兑换抵用商品金额+邮费
             * $point_money = $goods_money;
             * //订单为已支付
             * if($deliver_price == 0)
             * {
             * $order_status = 1;
             * }else
             * {
             * $order_status = 0;
             * }
             *
             * //赠送积分为0
             * $give_point = 0;
             * //不享受满减送优惠
             * $promotion_money = 0;
             *
             * }else{
             */
            // 订单来源
            if (isWechatApplet($this->uid)) {
                $order_from = 4; // 微信小程序
            } elseif (isWeixin()) {
                $order_from = 1; // 微信
            } elseif (request()->isMobile()) {
                $order_from = 2; // 手机
            } else {
                $order_from = 3; // 电脑
            }
            // 订单支付方式
            
            // 订单待支付
            $order_status = 0;
            // 购买商品获取积分数
            $give_point = $order_goods_preference->getGoodsSkuListGivePointNew($goods_sku_list);
            // 订单优惠价格
            $promotion_money = round(($goods_money - $combo_package_price), 2);
            // 如果优惠价小于0则优惠为0 一般这种情况是因为在组合商品发布后商品价格发生变化
            $promotion_money = $promotion_money < 0 ? 0 : $promotion_money;
            
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
                $order_real_money = $combo_package_price;
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
            $order_money = $combo_package_price + $deliver_price;
            
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
                'fixed_telephone' => $fixed_telephone,
                'distribution_time_out' => $distribution_time_out
            ); // 固定电话
               // datetime NOT NULL DEFAULT 'CURRENT_TIMESTAMP' COMMENT '订单创建时间',
            if ($pay_status == 2) {
                $data_order["pay_time"] = time();
            }
            $order = new NsOrderModel();
            $order->save($data_order);
            $order_id = $order->order_id;
            $pay = new UnifyPay();
            $pay_body = $this->getPayBodyContent($shop_name, $goods_sku_list);
            $pay->createPayment($shop_id, $out_trade_no, $pay_body, $shop_name . "订单", $pay_money, 1, $order_id);
            
            // 订单优惠详情，添加组合套餐优惠情况
            $order_promotion_details = new NsOrderPromotionDetailsModel();
            $data_promotion_details = array(
                'order_id' => $order_id,
                'promotion_id' => $combo_package_id,
                'promotion_type_id' => 3,
                'promotion_type' => 'ZUHETAOCAN',
                'promotion_name' => '组合套餐活动',
                'promotion_condition' => "套餐名称：" . $combo_package_detail['combo_package_name'] . "原价：" . $goods_money . "套餐价：" . $combo_package_detail['combo_package_price'] * $buy_num,
                'discount_money' => $promotion_money,
                'used_time' => time()
            );
            $order_promotion_details->save($data_promotion_details);
            
            // 添加到对应商品项优惠信息
            
            $goods_sku = explode(",", $goods_sku_list);
            $ns_goods_sku = new NsGoodsSkuModel();
            $ns_goods = new NsGoodsModel();
            $temp_promotion_money = $promotion_money / $buy_num; // 单套套餐优惠价格
            $temp_goods_money = $goods_money / $buy_num; // 单套商品总价
            $temp_promotion_surplus_money = $temp_promotion_money; // 剩余优惠
            
            for ($i = 0; $i < count($goods_sku); $i ++) {
                
                $sku = explode(":", $goods_sku[$i]);
                $sku_id = $sku[0];
                $goods_detial = $ns_goods_sku->getInfo([
                    "sku_id" => $sku_id
                ], "price");
                $discount_money = round($goods_detial["price"] * $temp_promotion_money / $goods_money, 2);
                if ($i == (count($goods_sku) - 1)) {
                    $discount_money = $temp_promotion_surplus_money;
                }
                if ($discount_money > $temp_promotion_surplus_money) {
                    $discount_money = $temp_promotion_surplus_money;
                }
                if ($discount_money > $goods_detial["price"]) {
                    $discount_money = $goods_detial["price"];
                }
                $order_goods_promotion_details = new NsOrderGoodsPromotionDetailsModel();
                // 商品原价/原价*优惠价
                $data_details = array(
                    'order_id' => $order_id,
                    'promotion_id' => $combo_package_id,
                    'sku_id' => $sku_id,
                    'promotion_type' => 'ZUHETAOCAN',
                    'discount_money' => $discount_money,
                    'used_time' => time()
                );
                $order_goods_promotion_details->save($data_details);
                
                $temp_promotion_surplus_money = $temp_promotion_surplus_money - $discount_money;
                if ($temp_promotion_surplus_money < 0) {
                    $temp_promotion_surplus_money = 0;
                }
            }
            
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
                        'create_time' => time(),
                        'picked_up_id' => $pick_up_id
                    );
                    if($pay_money == 0){
                        $data_pickup['picked_up_code'] = $this->getPickupCode($shop_id);
                    }
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
            $order_goods = new OrderGoods();
            $res_order_goods = $order_goods->addComboPackageOrderGoods($order_id, $goods_sku_list);
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
     * 订单创建
     * (积分兑换 实物商品)
     *
     * @param unknown $order_type            
     * @param unknown $out_trade_no            
     * @param unknown $pay_type            
     * @param unknown $shipping_type
     *            1. 物流 2. 自提 3. 本地配送
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
     * @param unknown $point_money            
     * @param unknown $coupon_money            
     * @param unknown $coupon_id            
     * @param unknown $user_money            
     * @param unknown $promotion_money            
     * @param unknown $shipping_money            
     * @param unknown $pay_money            
     * @param unknown $give_point            
     * @param unknown $goods_sku_list            
     * @return number|Exception
     */
    public function orderCreatePointExhange($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, $coupon_id, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $coin, $fixed_telephone = "", $point_exchange_type, $distribution_time_out)
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
            // 获取优惠券金额
            $coupon = new MemberCoupon();
            $coupon_money = $coupon->getCouponMoney($coupon_id);
            
            // 获取购买人信息
            $buyer = new UserModel();
            $buyer_info = $buyer->getInfo([
                'uid' => $this->uid
            ], 'nick_name');
            // 订单商品费用
            
            $goods_money = 0;
            // 只有当兑换类型为 积分与现金同时存在才计算商品金额
            if ($point_exchange_type == 1) {
                $goods_money = $order_goods_preference->getGoodsSkuListPrice($goods_sku_list);
            }
            $point = $order_goods_preference->getGoodsListExchangePoint($goods_sku_list);
            $order_goods_express = new GoodsExpress();
            // 获取订单邮费,订单自提免除运费
            if ($shipping_type == 1) {
                
                $deliver_price = $order_goods_express->getSkuListExpressFee($goods_sku_list, $shipping_company_id, $receiver_province, $receiver_city, $receiver_district);
                if ($deliver_price < 0) {
                    $this->order->rollback();
                    return $deliver_price;
                }
            } elseif ($shipping_type == 2) {
                // 根据自提点服务费用计算
                $deliver_price = $order_goods_preference->getPickupMoney($goods_money);
            } elseif ($shipping_type == 3) {
                $deliver_price = $order_goods_express->getGoodsO2oPrice($goods_money, $shop_id, $receiver_province, $receiver_city, $receiver_district, 0);
            } else {
                return 0;
            }
            
            // 积分兑换抵用金额
            $account_flow = new MemberAccount();
            
            // 订单来源
            if (isWechatApplet($this->uid)) {
                $order_from = 4; // 微信小程序
            } elseif (isWeixin()) {
                $order_from = 1; // 微信
            } elseif (request()->isMobile()) {
                $order_from = 2; // 手机
            } else {
                $order_from = 3; // 电脑
            }
            // 订单支付方式
            
            // 订单待支付
            $order_status = 0;
            // 购买商品获取积分数
            $give_point = $order_goods_preference->getGoodsSkuListGivePointNew($goods_sku_list);
            // 订单满减送活动优惠
            $goods_mansong = new GoodsMansong();
            $mansong_array = $goods_mansong->getGoodsSkuListMansong($goods_sku_list);
            $promotion_money = 0;
            $mansong_rule_array = array();
            $mansong_discount_array = array();
            $manson_gift_array = array(); // 赠品[id]=>数量
                                          
            // 订单费用(具体计算)
            $order_money = $goods_money + $deliver_price - $promotion_money - $coupon_money;
            
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
            
            $pay_money = $order_money - $user_money - $platform_money;
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
                'point_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单消耗积分抵多少钱',
                'coupon_money' => $coupon_money, // _money decimal(10, 2) NOT NULL COMMENT '订单代金券支付金额',
                'coupon_id' => $coupon_id, // int(11) NOT NULL COMMENT '订单代金券id',
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
                'fixed_telephone' => $fixed_telephone,
                'distribution_time_out' => $distribution_time_out
            ); // 固定电话
               // datetime NOT NULL DEFAULT 'CURRENT_TIMESTAMP' COMMENT '订单创建时间',
            if ($pay_status == 2) {
                $data_order["pay_time"] = time();
            }
            $order = new NsOrderModel();
            $order->save($data_order);
            $order_id = $order->order_id;
            $pay = new UnifyPay();
            $pay_body = $this->getPayBodyContent($shop_name, $goods_sku_list);
            $pay->createPayment($shop_id, $out_trade_no, $pay_body, $shop_name . "订单", $pay_money, 1, $order_id);
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
                        'create_time' => time(),
                        'picked_up_id' => $pick_up_id
                    );
                    if($pay_money == 0){
                        $data_pickup['picked_up_code'] = $this->getPickupCode($shop_id);
                    }
                    $order_pick_up_model->save($data_pickup);
                }
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
            if ($platform_money > 0) {
                $retval_platform_money = $account_flow->addMemberAccountData(0, 2, $this->uid, 0, $platform_money * (- 1), 1, $order_id, '商城订单');
                if ($retval_platform_money < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_PLATFORM_MONEY;
                }
            }
            
            // 添加订单项
            $order_goods = new OrderGoods();
            $res_order_goods = $order_goods->addOrderGoods($order_id, $goods_sku_list);
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
     * 预售订单创建
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
     * @param unknown $coupon_id            
     * @param unknown $user_money            
     * @param unknown $goods_sku_list            
     * @param unknown $platform_money            
     * @param unknown $pick_up_id            
     * @param unknown $shipping_company_id            
     * @param unknown $coin            
     * @param string $fixed_telephone            
     */
    public function orderCreatePresell($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, $coupon_id, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $coin, $fixed_telephone = "", $is_full_payment = 0, $distribution_time_out)
    {
        $presell_order_model = new NsOrderPresellModel();
        $order_goods_express = new GoodsExpress();
        $presell_order_model->startTrans();
        
        try {
            
            $order_goods_preference = new GoodsPreference();
            $presell_money = $order_goods_preference->getGoodsPresell($goods_sku_list);
            $deliver_price = 0;
            
            // 单店版查询网站内容
            $web_site = new WebSite();
            $web_info = $web_site->getWebSiteInfo();
            $shop_name = $web_info['title'];
            
            // 查询商品对应的店铺ID
            $order_goods_preference = new GoodsPreference();
            $shop_id = $order_goods_preference->getGoodsSkuListShop($goods_sku_list);
            
            if (! empty($is_full_payment)) {
                $presell_money = $order_goods_preference->getGoodsSkuListPrice($goods_sku_list);
                if ($shipping_type == 1) {
                    $deliver_price = $order_goods_express->getSkuListExpressFee($goods_sku_list, $shipping_company_id, $receiver_province, $receiver_city, $receiver_district);
                    if ($deliver_price < 0) {
                        $this->order->rollback();
                        return $deliver_price;
                    }
                } elseif ($shipping_type == 2) {
                    // 根据自提点服务费用计算
                    $deliver_price = $order_goods_preference->getPickupMoney($presell_money);
                } elseif ($shipping_type == 3) {
                    $deliver_price = $order_goods_express->getGoodsO2oPrice($presell_money, $shop_id, $receiver_province, $receiver_city, $receiver_district, 0);
                } else {
                    return 0;
                }
            }
            
            $order_out_trade_no = $this->createOutTradeNo();
            
            // 积分兑换抵用金额
            $account_flow = new MemberAccount();
            
            $point_money = $order_goods_preference->getPointMoney($point, 0);
            if ($point_money < 0) {
                $presell_order_model->rollback();
                return $point_money;
            }
            
            $goods_service = new Goods();
            $sku_data = explode(":", $goods_sku_list);
            $sku_id = $sku_data[0];
            $goods_id = $goods_service->getGoodsId($sku_id);
            
            // 创建订单，类型为预售订单
            $order_id = $this->orderCreate($order_type, $order_out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, 0, $coupon_id, $user_money, $goods_sku_list, 0.00, $pick_up_id, $shipping_company_id, $coin, $fixed_telephone, $presell_money, $distribution_time_out);
            if ($order_id < 0) {
                
                $presell_order_model->rollback();
                return $order_id;
            }
            
            $goods_model = new NsGoodsModel();
            $goods_info = $goods_model->getInfo([
                'goods_id' => $goods_id
            ], '*');
            // 发货时间
            $presell_delivery_type = $goods_info['presell_delivery_type'];
            $presell_delivery_value = $presell_delivery_type == 1 ? $goods_info['presell_time'] : $goods_info['presell_day'];
            
            $presell_money += $deliver_price;
            
            // 预售金实际需支付金额
            $presell_pay = $presell_money - $platform_money - $point_money;
            $presell_pay = $presell_pay < 0 ? 0 : $presell_pay;
            
            // 创建预售订单
            $presell_order_data = array(
                'out_trade_no' => $out_trade_no,
                'presell_pay' => $presell_pay,
                'platform_money' => $platform_money,
                'create_time' => time(),
                'relate_id' => $order_id,
                'presell_money' => $presell_money,
                'point' => $point,
                'point_money' => $point_money,
                'presell_price' => $presell_delivery_type,
                'presell_delivery_type' => $presell_delivery_type,
                'presell_delivery_value' => $presell_delivery_value,
                'is_full_payment' => $is_full_payment
            );
            $presell_order_id = $presell_order_model->save($presell_order_data);
            
            $pay = new UnifyPay();
            $pay->createPayment($shop_id, $out_trade_no, $shop_name . "预售订单", $shop_name . "预售订单", $presell_pay, 5, $presell_order_id);
            
            // 使用积分
            if ($point > 0) {
                $retval_point = $account_flow->addMemberAccountData($shop_id, 1, $this->uid, 0, $point * (- 1), 1, $order_id, '商城订单');
                if ($retval_point < 0) {
                    $presell_order_model->rollback();
                    return ORDER_CREATE_LOW_POINT;
                }
            }
            
            if ($platform_money > 0) {
                $retval_platform_money = $account_flow->addMemberAccountData(0, 2, $this->uid, 0, $platform_money * (- 1), 1, $order_id, '商城订单');
                if ($retval_platform_money < 0) {
                    $presell_order_model->rollback();
                    return ORDER_CREATE_LOW_PLATFORM_MONEY;
                }
            }
            
            $presell_order_model->commit();
            return $presell_order_id;
        } catch (\Exception $e) {
            $presell_order_model->rollback();
            dump($e->getMessage());
            return $e->getMessage();
        }
    }

    /**
     * 订单创建
     * （积分兑换 虚拟商品）
     */
    public function orderCreateVirtualPointExhange($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $point, $coupon_id, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $user_telephone, $coin, $point_exchange_type)
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
            // 获取优惠券金额
            $coupon = new MemberCoupon();
            $coupon_money = $coupon->getCouponMoney($coupon_id);
            
            // 获取购买人信息
            $buyer = new UserModel();
            $buyer_info = $buyer->getInfo([
                'uid' => $this->uid
            ], 'nick_name');
            // 订单商品费用
            $goods_money = 0;
            if ($point_exchange_type == 1) {
                $goods_money = $order_goods_preference->getGoodsSkuListPrice($goods_sku_list);
            }
            // 积分兑换抵用金额
            $account_flow = new MemberAccount();
            
            $point_money = 0;
            
            // 订单来源
            if (isWechatApplet($this->uid)) {
                $order_from = 4; // 微信小程序
            } elseif (isWeixin()) {
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
            // 订单满减送活动优惠
            $goods_mansong = new GoodsMansong();
            $mansong_array = $goods_mansong->getGoodsSkuListMansong($goods_sku_list);
            $promotion_money = 0;
            $mansong_rule_array = array();
            $mansong_discount_array = array();
            if (! empty($mansong_array)) {
                foreach ($mansong_array as $k_mansong => $v_mansong) {
                    foreach ($v_mansong['discount_detail'] as $k_rule => $v_rule) {
                        $rule = $v_rule[1];
                        $discount_money_detail = explode(':', $rule);
                        $mansong_discount_array[] = array(
                            $discount_money_detail[0],
                            $discount_money_detail[1],
                            $v_rule[0]['rule_id']
                        );
                        $promotion_money += $discount_money_detail[1]; // round($discount_money_detail[1],2);
                        $mansong_rule_array[] = $v_rule[0];
                    }
                }
                $promotion_money = round($promotion_money, 2);
            }
            
            // 订单费用(具体计算)
            $order_money = $goods_money - $promotion_money - $coupon_money;
            
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
            
            $pay_money = $order_money - $user_money - $platform_money;
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
                'shipping_time' => getTimeTurnTimeStamp($shipping_time), // datetime NOT NULL COMMENT '买家要求配送时间',
                'receiver_mobile' => $user_telephone, // varchar(11) NOT NULL DEFAULT '' COMMENT '收货人的手机号码',
                'receiver_province' => '', // int(11) NOT NULL COMMENT '收货人所在省',
                'receiver_city' => '', // int(11) NOT NULL COMMENT '收货人所在城市',
                'receiver_district' => '', // int(11) NOT NULL COMMENT '收货人所在街道',
                'receiver_address' => '', // varchar(255) NOT NULL DEFAULT '' COMMENT '收货人详细地址',
                'receiver_zip' => '', // varchar(6) NOT NULL DEFAULT '' COMMENT '收货人邮编',
                'receiver_name' => '', // varchar(50) NOT NULL DEFAULT '' COMMENT '收货人姓名',
                'shop_id' => $shop_id, // int(11) NOT NULL COMMENT '卖家店铺id',
                'shop_name' => $shop_name, // varchar(100) NOT NULL DEFAULT '' COMMENT '卖家店铺名称',
                'goods_money' => $goods_money, // decimal(19, 2) NOT NULL COMMENT '商品总价',
                'tax_money' => $tax_money, // 税费
                'order_money' => $order_money, // decimal(10, 2) NOT NULL COMMENT '订单总价',
                'point' => $point, // int(11) NOT NULL COMMENT '订单消耗积分',
                'point_money' => $point_money, // decimal(10, 2) NOT NULL COMMENT '订单消耗积分抵多少钱',
                'coupon_money' => $coupon_money, // _money decimal(10, 2) NOT NULL COMMENT '订单代金券支付金额',
                'coupon_id' => $coupon_id, // int(11) NOT NULL COMMENT '订单代金券id',
                'user_money' => $user_money, // decimal(10, 2) NOT NULL COMMENT '订单预存款支付金额',
                'promotion_money' => $promotion_money, // decimal(10, 2) NOT NULL COMMENT '订单优惠活动金额',
                'shipping_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单运费',
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
                'fixed_telephone' => ""
            ); // 固定电话
               // datetime NOT NULL DEFAULT 'CURRENT_TIMESTAMP' COMMENT '订单创建时间',
            if ($pay_status == 2) {
                $data_order["pay_time"] = time();
            }
            $order = new NsOrderModel();
            $order->save($data_order);
            $order_id = $order->order_id;
            $pay = new UnifyPay();
            $pay->createPayment($shop_id, $out_trade_no, $shop_name . "虚拟订单", $shop_name . "虚拟订单", $pay_money, 1, $order_id);
            // 满减送详情，添加满减送活动优惠情况
            if (! empty($mansong_rule_array)) {
                
                $mansong_rule_array = array_unique($mansong_rule_array);
                foreach ($mansong_rule_array as $k_mansong_rule => $v_mansong_rule) {
                    $order_promotion_details = new NsOrderPromotionDetailsModel();
                    $data_promotion_details = array(
                        'order_id' => $order_id,
                        'promotion_id' => $v_mansong_rule['rule_id'],
                        'promotion_type_id' => 1,
                        'promotion_type' => 'MANJIAN',
                        'promotion_name' => '满减送活动',
                        'promotion_condition' => '满' . $v_mansong_rule['price'] . '元，减' . $v_mansong_rule['discount'],
                        'discount_money' => $v_mansong_rule['discount'],
                        'used_time' => time()
                    );
                    $order_promotion_details->save($data_promotion_details);
                }
                // 添加到对应商品项优惠满减
                if (! empty($mansong_discount_array)) {
                    foreach ($mansong_discount_array as $k => $v) {
                        $order_goods_promotion_details = new NsOrderGoodsPromotionDetailsModel();
                        $data_details = array(
                            'order_id' => $order_id,
                            'promotion_id' => $v[2],
                            'sku_id' => $v[0],
                            'promotion_type' => 'MANJIAN',
                            'discount_money' => $v[1],
                            'used_time' => time()
                        );
                        $order_goods_promotion_details->save($data_details);
                    }
                }
            }
            // 添加到对应商品项优惠优惠券使用详情
            if ($coupon_id > 0) {
                $coupon_details_array = $order_goods_preference->getGoodsCouponPromoteDetail($coupon_id, $coupon_money, $goods_sku_list);
                foreach ($coupon_details_array as $k => $v) {
                    $order_goods_promotion_details = new NsOrderGoodsPromotionDetailsModel();
                    $data_details = array(
                        'order_id' => $order_id,
                        'promotion_id' => $coupon_id,
                        'sku_id' => $v['sku_id'],
                        'promotion_type' => 'COUPON',
                        'discount_money' => $v['money'],
                        'used_time' => time()
                    );
                    $order_goods_promotion_details->save($data_details);
                }
            }
            // 使用积分
            if ($point > 0) {
                $retval_point = $account_flow->addMemberAccountData($shop_id, 1, $this->uid, 0, $point * (- 1), 1, $order_id, '商城虚拟订单');
                if ($retval_point < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_POINT;
                }
            }
            if ($coin > 0) {
                $retval_point = $account_flow->addMemberAccountData($shop_id, 3, $this->uid, 0, $coin * (- 1), 1, $order_id, '商城虚拟订单');
                if ($retval_point < 0) {
                    $this->order->rollback();
                    return LOW_COIN;
                }
            }
            if ($user_money > 0) {
                $retval_user_money = $account_flow->addMemberAccountData($shop_id, 2, $this->uid, 0, $user_money * (- 1), 1, $order_id, '商城虚拟订单');
                if ($retval_user_money < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_USER_MONEY;
                }
            }
            if ($platform_money > 0) {
                $retval_platform_money = $account_flow->addMemberAccountData(0, 2, $this->uid, 0, $platform_money * (- 1), 1, $order_id, '商城虚拟订单');
                if ($retval_platform_money < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_PLATFORM_MONEY;
                }
            }
            // 使用优惠券
            if ($coupon_id > 0) {
                $retval = $coupon->useCoupon($this->uid, $coupon_id, $order_id);
                if (! ($retval > 0)) {
                    $this->order->rollback();
                    return $retval;
                }
            }
            // 添加订单项
            $order_goods = new OrderGoods();
            $res_order_goods = $order_goods->addOrderGoods($order_id, $goods_sku_list);
            if (! ($res_order_goods > 0)) {
                $this->order->rollback();
                return $res_order_goods;
            }
            $this->addOrderAction($order_id, $this->uid, '创建虚拟订单');
            
            $this->order->commit();
            return $order_id;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 订单支付
     *
     * @param unknown $order_pay_no            
     * @param unknown $pay_type(10:线下支付)            
     * @param unknown $status
     *            0:订单支付完成 1：订单交易完成
     * @return Exception
     */
    public function OrderPay($order_pay_no, $pay_type, $status)
    {
        $this->order->startTrans();
        try {
            // 添加订单日志
            // 可能是多个订单
            $order_id_array = $this->order->where(['out_trade_no' => $order_pay_no,'order_status' => 0])->column('order_id');
            // 检测是否支持拼团版本
            $is_support_pintuan = IS_SUPPORT_PINTUAN;
            $account = new MemberAccount();
            foreach ($order_id_array as $k => $order_id) {
            	
                if ($is_support_pintuan) {
                    $order_info = $this->order->getInfo([
                        'order_id' => $order_id
                    ], 'order_money,buyer_id,pay_money,order_type,order_no,tuangou_group_id,shipping_type');
                    // 如果该订单是拼团订单
                    if($order_info['order_type'] == 4){
                        // 拼团支付限制
                        $tuangou_group = new NsTuangouGroupModel();
                        $pingtuan_info = $tuangou_group->getInfo(['group_id'=>$order_info['tuangou_group_id']], "tuangou_num,status,group_uid,real_num");
                        
                        $condition_1['order_status'] = ["in","1,2,3,4"];
                        $condition_1['tuangou_group_id'] = $order_info['tuangou_group_id'];
                        
                        $order_list_count =  $this->order->getCount($condition_1);
                        
                        if($pingtuan_info['tuangou_num'] <= $order_list_count || !in_array($pingtuan_info['status'], [0, 1])){
                            $this->order->rollback();
                            return 0;
                        }
                        //拼团支付成功后的后续操作
                        $this->pintuanPaySuccessAction($order_info);
                    }
                } else {
                    $order_info = $this->order->getInfo([
                        'order_id' => $order_id
                    ], 'order_money,buyer_id,pay_money,order_type,order_no,shipping_type');
                }
                
                // 修改订单状态
                $data = array(
                    'payment_type' => $pay_type,
                    'pay_status' => 2,
                    'pay_time' => time(),
                    'order_status' => 1
                ); // 订单转为待发货状态
                
                // 如果该订单为货到付款的话该订单的支付状态仍为未支付
                if($pay_type == 4){
                    $data['pay_status'] = 0;
                }
                // 如果订单配送方式是自提的话在支付完成后生成自提码
                if($order_info['shipping_type'] == 2){
                    $ns_order_pickup = new NsOrderPickupModel();
                    $pickup_code = $this->getPickupCode(0);
                    $ns_order_pickup -> save([
                        'picked_up_code' => $pickup_code
                    ],[
                        'order_id' => $order_id
                    ]);
                }
                
                $order = new NsOrderModel();
                $order->save($data, [
                    'order_id' => $order_id
                ]);
                //添加订单操作日志
                if ($pay_type == 10) {
                    // 线下支付
                    $this->addOrderAction($order_id, $this->uid, '线下支付');
                } else {
                    // 查询订单购买人ID
                
                    $this->addOrderAction($order_id, $order_info['buyer_id'], '订单支付');
                }
                // 增加会员累计消费
                $account->addMmemberConsum(0, $order_info['buyer_id'], $order_info['order_money']);
            }
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            $this->order->rollback();
            Log::write("订单支付出错" . $e->getMessage());
            return $e->getMessage();
        }
    }
    
    /**
     * 拼团支付成功之后的操作
     * @param unknown $order_info
     */
    private function pintuanPaySuccessAction($order_info){
        if($order_info['tuangou_group_id'] > 0){
            $tuangou_group = new NsTuangouGroupModel();
            $pingtuan_info = $tuangou_group->getInfo(['group_id'=>$order_info['tuangou_group_id']], "tuangou_num,status,group_uid,real_num");
        
            // 支付成功 调用短信邮箱通知钩子 改变拼团状态
            if($pingtuan_info['group_uid'] == $order_info['buyer_id'] && $pingtuan_info['real_num'] == 0){
                // 支付成功后修改状态
                $tuangou_group -> save(['status' => 1], ['group_id' => $order_info['tuangou_group_id']]);
                // 拼团发起通知用户
                runhook("Notify", "openGroupNoticeUser", [
                    'pintuan_group_id' => $order_info['tuangou_group_id'],
                    'order_no' => $order_info['order_no']
                ]);
                // 拼团发起通知商家
                runhook("Notify", "openGroupNoticeBusiness", [
                    'pintuan_group_id' => $order_info['tuangou_group_id'],
                    'order_no' => $order_info['order_no']
                ]);
                // 拼团成功微信模板消息
                hook('openGroupNotice', [
                    'pintuan_group_id' => $order_info['tuangou_group_id']
                ]);
            }else{
                // 拼团参与通知
                runhook("Notify", "addGroupNoticeUser", [
                    'pintuan_group_id' => $order_info['tuangou_group_id'],
                    'order_no' => $order_info['order_no'],
                    'uid' => $order_info['buyer_id']
                ]);
                // 拼团参团微信模板消息
                hook('addGroupNotice', [
                    'pintuan_group_id' => $order_info['tuangou_group_id'],
                    'uid' => $order_info['buyer_id']
                ]);
            }
        }
    }
    
    /**
     * 预售订单支付
     * 
     * @param unknown $order_pay_no            
     * @param unknown $pay_type            
     * @param unknown $status            
     */
    public function presellOrderPay($order_pay_no, $pay_type, $status = 1)
    {
        $presell_order_model = new NsOrderPresellModel();
        $presell_order_model->startTrans();
        
        try {
            
            $order_data = $presell_order_model->getInfo([
                'out_trade_no' => $order_pay_no
            ], '*');
            $presell_delivery_time = 0;
            
            if ($order_data['presell_delivery_type'] == 1) {
                $presell_delivery_time = $order_data['presell_delivery_value'];
            } else {
                $presell_delivery_time = Time::daysAfter($order_data['presell_delivery_value']);
            }
            
            // 修改订单状态
            $data = array(
                'payment_type' => $pay_type,
                'pay_time' => time(),
                'order_status' => $status,
                'presell_delivery_time' => $presell_delivery_time
            ); // 订单转为待发货状态
            
            $res = $presell_order_model->save($data, ['out_trade_no' => $order_pay_no]);
            
            $order_model = new NsOrderModel();
            $order_model->save(['order_status' => 7], ['order_id' => $order_data['relate_id']]);
            
            if ($pay_type == 10) {
                // 线下支付
                $this->addOrderAction($order_data['relate_id'], $this->uid, '预售金线下支付');
            } else {
                // 查询订单购买人ID
                
                $this->addOrderAction($order_data['relate_id'], $this->uid, '预售金支付');
            }
            
            $presell_order_model->commit();
            return $res;
        } catch (\Exception $e) {
            Log::write('预售订单支付失败' . $e->getMessage());
            $presell_order_model->rollback();
        }
    }

    /**
     * 虚拟订单，生成虚拟商品
     * 1、根据订单id查询订单项(虚拟订单项只会有一条数据)
     * 2、根据购买的商品获取虚拟商品类型信息
     * 3、根据购买的商品数量添加相应的虚拟商品数量
     */
    public function virtualOrderOperation($order_id, $buyer_nickname, $order_no)
    {
        $order_goods_model = new NsOrderGoodsModel();
        // 查询订单项信息
        $order_goods_items = $order_goods_model->getInfo([
            'order_id' => $order_id
        ], 'order_goods_id,goods_id,goods_name,buyer_id,num,goods_money,price');
        $res = 0;
        if (! empty($order_goods_items)) {
            $virtual_goods = new VirtualGoods();
            $goods_model = new NsGoodsModel();
            // 根据goods_id查询虚拟商品类型
            $virtual_goods_type_id = $goods_model->getInfo([
                'goods_id' => $order_goods_items['goods_id']
            ], 'virtual_goods_type_id');
            $goods_id = $order_goods_items['goods_id'];
            $virtual_goods_type_info = $virtual_goods->getVirtualGoodsTypeInfo([
                'relate_goods_id' => $goods_id
            ]);
            if (! empty($virtual_goods_type_info)) {
                
                // 生成虚拟商品
                for ($i = 0; $i < $order_goods_items['num']; $i ++) {
                    
                    $virtual_goods_name = $order_goods_items['goods_name']; // 虚拟商品名称
                    $money = $order_goods_items['price']; // 虚拟商品金额
                    $buyer_id = $order_goods_items['buyer_id']; // 买家id
                    $order_goods_id = $order_goods_items['order_goods_id']; // 关联订单项id
                    $validity_period = $virtual_goods_type_info['validity_period']; // 有效期至
                    $start_time = time();
                    if ($validity_period == 0) {
                        $end_time = 0;
                    } else {
                        $end_time = strtotime("+$validity_period days");
                    }
                    $use_number = 0; // 使用次数，刚添加的默认0
                    $confine_use_number = $virtual_goods_type_info['confine_use_number'];
                    $use_status = 0; // (-1:已失效,0:未使用,1:已使用)
                    
                    $remark = '';
                    $virtual_goods_group_id = $virtual_goods_type_info['virtual_goods_group_id'];
                    // 如果是点卡的话就是原因的发放即可
                    if ($virtual_goods_group_id == 3) {
                        
                        $virtual_goods_model = new NsVirtualGoodsModel();
                        $virtual_goods_id = $virtual_goods_model->getInfo([
                            'use_status' => - 2,
                            'goods_id' => $goods_id
                        ], 'virtual_goods_id')['virtual_goods_id'];
                        $res = $virtual_goods->updateVirtualGoods($virtual_goods_id, $virtual_goods_name, $money, $buyer_id, $buyer_nickname, $order_goods_id, $order_no, $start_time, $end_time);
                    } else {
                        
                        $value_array = json_decode($virtual_goods_type_info['value_info'], true);
                        // 如果不是网上服务的话，需要拼接虚拟商品说明信息
                        if ($virtual_goods_group_id == 2) {
                            
                            $remark = "网盘地址：" . $value_array[0]['cloud_address'] . "&nbsp;&nbsp;&nbsp;网盘密码：" . $value_array[0]['cloud_password'];
                            $use_status = 1;
                        } elseif ($virtual_goods_group_id == 4) {
                            $remark = "<a href='" . __IMG($value_array[0]['download_resources']) . "'>点此下载</a>" . "&nbsp;&nbsp;&nbsp;解压密码：" . $value_array[0]['unzip_password'];
                            $use_status = 1;
                        }
                        $res = $virtual_goods->addVirtualGoods($this->instance_id, $virtual_goods_name, $money, $buyer_id, $buyer_nickname, $order_goods_id, $order_no, $validity_period, $start_time, $end_time, $use_number, $confine_use_number, $use_status, $order_goods_items['goods_id'], $remark);
                    }
                }
            }
        }
        return $res;
    }

    /**
     * 添加订单操作日志
     * order_id int(11) NOT NULL COMMENT '订单id',
     * action varchar(255) NOT NULL DEFAULT '' COMMENT '动作内容',
     * uid int(11) NOT NULL DEFAULT 0 COMMENT '操作人id',
     * user_name varchar(50) NOT NULL DEFAULT '' COMMENT '操作人',
     * order_status int(11) NOT NULL COMMENT '订单大状态',
     * order_status_text varchar(255) NOT NULL DEFAULT '' COMMENT '订单状态名称',
     * action_time datetime NOT NULL COMMENT '操作时间',
     * PRIMARY KEY (action_id)
     *
     * @param unknown $order_id            
     * @param unknown $uid            
     * @param unknown $action_text            
     */
    public function addOrderAction($order_id, $uid, $action_text)
    {
        $this->order->startTrans();
        try {
            $order_status = $this->order->getInfo([
                'order_id' => $order_id
            ], 'order_status');
            if ($uid != 0) {
                $user = new UserModel();
                $user_name = $user->getInfo([
                    'uid' => $uid
                ], 'nick_name');
                $action_name = $user_name['nick_name'];
            } else {
                $action_name = 'system';
            }
            
            $data_log = array(
                'order_id' => $order_id,
                'action' => $action_text,
                'uid' => $uid,
                'user_name' => $action_name,
                'order_status' => $order_status['order_status'],
                'order_status_text' => $this->getOrderStatusName($order_id),
                'action_time' => time()
            );
            $order_action = new NsOrderActionModel();
            $order_action->save($data_log);
            $this->order->commit();
            return $order_action->action_id;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 获取订单当前状态 名称
     *
     * @param unknown $order_id            
     */
    public function getOrderStatusName($order_id)
    {
        $order_status = $this->order->getInfo([
            'order_id' => $order_id
        ], 'order_status');
        $status_array = OrderStatus::getOrderCommonStatus();
        foreach ($status_array as $k => $v) {
            if ($v['status_id'] == $order_status['order_status']) {
                return $v['status_name'];
            }
        }
        return false;
    }

    /**
     * 通过店铺id 得到订单的订单号
     *
     * @param unknown $shop_id            
     */
    public function createOrderNo_ext($shop_id)
    {
        $time_str = date('YmdHi');
        $order_model = new NsOrderModel();
        $order_obj = $order_model->getFirstData([
            "shop_id" => $shop_id,
            "order_no" => array(
                "like",
                substr($time_str, 0, 12) . "%"
            )
        ], "order_id DESC");
        $num = 0;
        if (! empty($order_obj)) {
            $order_no_max = $order_obj["order_no"];
            if (empty($order_no_max)) {
                $num = 1;
            } else {
                if (substr($time_str, 0, 12) == substr($order_no_max, 0, 12)) {
                    $max_no = substr($order_no_max, 12, 4);
                    $num = $max_no * 1 + 1;
                } else {
                    $num = 1;
                }
            }
        } else {
            $num = 1;
        }
        $order_no = $time_str . sprintf("%04d", $num);
        $count = $order_model->getCount([
            'order_no' => $order_no
        ]);
        if ($count > 0) {
            return $this->createOrderNo($shop_id);
        }
        return $order_no;
    }
    public function createOrderNo($shop_id)
    {
        $time_str = date('YmdHi');
        $num = 0;
        $max_no=Cache::get($shop_id."_".$time_str);
        if(!isset($max_no) || empty($max_no)){
            $max_no=1;
        }else{
            $max_no=$max_no+1;
        }
        $order_no = $time_str . sprintf("%04d", $max_no);
        Cache::set($shop_id."_".$time_str, $max_no);
        return $order_no;
    }

    /**
     * 创建订单支付编号
     *
     * @param unknown $order_id            
     */
    public function createOutTradeNo()
    {
        $pay_no = new UnifyPay();
        return $pay_no->createOutTradeNo();
    }

    /**
     * 订单重新生成订单号
     *
     * @param unknown $orderid            
     */
    public function createNewOutTradeNo($orderid)
    {
        $order = new NsOrderModel();
        $new_no = $this->createOutTradeNo();
        $data = array(
            'out_trade_no' => $new_no
        );
        $retval = $order->save($data, [
            'order_id' => $orderid
        ]);
        if ($retval) {
            return $new_no;
        } else {
            return '';
        }
    }

    /**
     * 预售订单重新生成订单号
     * 
     * @param unknown $presell_order_id            
     */
    public function createNewOutTradeNoPresell($presell_order_id)
    {
        $presell_order = new NsOrderPresellModel();
        $new_no = $this->createOutTradeNo();
        $data = array(
            'out_trade_no' => $new_no
        );
        $retval = $presell_order->save($data, [
            'presell_order_id' => $presell_order_id
        ]);
        if ($retval) {
            return $new_no;
        } else {
            return '';
        }
    }

    /**
     * 订单发货(整体发货)(不考虑订单项)
     *
     * @param unknown $orderid            
     */
    public function orderDoDelivery($orderid)
    {
        $this->order->startTrans();
        try {
            $order_item = new NsOrderGoodsModel();
            $count = $order_item->getCount([
                'order_id' => $orderid,
                'shipping_status' => 0,
                'refund_status' => array(
                    'ELT',
                    0
                )
            ]);
            if ($count == 0) {
                $data_delivery = array(
                    'shipping_status' => 1,
                    'order_status' => 2,
                    'consign_time' => time()
                );
                $order_model = new NsOrderModel();
                $order_model->save($data_delivery, [
                    'order_id' => $orderid
                ]);
                $this->addOrderAction($orderid, $this->uid, '订单发货');
            }
            
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 订单收货
     *
     * @param unknown $orderid            
     */
    public function OrderTakeDelivery($orderid)
    {
        $this->order->startTrans();
        try {
            $data_take_delivery = array(
                'shipping_status' => 2,
                'order_status' => 3,
                'sign_time' => time(),
                'pay_status' => 2
            );
            $order_model = new NsOrderModel();
            $order_model->save($data_take_delivery, [
                'order_id' => $orderid
            ]);
            $this->addOrderAction($orderid, $this->uid, '订单收货');
            // 判断是否需要在本阶段赠送积分
            $this->giveGoodsOrderPoint($orderid, 2);
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 订单自动收货
     *
     * @param unknown $orderid            
     */
    public function orderAutoDelivery($orderid)
    {
        $this->order->startTrans();
        try {
            $data_take_delivery = array(
                'shipping_status' => 2,
                'order_status' => 3,
                'sign_time' => time()
            );
            $order_model = new NsOrderModel();
            $order_model->save($data_take_delivery, [
                'order_id' => $orderid
            ]);
            
            $this->addOrderAction($orderid, 0, '订单自动收货');
            // 判断是否需要在本阶段赠送积分
            $this->giveGoodsOrderPoint($orderid, 2);
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 执行订单交易完成
     *
     * @param unknown $orderid            
     */
    public function orderComplete($orderid)
    {
        $this->order->startTrans();
        try {
            $data_complete = array(
                'order_status' => 4,
                "finish_time" => time()
            );
            $order_model = new NsOrderModel();
            $order_model->save($data_complete, [
                'order_id' => $orderid
            ]);
            $this->addOrderAction($orderid, $this->uid, '交易完成');
            $this->calculateOrderMansong($orderid);
            // 判断是否需要在本阶段赠送积分
            $this->giveGoodsOrderPoint($orderid, 1);
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 统计订单完成后赠送用户积分
     *
     * @param unknown $order_id            
     */
    private function calculateOrderGivePoint($order_id)
    {
        $point = $this->order->getInfo([
            'order_id' => $order_id
        ], 'shop_id, give_point,buyer_id');
        $member_account = new MemberAccount();
        $member_account->addMemberAccountData($point['shop_id'], 1, $point['buyer_id'], 1, $point['give_point'], 1, $order_id, '订单商品赠送积分');
    }

    /**
     * 订单完成后统计满减送赠送
     *
     * @param unknown $order_id            
     */
    private function calculateOrderMansong($order_id)
    {
        $order_info = $this->order->getInfo([
            'order_id' => $order_id
        ], 'shop_id, buyer_id');
        $order_promotion_details = new NsOrderPromotionDetailsModel();
        // 查询满减送活动规则
        $list = $order_promotion_details->getQuery([
            'order_id' => $order_id,
            'promotion_type_id' => 1
        ], 'promotion_id', '');
        if (! empty($list)) {
            $promotion_mansong_rule = new NsPromotionMansongRuleModel();
            foreach ($list as $k => $v) {
                $mansong_data = $promotion_mansong_rule->getInfo([
                    'rule_id' => $v['promotion_id']
                ], 'give_coupon,give_point');
                if (! empty($mansong_data)) {
                    // 满减送赠送积分
                    if ($mansong_data['give_point'] != 0) {
                        $member_account = new MemberAccount();
                        $member_account->addMemberAccountData($order_info['shop_id'], 1, $order_info['buyer_id'], 1, $mansong_data['give_point'], 1, $order_id, '订单满减送赠送积分');
                    }
                    // 满减送赠送优惠券
                    if ($mansong_data['give_coupon'] != 0) {
                        $member_coupon = new MemberCoupon();
                        $member_coupon->UserAchieveCoupon($order_info['buyer_id'], $mansong_data['give_coupon'], 1);
                    }
                }
            }
        }
    }

    /**
     * 订单执行交易关闭
     *
     * @param unknown $orderid            
     * @return Exception
     */
    public function orderClose($orderid)
    {
        $this->order->startTrans();
        try {
            $order_info = $this->order->getInfo([
                'order_id' => $orderid
            ], 'order_status,pay_status,point, coupon_id, user_money, buyer_id,shop_id,user_platform_money, coin_money,order_type,out_trade_no,payment_type');
            
            if((in_array($order_info['order_status'], [1, 2, 3]) && $order_info['payment_type'] != 4) || (in_array($order_info['order_status'], [2, 3]) && $order_info['payment_type']== 4) ){
                return 0;
            }
            if ($order_info['order_status'] == 5) {
                $this->order->commit();
                return 1;
            }
            // 如果该订单为预售订单 且已支付的预售金的话则不关闭
            if($order_info['order_type'] == 6 && $order_info['order_status'] == 7){
                $this->order->commit();
                return 1;
            }
            $data_close = array(
                'order_status' => 5
            );
            $order_model = new NsOrderModel();
            $order_model->save($data_close, [
                'order_id' => $orderid
            ]);
            
            $pay = new NsOrderPaymentModel();
            $payInfo = $pay -> getInfo([
                'out_trade_no' => $order_info['out_trade_no']
            ], 'balance_money');
            
            $account_flow = new MemberAccount();
            if ($order_info['order_status'] == 0 || ($order_info['order_status'] == 6 && $order_info['order_type'] == 6)) {
                // 会员余额返还
                if ($order_info['user_money'] > 0) {
                    $account_flow->addMemberAccountData($order_info['shop_id'], 2, $order_info['buyer_id'], 1, $order_info['user_money'], 2, $orderid, '订单关闭返还用户余额');
                }
                $balance_money = $payInfo['balance_money'];
                // 预售订单
                if($order_info['order_type'] == 6){
                    $order_presell = new NsOrderPresellModel();
                    $order_presell_info = $order_presell -> getInfo(["relate_id" => $orderid], "out_trade_no");
                    $order_presell_pay_info = $pay -> getInfo(['out_trade_no' => $order_presell_info["out_trade_no"]], 'balance_money');
                    if(!empty($order_presell_pay_info['balance_money'])){
                        $balance_money += $order_presell_pay_info['balance_money'];
                    }
                }
                
                // 平台余额返还
                if ($balance_money > 0) {
                    $account_flow->addMemberAccountData(0, 2, $order_info['buyer_id'], 1, $balance_money, 2, $orderid, '商城订单关闭返还锁定余额');
                }
            }
            
            // 积分返还
            if ($order_info['point'] > 0) {
                $account_flow->addMemberAccountData($order_info['shop_id'], 1, $order_info['buyer_id'], 1, $order_info['point'], 2, $orderid, '订单关闭返还积分');
            }
            if ($order_info['coin_money'] > 0) {
                $coin_convert_rate = $account_flow->getCoinConvertRate();
                $account_flow->addMemberAccountData($order_info['shop_id'], 3, $order_info['buyer_id'], 1, $order_info['coin_money'] / $coin_convert_rate, 2, $orderid, '订单关闭返还购物币');
            }
            
            // 优惠券返还
            $coupon = new MemberCoupon();
            if ($order_info['coupon_id'] > 0) {
                $coupon->UserReturnCoupon($order_info['coupon_id']);
            }
            // 退回库存
            $order_goods = new NsOrderGoodsModel();
            $order_goods_list = $order_goods->getQuery([
                'order_id' => $orderid
            ], '*', '');
            foreach ($order_goods_list as $k => $v) {
                $return_stock = 0;
                $goods_sku_model = new NsGoodsSkuModel();
                $goods_sku_info = $goods_sku_model->getInfo([
                    'sku_id' => $v['sku_id']
                ], 'goods_id, stock');
                if ($v['shipping_status'] != 1) {
                    // 卖家未发货
                    $return_stock = 1;
                } else {
                    // 卖家已发货,买家不退货
                    if ($v['refund_type'] == 1) {
                        $return_stock = 0;
                    } else {
                        $return_stock = 1;
                    }
                }
                // 销量返回
                $goods_model = new NsGoodsModel();
                $sales_info = $goods_model->getInfo([
                    'goods_id' => $goods_sku_info['goods_id']
                ], 'real_sales');
                $goods_model->save([
                    'real_sales' => $sales_info['real_sales'] - $v['num']
                ], [
                    "goods_id" => $goods_sku_info['goods_id']
                ]);
                // 退货返回库存
                if ($return_stock == 1) {
                    $data_goods_sku = array(
                        'stock' => $goods_sku_info['stock'] + $v['num']
                    );
                    $goods_sku_model->save($data_goods_sku, [
                        'sku_id' => $v['sku_id']
                    ]);
                    $count = $goods_sku_model->getSum([
                        'goods_id' => $goods_sku_info['goods_id']
                    ], 'stock');
                    // 商品库存增加
                    $goods_model = new NsGoodsModel();
                    
                    $goods_model->save([
                        'stock' => $count
                    ], [
                        "goods_id" => $goods_sku_info['goods_id']
                    ]);
                }
            }
            $this->addOrderAction($orderid, $this->uid, '交易关闭');
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            Log::write($e->getMessage());
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 订单状态变更
     *
     * @param unknown $order_id            
     * @param unknown $order_goods_id            
     */
    public function orderGoodsRefundFinish($order_id)
    {
        $orderInfo = NsOrderModel::get($order_id);
        $orderInfo->startTrans();
        try {
            $order_goods_model = new NsOrderGoodsModel();
            $total_count = $order_goods_model->where("order_id=$order_id")->count();
            $refunding_count = $order_goods_model->where("order_id=$order_id AND refund_status<>0 AND refund_status<>5 AND refund_status>0")->count();
            $refunded_count = $order_goods_model->where("order_id=$order_id AND refund_status=5")->count();
            $shipping_status = $orderInfo->shipping_status;
            $all_refund = 0;
            if ($refunding_count > 0) {
                
                $orderInfo->order_status = OrderStatus::getOrderCommonStatus()[6]['status_id']; // 退款中
            } elseif ($refunded_count == $total_count) {
                
                $all_refund = 1;
            } elseif ($shipping_status == OrderStatus::getShippingStatus()[0]['shipping_status']) {
                
                $orderInfo->order_status = OrderStatus::getOrderCommonStatus()[1]['status_id']; // 待发货
            } elseif ($shipping_status == OrderStatus::getShippingStatus()[1]['shipping_status']) {
                
                $orderInfo->order_status = OrderStatus::getOrderCommonStatus()[2]['status_id']; // 已发货
            } elseif ($shipping_status == OrderStatus::getShippingStatus()[2]['shipping_status']) {
                
                $orderInfo->order_status = OrderStatus::getOrderCommonStatus()[3]['status_id']; // 已收货
            }
            
            // 订单恢复正常操作
            if ($all_refund == 0) {
                $retval = $orderInfo->save();
                if ($refunding_count == 0) {
                    $this->orderDoDelivery($order_id);
                }
            } else {
                // 全部退款订单转化为交易关闭
                $retval = $this->orderClose($order_id);
            }
            
            $orderInfo->commit();
            return $retval;
        } catch (\Exception $e) {
            $orderInfo->rollback();
            return $e->getMessage();
        }
        
        return $retval;
    }

    /**
     * 获取订单详情
     *
     * @param unknown $order_id            
     */
    public function getDetail($order_id)
    {
        // 查询主表
        $order_detail = $this->order->getInfo([
            "order_id" => $order_id,
            "is_deleted" => 0
        ]);
        if (empty($order_detail)) {
            return array();
        }
        // 发票信息
        $temp_array = array();
        if ($order_detail["buyer_invoice"] != "") {
            $temp_array = explode("$", $order_detail["buyer_invoice"]);
        }
        $order_detail["buyer_invoice_info"] = $temp_array;
        if (empty($order_detail)) {
            return '';
        }
        $order_detail['payment_type_name'] = OrderStatus::getPayType($order_detail['payment_type']);
        $express_company_name = "";
        if ($order_detail['shipping_type'] == 1) {
            $order_detail['shipping_type_name'] = '商家配送';
            $express_company = new NsOrderExpressCompanyModel();
            
            $express_obj = $express_company->getInfo([
                "co_id" => $order_detail["shipping_company_id"]
            ], "company_name");
            if (! empty($express_obj["company_name"])) {
                $express_company_name = $express_obj["company_name"];
            }
        } elseif ($order_detail['shipping_type'] == 2) {
            $order_detail['shipping_type_name'] = '门店自提';
        } else {
            $order_detail['shipping_type_name'] = '';
        }
        $order_detail["shipping_company_name"] = $express_company_name;
        // 查询订单项表
        $order_detail['order_goods'] = $this->getOrderGoods($order_id);
        
        if ($order_detail['order_type'] == 6) {
            
            $order_status = OrderStatus::getOrderPresellStatus();
        } else {
            if ($order_detail['payment_type'] == 6 || $order_detail['shipping_type'] == 2) {
                $order_status = OrderStatus::getSinceOrderStatus();
            } else {
                // 查询操作项
                $order_status = OrderStatus::getOrderCommonStatus();
            }
        }
        
        // 查询订单提货信息表
        if ($order_detail['shipping_type'] == 2) {
            $order_pickup_model = new NsOrderPickupModel();
            $order_pickup_info = $order_pickup_model->getInfo([
                'order_id' => $order_id
            ], '*');
            $address = new Address();
            $order_pickup_info['province_name'] = $address->getProvinceName($order_pickup_info['province_id']);
            $order_pickup_info['city_name'] = $address->getCityName($order_pickup_info['city_id']);
            $order_pickup_info['district_name'] = $address->getDistrictName($order_pickup_info['district_id']);
            $order_detail['order_pickup'] = $order_pickup_info;
        } else {
            $order_detail['order_pickup'] = null;
        }
        // 查询订单操作
        foreach ($order_status as $k_status => $v_status) {
            
            if ($v_status['status_id'] == $order_detail['order_status']) {
                $order_detail['operation'] = $v_status['operation'];
                $order_detail['member_operation'] = $v_status['member_operation'];
                $order_detail['status_name'] = $v_status['status_name'];
            }
        }
        // 查询订单操作日志
        $order_action = new NsOrderActionModel();
        $order_action_log = $order_action->getQuery([
            'order_id' => $order_id
        ], '*', 'action_time desc');
        $order_detail['order_action'] = $order_action_log;
        
        $address_service = new Address();
        $order_detail['address'] = $order_detail["receiver_address"];
        return $order_detail;
    }

    /**
     * 查询订单的订单项列表
     *
     * @param unknown $order_id            
     */
    public function getOrderGoods($order_id, $refund = 0)
    {
        $order_goods = new NsOrderGoodsModel();
        /*  $order_goods_list = $order_goods->all([
            'order_id' => $order_id
        ]); */ 
        
        $where['order_id'] = $order_id;
        if($refund == 1){
        	$where['refund_status'] = array('<=', 0);        	
        }
        $order_goods_list = $order_goods->getQuery($where, '*', '', '');
        
        foreach ($order_goods_list as $k => $v) {
            $order_goods_list[$k]['express_info'] = $this->getOrderGoodsExpress($v['order_goods_id']);
            $shipping_status_array = OrderStatus::getShippingStatus();
            foreach ($shipping_status_array as $k_status => $v_status) {
                if ($v['shipping_status'] == $v_status['shipping_status']) {
                    $order_goods_list[$k]['shipping_status_name'] = $v_status['status_name'];
                }
            }
            // 商品图片
            $picture = new AlbumPictureModel();
            $picture_info = $picture->get($v['goods_picture']);
            $order_goods_list[$k]['picture_info'] = $picture_info;
            if ($v['refund_status'] != 0) {
                $order_refund_status = OrderStatus::getRefundStatus();
                foreach ($order_refund_status as $k_status => $v_status) {
                    
                    if ($v_status['status_id'] == $v['refund_status']) {
                        $order_goods_list[$k]['refund_operation'] = $v_status['refund_operation'];
                        $order_goods_list[$k]['status_name'] = $v_status['status_name'];
                    }
                }
            } else {
                $order_goods_list[$k]['refund_operation'] = null;
                $order_goods_list[$k]['status_name'] = '';
            }
        }
        return $order_goods_list;
    }

    /**
     * 获取订单的物流信息
     *
     * @param unknown $order_id            
     */
    public function getOrderExpress($order_id)
    {
        $order_goods_express = new NsOrderGoodsExpressModel();
        $order_express_list = $order_goods_express->all([
            'order_id' => $order_id
        ]);
        return $order_express_list;
    }

    /**
     * 获取订单项的物流信息
     *
     * @param unknown $order_goods_id            
     * @return multitype:|Ambigous
     */
    private function getOrderGoodsExpress($order_goods_id)
    {
        $order_goods = new NsOrderGoodsModel();
        $order_goods_info = $order_goods->getInfo([
            'order_goods_id' => $order_goods_id
        ], 'order_id,shipping_status');
        if ($order_goods_info['shipping_status'] == 0) {
            return null;
        } else {
            $order_express_list = $this->getOrderExpress($order_goods_info['order_id']);
            foreach ($order_express_list as $k => $v) {
                $order_goods_id_array = explode(",", $v['order_goods_id_array']);
                if (in_array($order_goods_id, $order_goods_id_array)) {
                    return $v;
                }
            }
            return null;
        }
    }

    /**
     * 订单价格调整
     *
     * @param unknown $order_id            
     * @param unknown $goods_money
     *            调整后的商品总价
     * @param unknown $shipping_fee
     *            调整后的运费
     */
    public function orderAdjustMoney($order_id, $goods_money, $shipping_fee)
    {
        $this->order->startTrans();
        try {
            $order_model = new NsOrderModel();
            $order_info = $order_model->getInfo([
                'order_id' => $order_id
            ], 'goods_money,shipping_money,order_money,pay_money');
            // 商品金额差额
            $goods_money_adjust = $goods_money - $order_info['goods_money'];
            $shipping_fee_adjust = $shipping_fee - $order_info['shipping_money'];
            $order_money = $order_info['order_money'] + $goods_money_adjust + $shipping_fee_adjust;
            $pay_money = $order_info['pay_money'] + $goods_money_adjust + $shipping_fee_adjust;
            $data = array(
                'goods_money' => $goods_money,
                'order_money' => $order_money,
                'shipping_money' => $shipping_fee,
                'pay_money' => $pay_money
            );
            $retval = $order_model->save($data, [
                'order_id' => $order_id
            ]);
            $this->addOrderAction($order_id, $this->uid, '调整金额');
            $this->order->commit();
            return $retval;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e;
        }
    }

    /**
     * 获取订单整体商品金额(根据订单项)
     *
     * @param unknown $order_id            
     */
    public function getOrderGoodsMoney($order_id)
    {
        $order_goods = new NsOrderGoodsModel();
        $money = $order_goods->getSum([
            'order_id' => $order_id
        ], 'goods_money');
        if (empty($money)) {
            $money = 0;
        }
        return $money;
    }

    /**
     * 获取订单赠品
     *
     * @param unknown $order_id            
     */
    public function getOrderPromotionGift($order_id)
    {
        $gift_list = array();
        $order_promotion_details = new NsOrderPromotionDetailsModel();
        $promotion_list = $order_promotion_details->getQuery([
            'order_id' => $order_id,
            'promotion_type_id' => 1
        ], 'promotion_id', '');
        if (! empty($promotion_list)) {
            foreach ($promotion_list as $k => $v) {
                $rule = new NsPromotionMansongRuleModel();
                $gift = $rule->getInfo([
                    'rule_id' => $v['promotion_id']
                ], 'gift_id');
                $gift_list[] = $gift['gift_id'];
            }
        }
        return $gift_list;
    }

    /**
     * 获取具体订单项信息
     *
     * @param unknown $order_goods_id
     *            订单项ID
     */
    public function getOrderGoodsInfo($order_goods_id)
    {
        $order_goods = new NsOrderGoodsModel();
        return $order_goods->getInfo([
            'order_goods_id' => $order_goods_id
        ], 'goods_id,goods_name,goods_money,goods_picture,shop_id');
    }

    /**
     * 通过订单id 得到该订单的世纪支付金额
     *
     * @param unknown $order_id            
     */
    public function getOrderRealPayMoney($order_id)
    {
        $order_goods_model = new NsOrderGoodsModel();
        // 查询订单的所有的订单项
        $order_goods_list = $order_goods_model->getQuery([
            "order_id" => $order_id
        ], "goods_money,adjust_money,refund_real_money", "");
        $order_real_money = 0;
        if (! empty($order_goods_list)) {
            $order_goods_promotion = new NsOrderGoodsPromotionDetailsModel();
            foreach ($order_goods_list as $k => $order_goods) {
                $promotion_money = $order_goods_promotion->getSum([
                    'order_id' => $order_id,
                    'sku_id' => $order_goods['sku_id']
                ], 'discount_money');
                if (empty($promotion_money)) {
                    $promotion_money = 0;
                }
                // 订单项的真实付款金额
                $order_goods_real_money = $order_goods['goods_money'] + $order_goods['adjust_money'] - $order_goods['refund_real_money'] - $promotion_money;
                // 订单付款金额
                $order_real_money = $order_real_money + $order_goods_real_money;
            }
        }
        return $order_real_money;
    }

    /**
     * 订单提货
     *
     * @param unknown $order_id            
     */
    public function pickupOrder($order_id, $buyer_name, $buyer_phone, $remark)
    {
        // 订单转为已收货状态
        $this->order->startTrans();
        try {
            $data_take_delivery = array(
                'shipping_status' => 2,
                'order_status' => 3,
                'sign_time' => time()
            );
            $order_model = new NsOrderModel();
            $order_model->save($data_take_delivery, [
                'order_id' => $order_id
            ]);
            $this->addOrderAction($order_id, $this->uid, '订单提货' . '提货人：' . $buyer_name . ' ' . $buyer_phone);
            // 记录提货信息
            $order_pickup_model = new NsOrderPickupModel();
            $data_pickup = array(
                'buyer_name' => $buyer_name,
                'buyer_mobile' => $buyer_phone,
                'remark' => $remark,
                'picked_up_status' => 1,
                'auditor_id' => $this->uid,
                'picked_up_time' => time()
            );
            $order_pickup_model->save($data_pickup, [
                'order_id' => $order_id
            ]);
            $order_goods_model = new NsOrderGoodsModel();
            $order_goods_model->save([
                'shipping_status' => 2
            ], [
                'order_id' => $order_id
            ]);
            $this->giveGoodsOrderPoint($order_id, 2);
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 订单发放
     *
     * @param unknown $order_id            
     */
    public function giveGoodsOrderPoint($order_id, $type)
    {
        // 判断是否需要在本阶段赠送积分
        $order_model = new NsOrderModel();
        $order_info = $order_model->getInfo([
            "order_id" => $order_id
        ], "give_point_type,shop_id,buyer_id,give_point,order_no");
        if ($order_info["give_point_type"] == $type) {
            if ($order_info["give_point"] > 0) {
                $member_account = new MemberAccount();
                $text = "";
                if ($order_info["give_point_type"] == 1) {
                    $text = "商城订单完成赠送积分,订单号：" . $order_info['order_no'];
                } elseif ($order_info["give_point_type"] == 2) {
                    $text = "商城订单完成收货赠送积分,订单号：" . $order_info['order_no'];
                } elseif ($order_info["give_point_type"] == 3) {
                    $text = "商城订单完成支付赠送积分,订单号：" . $order_info['order_no'];
                }
                $member_account->addMemberAccountData($order_info['shop_id'], 1, $order_info['buyer_id'], 1, $order_info['give_point'], 1, $order_id, $text);
            }
        }
    }

    /**
     * 添加订单退款账号记录
     * 创建时间：2017年10月18日 10:03:37 王永杰
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::addOrderRefundAccountRecords()
     */
    public function addOrderRefundAccountRecords($order_goods_id, $refund_trade_no, $refund_money, $refund_way, $buyer_id, $remark)
    {
        $model = new NsOrderRefundAccountRecordsModel();
        
        $data = array(
            'order_goods_id' => $order_goods_id,
            'refund_trade_no' => $refund_trade_no,
            'refund_money' => $refund_money,
            'refund_way' => $refund_way,
            'buyer_id' => $buyer_id,
            'refund_time' => time(),
            'remark' => $remark
        );
        $res = $model->save($data);
        return $res;
    }

    /**
     * 根据订单id查询赠品发放记录需要的信息
     * 创建时间：2018年1月25日11:51:33
     *
     * @param unknown $order_id            
     */
    public function addPromotionGiftGrantRecords($order_id, $uid, $nick_name)
    {
        $order_goods_model = new NsOrderGoodsModel(); // 订单项
        $gift_model = new NsPromotionGiftModel(); // 赠品活动
        $gift_goods_model = new NsPromotionGiftGoodsModel(); // 商品赠品
        $promotion = new Promotion();
        
        // 查询赠品订单项
        $list = $order_goods_model->getQuery([
            'order_id' => $order_id,
            'gift_flag' => [
                '>',
                0
            ]
        ], "order_goods_id,goods_id,goods_name,goods_picture,gift_flag,shop_id", "");
        if (! empty($list)) {
            foreach ($list as $k => $v) {
                
                // 查询赠品id，名称
                $gift_info = $gift_model->getInfo([
                    'gift_id' => $v['gift_flag']
                ], "gift_id,gift_name");
                
                if (! empty($gift_info)) {
                    
                    $type = 1;
                    $type_name = "满减";
                    $relate_id = $v['order_goods_id']; // 关联订单id
                    $remark = "满减送赠品";
                    $res = $promotion->addPromotionGiftGrantRecords($v['shop_id'], $uid, $nick_name, $gift_info['gift_id'], $gift_info['gift_name'], $v['goods_name'], $v['goods_picture'], $type, $type_name, $relate_id, $remark);
                    return $res;
                }
            }
        }
    }

    /**
     * 添加订单退款账号记录 售后
     * 创建时间：2017年10月18日 10:03:37 王永杰
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::addOrderRefundAccountRecords()
     */
    public function addOrderCustomerAccountRecords($order_goods_id, $refund_trade_no, $refund_money, $refund_way, $buyer_id, $remark)
    {
        $model = new NsOrderCustomerAccountRecordsModel();
        
        $data = array(
            'order_goods_id' => $order_goods_id,
            'refund_trade_no' => $refund_trade_no,
            'refund_money' => $refund_money,
            'refund_way' => $refund_way,
            'buyer_id' => $buyer_id,
            'refund_time' => time(),
            'remark' => $remark
        );
        $res = $model->save($data);
        return $res;
    }
    
    
    /**
     * 订单支付的后续操作
     *
     * @param unknown $order_pay_no
     * @param unknown $pay_type(10:线下支付)
     * @param unknown $status
     *            0:订单支付完成 1：订单交易完成
     * @return Exception
     */
    public function OrderPayFollowUpAction($order_pay_no, $pay_type, $status)
    {
        $this->order->startTrans();
        try {
            // 添加订单日志
            // 可能是多个订单
            $order_id_array = $this->order->where(['out_trade_no' => $order_pay_no,'order_status' => 1])->column('order_id');
            // 检测是否支持拼团版本
            $is_support_pintuan = IS_SUPPORT_PINTUAN;
            $account = new MemberAccount();
            foreach ($order_id_array as $k => $order_id) {
                if ($is_support_pintuan) {
                    $order_info = $this->order->getInfo([
                        'order_id' => $order_id
                    ], 'order_money,buyer_id,pay_money,order_type,order_no,tuangou_group_id');
                } else {
                    $order_info = $this->order->getInfo([
                        'order_id' => $order_id
                    ], 'order_money,buyer_id,pay_money,order_type,order_no');
                }
    
                
                Log::write("订单线上支付 积分");
                $user = new UserModel();
                $user_info = $user->getInfo(["uid" => $order_info['buyer_id']], "nick_name");
                if ($order_info['order_type'] == 2) {
                    // 虚拟商品，订单自动完成
                    $this->virtualOrderOperation($order_id, $user_info["nick_name"], $order_info['order_no']);
                    $order_service = new OrderService();
                    $res = $order_service->orderComplete($order_id);
                    if (! ($res > 0)) {
                        $this->order->rollback();
                        return $res;
                    }
                } elseif ($order_info['order_type'] == 4) {
                    /**
                     * ********************拼团 start**************************
                     */
                    // 拼团商品，要修改拼团
                    $pintuan = new Pintuan();
                    $res = $pintuan->tuangouGroupModify($order_info["tuangou_group_id"]);
                    if ($res == 0) {
                        $this->order->rollback();
                        $this->orderClose($order_id);
                        return TUANGOU_PAY_ERROR;
                    } else
                        if ($res == 1) {
                            // 如果支付成功,并且拼团未完成订单状态改为待成团
                            $order = new NsOrderModel();
                            $new_data = array(
                                "order_status" => 6
                            );
                            $order->save($new_data, [
                                'order_id' => $order_id
                            ]);
                        }
                    /**
                     * ********************拼团 end**************************
                     */
                } else {
    
                    // 根据订单id查询订单项中的赠品集合，添加赠品发放记录
                    $temp = $this->addPromotionGiftGrantRecords($order_id, $order_info['buyer_id'], $user_info["nick_name"]);
                    if ($status == 1) {
                        // 执行订单交易完成
                        $order_service = new OrderService();
                        $res = $order_service->orderComplete($order_id);
                        if (! ($res > 0)) {
                            $this->order->rollback();
                            return $res;
                        }
                    }
                }
            }
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e->getMessage();
        }
    }
    
    /**
     * 获取支付信息支付主体
     * @param unknown $shop_name
     * @param unknown $goods_sku_list
     */
    public function getPayBodyContent($shop_name, $goods_sku_list){
        $pay_body = $shop_name;
        try {
            $goods_sku_list_array = explode(",", $goods_sku_list);
            if(!empty($goods_sku_list_array)){
                $goods_sku = explode(':', $goods_sku_list_array[0]);
                $goods_sku_model = new NsGoodsSkuModel();
                $goods_sku_info = $goods_sku_model->getInfo(['sku_id' => $goods_sku[0]],"sku_name,goods_id");
                $goods_model = new NsGoodsModel();
                $goods_info = $goods_model -> getInfo(['goods_id' => $goods_sku_info['goods_id']], "goods_name");
                $first_goods_name = $goods_info["goods_name"];
                if(count($goods_sku_list_array) > 1){
                    $first_goods_name .= '等多件';
                }
                if(!empty($first_goods_name)){
                    $pay_body .= '-'.$first_goods_name;
                }
                $patterns = array('/&amp;/');
                $replacements = array('');
                $pay_body = preg_replace($patterns, $replacements, $pay_body);
                if(strlen($pay_body) > 127){
                    $num = round(127 / 3);
                    $pay_body = mb_substr($pay_body, 0, $num, 'UTF-8');
                    $pay_body .= '...';
                }
            }   
            return $pay_body;
        } catch (\Exception $e) {
            return $pay_body . '订单'; 
        }
    }
    
    /**
     * 获取自提码
     * @param unknown $shop_id
     */
    public function getPickupCode($shop_id){
        $pickup_code = substr(sha1(date('YmdHis').uniqid().$shop_id), 24);
        return $pickup_code;
    }
    
    /**
     * 自提点审核员确认提货成功
     * @param unknown $order_id
     * @param unknown $auditor_id
     * @return number
     */
    public function pickedUpAuditorConfirmPickup($order_id, $auditor_id,$buyer_name,$buyer_phone)
    {
        // 订单转为已收货状态
        $this->order->startTrans();
        try {
            $data_take_delivery = array(
                'shipping_status' => 2,
                'order_status' => 3,
                'sign_time' => time()
            );
            $order_model = new NsOrderModel();
            $order_model->save($data_take_delivery, [
                'order_id' => $order_id
            ]);
            $ns_picked_up_auditor_view = new NsPickedUpAuditorViewModel();
            $picked_up_auditor_info = $ns_picked_up_auditor_view -> getViewInfo(['npua.auditor_id'=> $auditor_id]);
            if(empty($picked_up_auditor_info)){
               return array(
                   'code' => -1,
                   'message' => '未获取该审核员的信息'
               );
               $this->order->rollback();
            }
            $auditor_name = $picked_up_auditor_info['nick_name'];
            $this->addOrderAction($order_id, $auditor_id, '订单提货' . ' 提货人：' . $buyer_name . ' ' . $buyer_phone .' 门店审核人员：'.$auditor_name.'确认用户提货');
            // 记录提货信息
            $order_pickup_model = new NsOrderPickupModel();
            $data_pickup = array(
                'buyer_name' => $buyer_name,
                'buyer_mobile' => $buyer_phone,
                'remark' => '',
                'picked_up_status' => 1,
                'auditor_id' => $auditor_id,
                'picked_up_time' => time()
            );
            $order_pickup_model->save($data_pickup, [
                'order_id' => $order_id
            ]);
            $order_goods_model = new NsOrderGoodsModel();
            $order_goods_model->save([
                'shipping_status' => 2
            ], [
                'order_id' => $order_id
            ]);
            $this->giveGoodsOrderPoint($order_id, 2);
            $this->order->commit();
            return array(
               'code' => 1,
               'message' => '提货成功'
            );
        } catch (\Exception $e) {
            $this->order->rollback();
            return array(
                'code' => -1,
                'message' => $e->getMessage()
            );
        }
    }
}