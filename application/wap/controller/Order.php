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
namespace app\wap\controller;

use data\model\AlbumPictureModel;
use data\model\NsCartModel;
use data\model\NsGoodsModel;
use data\service\Config;
use data\service\Express;
use data\service\Goods;
use data\service\Member;
use data\service\Member as MemberService;
use data\service\Order\Order as OrderOrderService;
use data\service\Order\OrderGoods;
use data\service\Order as OrderService;
use data\service\promotion\GoodsExpress as GoodsExpressService;
use data\service\promotion\GoodsMansong;
use data\service\Promotion;
use data\service\promotion\GoodsPreference;
use data\service\Shop;
use data\model\NsGoodsSkuModel;
use data\service\promotion\PromoteRewardRule;
use data\service\WebSite;
use data\service\Order\OrderGroupBuy;
use data\service\GroupBuy;
use Qiniu\json_decode;

/**
 * 订单控制器
 *
 * @author Administrator
 *        
 */
class Order extends BaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->checkLogin();
    }

    /**
     * 检测用户
     */
    private function checkLogin()
    {
        $uid = $this->uid;
        if (empty($uid)) {
            $redirect = __URL(__URL__ . "/wap/login");
            $this->redirect($redirect); // 用户未登录
        }
        $is_member = $this->user->getSessionUserIsMember();
        if (empty($is_member)) {
            $redirect = __URL(__URL__ . "/wap/login");
            $this->redirect($redirect); // 用户未登录
        }
    }

    /**
     * 待付款订单
     */
    public function paymentOrder()
    {
        $unpaid_goback = isset($_SESSION['unpaid_goback']) ? $_SESSION['unpaid_goback'] : '';
        $order_create_flag = isset($_SESSION['order_create_flag']) ? $_SESSION['order_create_flag'] : '';
        
        // 订单创建标识，表示当前生成的订单详情已经创建好了。用途：订单创建成功后，返回上一个界面的路径是当前创建订单的详情，而不是首页
        if (! empty($order_create_flag)) {
            $_SESSION['order_create_flag'] = "";
            $this->redirect($_SESSION['unpaid_goback']);
        }
        
        // 判断实物类型：实物商品，虚拟商品
        $order_tag = isset($_SESSION['order_tag']) ? $_SESSION['order_tag'] : "";
        
        if (empty($order_tag)) {
            $redirect = __URL(__URL__); // 没有商品返回到首页
            $this->redirect($redirect);
        }
        $order_goods_type = isset($_SESSION['order_goods_type']) ? $_SESSION['order_goods_type'] : "";
        $this->assign("order_goods_type", $order_goods_type);
        
        if ($order_tag == "buy_now" && $order_goods_type === "0") {
            // 虚拟商品
            $this->virtualOrderInfo();
            $order_tag = "virtual_goods";
        } elseif ($order_tag == "combination_packages") {
            // 组合套餐
            $this->comboPackageorderInfo();
        } elseif ($order_tag == "groupbuy") {
            // 团购
            $this->orderGroupBuyInfo();
        } elseif ($order_tag == "js_point_exchange") {
            // 积分兑换
            $this->pointExchangeOrderInfo();
        } else {
            // 实物商品
            $this->orderInfo();
        }
        $this->assign("order_tag", $order_tag); // 标识：立即购买还是购物车中进来的
        
        $web_config = new Config();
        //查询表支付宝配置
        $zfb_info = $web_config->getAlipayStatus($this->instance_id);
        $zfb_info = json_decode($zfb_info['value'], true);
        $this->assign("zfb_info", $zfb_info);
        $wx_info = $web_config->getWpayConfig($this->instance_id);
        $this->assign("wx_info", $wx_info);
        $yl_info = $web_config->getUnionpayConfig($this->instance_id);
        $this->assign("yl_info", $yl_info);
        // 配送时间段
        $config = new Config();
        $distribution_time_out = $config -> getConfig(0, "DISTRIBUTION_TIME_SLOT");
        if(!empty($distribution_time_out["value"])){
            $this->assign("distribution_time_out", $distribution_time_out["value"]);
        }else{
            $this->assign("distribution_time_out", "");
        }
        return view($this->style . 'Order/paymentOrder');
    }

    /**
     * 组装本地配送时间说明
     *
     * @return string
     */
    public function getDistributionTime()
    {
        $config_service = new Config();
        $distribution_time = $config_service->getDistributionTimeConfig($this->instance_id);
        if ($distribution_time == 0) {
            $time_desc = '';
        } else {
            $time_obj = json_decode($distribution_time['value'], true);
            if ($time_obj['morning_start'] != '' && $time_obj['morning_end'] != '') {
                $morning_time_desc = '上午' . $time_obj['morning_start'] . '&nbsp;至&nbsp;' . $time_obj['morning_end'] . '&nbsp;&nbsp;';
            } else {
                $morning_time_desc = '';
            }
            
            if ($time_obj['afternoon_start'] != '' && $time_obj['afternoon_end'] != '') {
                $afternoon_time_desc = '下午' . $time_obj['afternoon_start'] . '&nbsp;至&nbsp;' . $time_obj['afternoon_end'];
            } else {
                $afternoon_time_desc = '';
            }
            $time_desc = $morning_time_desc . $afternoon_time_desc;
        }
        return $time_desc;
    }

    /**
     * 待付款订单需要的数据
     * 2017年6月28日 15:24:48 王永杰
     */
    public function orderInfo()
    {
        $member = new MemberService();
        $order = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config = new Config();
        $promotion = new Promotion();
        $shop_service = new Shop();
        $goods_preference = new GoodsPreference(); // 商品优惠价格操作类
                                                   // 检测购物车
        $order_tag = $_SESSION['order_tag'];
        switch ($order_tag) {
            // 立即购买
            case "buy_now":
                $res = $this->buyNowSession();
                $goods_sku_list = $res["goods_sku_list"];
                $list = $res["list"];
                break;
            case "cart":
                
                // 加入购物车
                $res = $this->addShoppingCartSession();
                $goods_sku_list = $res["goods_sku_list"];
                $list = $res["list"];
                break;
            case "goods_presell":
                $res = $this->buyNowSession();
                $goods_sku_list = $res["goods_sku_list"];
                $list = $res["list"];
                
                $presell_money = $goods_preference->getGoodsPresell($res["goods_sku_list"]);
                $this->assign('presell_money', $presell_money);
                break;
        }
        $this->assign('goods_sku_list', $goods_sku_list);
        
        $discount_money = $goods_mansong->getGoodsMansongMoney($goods_sku_list);
        $this->assign("discount_money", sprintf("%.2f", $discount_money)); // 总优惠
        
        $count_money = $order->getGoodsSkuListPrice($goods_sku_list);
        $this->assign("count_money", sprintf("%.2f", $count_money)); // 商品金额
        
        $address = $member->getDefaultExpressAddress(); // 获取默认收货地址
        $express = 0;
        
        $express_company_list = array();
        $goods_express_service = new GoodsExpressService();
        if (! empty($address)) {
            // 物流公司
            $express_company_list = $goods_express_service->getExpressCompany($this->instance_id, $goods_sku_list, $address['province'], $address['city'], $address['district']);
            if (! empty($express_company_list)) {
                foreach ($express_company_list as $v) {
                    $express = $v['express_fee']; // 取第一个运费，初始化加载运费
                    break;
                }
            }
            $this->assign("address_is_have", 1);
            // 本地配送
            $o2o_distribution = $goods_express_service->getGoodsO2oPrice($count_money - $discount_money, 0, $address['province'], $address['city'], $address['district'], 0);
            
            if ($o2o_distribution >= 0) {
                $this->assign("o2o_distribution", $o2o_distribution);
                $this->assign("is_open_o2o_distribution", 1);
            } else {
                $this->assign("is_open_o2o_distribution", 0);
            }
        } else {
            $this->assign("address_is_have", 0);
            $this->assign("is_open_o2o_distribution", 0);
        }
        $count = $goods_express_service->getExpressCompanyCount($this->instance_id);
        $this->assign("express_company_count", $count); // 物流公司数量
        $this->assign("express", sprintf("%.2f", $express)); // 运费
        $this->assign("express_company_list", $express_company_list); // 物流公司
        
        $pick_up_money = $order->getPickupMoney($count_money);
        $this->assign("pick_up_money", $pick_up_money);
        
        $count_point_exchange = 0;
        
        $max_use_point = $goods_preference->getMaxUsePoint($goods_sku_list); // 最大可使用积分数
        
        foreach ($list as $k => $v) {
            $list[$k]['price'] = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
        }
        $this->assign("count_point_exchange", $count_point_exchange); // 总积分
        $this->assign("itemlist", $list);
        
        $shop_id = $this->instance_id;
        $shop_config = $Config->getShopConfig($shop_id);
        $order_invoice_content = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (! empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        $this->assign("shop_config", $shop_config); // 后台配置
        
        $member_account = $member->getMemberAccount($this->uid, $this->instance_id);
        if ($member_account['balance'] == '' || $member_account['balance'] == 0) {
            $member_account['balance'] = '0.00';
        }
        $this->assign("member_account", $member_account); // 用户余额
        
        if ($order_tag !== 'goods_presell') {
            $coupon_list = $order->getMemberCouponList($goods_sku_list);
        } else {
            $coupon_list = array();
        }
        $this->assign("coupon_list", $coupon_list); // 获取优惠券
        
        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        if (! empty($address)) {
            $no_mail = checkIdIsinIdArr($address['city'], $promotion_full_mail['no_mail_city_id_array']);
            if ($no_mail) {
                $promotion_full_mail['is_open'] = 0;
            }
        }
        $this->assign("promotion_full_mail", $promotion_full_mail); // 满额包邮
        
        $pickup_point_list = $shop_service->getPickupPointList();
        $pickup_point_list["data"] = $this->pickupPointListSort($address, $pickup_point_list["data"]);
        $this->assign("pickup_point_list", $pickup_point_list); // 自提地址列表
        
        
        $this->assign("address_default", $address);
        
        $goods_mansong_gifts = $this->getOrderGoodsMansongGifts($goods_sku_list);
        $this->assign("goods_mansong_gifts", $goods_mansong_gifts); // 赠品列表
                                                                    
        // 本地配送时间
        $distribution_time = $this->getDistributionTime();
        $this->assign('distribution_time', $distribution_time);
        
        $default_use_point = 0; // 默认使用积分数
        if ($member_account["point"] >= $max_use_point && $max_use_point != 0) {
            $default_use_point = $max_use_point;
        } else {
            $default_use_point = $member_account["point"];
        }
        // 积分配置
        $point_config = $promotion->getPointConfig();
        if ($max_use_point == 0) {
            $point_config["is_open"] = 0;
        }
        $this->assign("point_config", $point_config);
        $this->assign("max_use_point", $max_use_point);
        $this->assign("default_use_point", $default_use_point);
    }

    /**
     * 待付款订单需要的数据
     * 2017年6月28日 15:24:48 王永杰
     */
    public function virtualOrderInfo()
    {
        if ($this->getIsOpenVirtualGoodsConfig() == 0) {
            $this->error("未开启虚拟商品功能");
        }
        $member = new MemberService();
        $order = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config = new Config();
        $promotion = new Promotion();
        $shop_service = new Shop();
        $goods_preference = new GoodsPreference();
        $res = $this->buyNowSession();
        $goods_sku_list = $res["goods_sku_list"];
        $list = $res["list"];
        $shop_id = $this->instance_id;
        $this->assign('goods_sku_list', $goods_sku_list);
        
        $discount_money = $goods_mansong->getGoodsMansongMoney($goods_sku_list);
        $this->assign("discount_money", sprintf("%.2f", $discount_money)); // 总优惠
        
        $count_money = $order->getGoodsSkuListPrice($goods_sku_list);
        $this->assign("count_money", sprintf("%.2f", $count_money)); // 商品金额
        $count_point_exchange = 0;
        
        $max_use_point = $goods_preference->getMaxUsePoint($goods_sku_list); // 最大可使用积分数
        foreach ($list as $k => $v) {
            $list[$k]['price'] = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
        }
        $this->assign("count_point_exchange", $count_point_exchange); // 总积分
        $this->assign("itemlist", $list);
        
        $shop_config = $Config->getShopConfig($shop_id);
        $order_invoice_content = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (! empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        $this->assign("shop_config", $shop_config); // 后台配置
        
        $member_account = $member->getMemberAccount($this->uid, $shop_id);
        if ($member_account['balance'] == '' || $member_account['balance'] == 0) {
            $member_account['balance'] = '0.00';
        }
        $this->assign("member_account", $member_account); // 用户余额
        
        $coupon_list = $order->getMemberCouponList($goods_sku_list);
        $this->assign("coupon_list", $coupon_list); // 获取优惠券
        
        $user_telephone = $this->user->getUserTelephone();
        $this->assign("user_telephone", $user_telephone);
        
        $default_use_point = 0; // 默认使用积分数
        if ($member_account["point"] >= $max_use_point && $max_use_point != 0) {
            $default_use_point = $max_use_point;
        } else {
            $default_use_point = $member_account["point"];
        }
        // 积分配置
        $point_config = $promotion->getPointConfig();
        if ($max_use_point == 0) {
            $point_config["is_open"] = 0;
        }
        $this->assign("point_config", $point_config);
        $this->assign("max_use_point", $max_use_point);
        $this->assign("default_use_point", $default_use_point);
    }

    /**
     * 待付款订单需要的数据 组合套餐
     * 2017年11月22日 10:07:26 王永杰
     */
    public function comboPackageorderInfo()
    {
        $member = new MemberService();
        $order = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config = new Config();
        $promotion = new Promotion();
        $shop_service = new Shop();
        $goods_express_service = new GoodsExpressService();
        $goods_preference = new GoodsPreference();
        $order_tag = $_SESSION['order_tag'];
        $res = $this->combination_packagesSession(); // 获取组合套餐session
                                                     
        // 套餐信息
        $combo_id = $res["combo_id"];
        $combo_detail = $promotion->getComboPackageDetail($combo_id);
        $this->assign("combo_detail", $combo_detail);
        $this->assign("combo_buy_num", $res["combo_buy_num"]);
        
        $goods_sku_list = $res["goods_sku_list"];
        $list = $res["list"];
        
        $goods_sku_list = trim($goods_sku_list);
        if (empty($goods_sku_list)) {
            $this->error("待支付订单中商品不可为空");
        }
        $this->assign('goods_sku_list', $goods_sku_list); // 商品sku列表
        
        $combo_package_price = $combo_detail["combo_package_price"] * $res["combo_buy_num"]; // 套餐总金额
        $this->assign("combo_package_price", $combo_package_price);
        
        $address = $member->getDefaultExpressAddress(); // 获取默认收货地址
        $express = 0;
        
        $express_company_list = array();
        $goods_express_service = new GoodsExpressService();
        if (! empty($address)) {
            // 物流公司
            $express_company_list = $goods_express_service->getExpressCompany($this->instance_id, $goods_sku_list, $address['province'], $address['city'], $address['district']);
            if (! empty($express_company_list)) {
                foreach ($express_company_list as $v) {
                    $express = $v['express_fee']; // 取第一个运费，初始化加载运费
                    break;
                }
            }
            $this->assign("address_is_have", 1);
            // 本地配送
            $o2o_distribution = $goods_express_service->getGoodsO2oPrice($combo_package_price, 0, $address['province'], $address['city'], $address['district'], 0);
            if ($o2o_distribution >= 0) {
                $this->assign("o2o_distribution", $o2o_distribution);
                $this->assign("is_open_o2o_distribution", 1);
            } else {
                $this->assign("is_open_o2o_distribution", 0);
            }
        } else {
            $this->assign("address_is_have", 0);
            $this->assign("is_open_o2o_distribution", 0);
        }
        $count = $goods_express_service->getExpressCompanyCount($this->instance_id);
        $this->assign("express_company_count", $count); // 物流公司数量
        $this->assign("express", sprintf("%.2f", $express)); // 运费
        $this->assign("express_company_list", $express_company_list); // 物流公司
        
        $count_money = $order->getComboPackageGoodsSkuListPrice($goods_sku_list); // 商品金额
        $this->assign("count_money", sprintf("%.2f", $count_money));
        
        $discount_money = $count_money - ($combo_detail["combo_package_price"] * $res["combo_buy_num"]); // 计算优惠金额
        $discount_money = $discount_money < 0 ? 0 : $discount_money;
        $this->assign("discount_money", sprintf("%.2f", $discount_money)); // 总优惠
                                                                           
        // 计算自提点运费
        $pick_up_money = $order->getPickupMoney($combo_package_price);
        if (empty($pick_up_money)) {
            $pick_up_money = 0;
        }
        $this->assign("pick_up_money", $pick_up_money);
        $count_point_exchange = 0;
        $max_use_point = $goods_preference->getMaxUsePoint($goods_sku_list); // 最大可使用积分数
        foreach ($list as $k => $v) {
            $list[$k]['price'] = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
        }
        $this->assign("itemlist", $list); // 格式化后的列表
        
        $shop_id = $this->instance_id;
        $shop_config = $Config->getShopConfig($shop_id);
        $order_invoice_content = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (! empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        
        $this->assign("shop_config", $shop_config); // 后台配置
        
        $member_account = $member->getMemberAccount($this->uid, $this->instance_id);
        if ($member_account['balance'] == '' || $member_account['balance'] == 0) {
            $member_account['balance'] = '0.00';
        }
        $this->assign("member_account", $member_account); // 用户余额
        
        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        if (! empty($address)) {
            $no_mail = checkIdIsinIdArr($address['city'], $promotion_full_mail['no_mail_city_id_array']);
            if ($no_mail) {
                $promotion_full_mail['is_open'] = 0;
            }
        }
        $this->assign("promotion_full_mail", $promotion_full_mail); // 满额包邮
        
        $pickup_point_list = $shop_service->getPickupPointList();
        $pickup_point_list["data"] = $this->pickupPointListSort($address, $pickup_point_list["data"]);
        $this->assign("pickup_point_list", $pickup_point_list); // 自提地址列表
        
        $this->assign("address_default", $address);
        
        // 本地配送时间
        $distribution_time = $this->getDistributionTime();
        $this->assign('distribution_time', $distribution_time);
        
        $default_use_point = 0; // 默认使用积分数
        if ($member_account["point"] >= $max_use_point && $max_use_point != 0) {
            $default_use_point = $max_use_point;
        } else {
            $default_use_point = $member_account["point"];
        }
        // 积分配置
        $point_config = $promotion->getPointConfig();
        if ($max_use_point == 0) {
            $point_config["is_open"] = 0;
        }
        $this->assign("point_config", $point_config);
        $this->assign("max_use_point", $max_use_point);
        $this->assign("default_use_point", $default_use_point);
    }

    /**
     * 加入购物车
     *
     * @return unknown
     */
    public function addShoppingCartSession()
    {
        // 加入购物车
        $session_cart_list = isset($_SESSION["cart_list"]) ? $_SESSION["cart_list"] : ""; // 用户所选择的商品
        if ($session_cart_list == "") {
            $this->redirect(__URL__); // 没有商品返回到首页
        }
        
        $cart_id_arr = explode(",", $session_cart_list);
        $goods = new Goods();
        $cart_list = $goods->getCartList($session_cart_list);
        if (count($cart_list) == 0) {
            $this->redirect(__URL__); // 没有商品返回到首页
        }
        $list = Array();
        $str_cart_id = ""; // 购物车id
        $goods_sku_list = ''; // 商品skuid集合
        for ($i = 0; $i < count($cart_list); $i ++) {
            if ($cart_id_arr[$i] == $cart_list[$i]["cart_id"]) {
                $list[] = $cart_list[$i];
                $str_cart_id .= "," . $cart_list[$i]["cart_id"];
                $goods_sku_list .= "," . $cart_list[$i]['sku_id'] . ':' . $cart_list[$i]['num'];
            }
        }
        $goods_sku_list = substr($goods_sku_list, 1); // 商品sku列表
        $res["list"] = $list;
        $res["goods_sku_list"] = $goods_sku_list;
        return $res;
    }

    /**
     * 立即购买
     */
    public function buyNowSession()
    {
        $order_sku_list = isset($_SESSION["order_sku_list"]) ? $_SESSION["order_sku_list"] : "";
        if (empty($order_sku_list)) {
            $this->redirect(__URL__); // 没有商品返回到首页
        }
        
        $cart_list = array();
        $order_sku_list = explode(":", $_SESSION["order_sku_list"]);
        $sku_id = $order_sku_list[0];
        $num = $order_sku_list[1];
        
        // 获取商品sku信息
        $goods_sku = new \data\model\NsGoodsSkuModel();
        $sku_info = $goods_sku->getInfo([
            'sku_id' => $sku_id
        ], '*');
        
        // 查询当前商品是否有SKU主图
        $order_goods_service = new OrderGoods();
        $picture = $order_goods_service->getSkuPictureBySkuId($sku_info);
        
        // 清除非法错误数据
        $cart = new NsCartModel();
        if (empty($sku_info)) {
            $cart->destroy([
                'buyer_id' => $this->uid,
                'sku_id' => $sku_id
            ]);
            $this->redirect(__URL__); // 没有商品返回到首页
        }
        $goods = new NsGoodsModel();
        $goods_info = $goods->getInfo([
            'goods_id' => $sku_info["goods_id"]
        ], 'max_buy,state,point_exchange_type,point_exchange,picture,goods_id,goods_name,max_use_point');
        
        $cart_list["stock"] = $sku_info['stock']; // 库存
        $cart_list["sku_id"] = $sku_info["sku_id"];
        $cart_list["sku_name"] = $sku_info["sku_name"];
        
        $goods_preference = new GoodsPreference();
        $member_price = $goods_preference->getGoodsSkuMemberPrice($sku_info['sku_id'], $this->uid);
        $goods_service = new Goods();
        $member_price = $goods_service->handleMemberPrice($sku_info["goods_id"], $member_price);
        
        $cart_list["price"] = $member_price < $sku_info['promote_price'] ? $member_price : $sku_info['promote_price'];
        
        $cart_list["goods_id"] = $goods_info["goods_id"];
        $cart_list["goods_name"] = $goods_info["goods_name"];
        $cart_list["max_buy"] = $goods_info['max_buy']; // 限购数量
        $cart_list['point_exchange_type'] = $goods_info['point_exchange_type']; // 积分兑换类型 0 非积分兑换 1 只能积分兑换
        $cart_list['point_exchange'] = $goods_info['point_exchange']; // 积分兑换
        $cart_list['max_use_point'] = $goods_info['max_use_point'] * $num; // 商品最大可用积分
        
        if ($goods_info['state'] != 1) {
            $this->redirect(__URL__); // 商品状态 0下架，1正常，10违规（禁售）
        }
        $cart_list["num"] = $num;
        // 如果购买的数量超过限购，则取限购数量
        if ($goods_info['max_buy'] != 0 && $goods_info['max_buy'] < $num) {
            $num = $goods_info['max_buy'];
        }
        // 如果购买的数量超过库存，则取库存数量
        if ($sku_info['stock'] < $num) {
            $num = $sku_info['stock'];
        }
        // 获取图片信息
        $album_picture_model = new AlbumPictureModel();
        $picture_info = $album_picture_model->get($picture == 0 ? $goods_info['picture'] : $picture);
        $cart_list['picture_info'] = $picture_info;
        
        // 获取商品阶梯优惠信息
        $goods_service = new Goods();
        $cart_list["price"] = $goods_service->getGoodsLadderPreferentialInfo($goods_info["goods_id"], $num, $cart_list["price"]);
        
        if (count($cart_list) == 0) {
            $this->redirect(__URL__); // 没有商品返回到首页
        }
        $list[] = $cart_list;
        $goods_sku_list = $sku_id . ":" . $num; // 商品skuid集合
        $res["list"] = $list;
        $res["goods_sku_list"] = $goods_sku_list;
        return $res;
    }

    /**
     * 组合套餐
     */
    public function combination_packagesSession()
    {
        $order_sku = isset($_SESSION["order_sku"]) ? $_SESSION["order_sku"] : "";
        if (empty($order_sku)) {
            $redirect = __URL(__URL__ . "/index");
            $this->redirect($redirect);
        }
        
        $order_sku_array = explode(",", $order_sku);
        foreach ($order_sku_array as $k => $v) {
            
            $cart_list = array();
            $order_sku_list = explode(":", $v);
            $sku_id = $order_sku_list[0];
            $num = $order_sku_list[1];
            
            // 获取商品sku信息
            $goods_sku = new NsGoodsSkuModel();
            $sku_info = $goods_sku->getInfo([
                'sku_id' => $sku_id
            ], '*');
            
            // 查询当前商品是否有SKU主图
            $order_goods_service = new OrderGoods();
            $picture = $order_goods_service->getSkuPictureBySkuId($sku_info);
            
            // 清除非法错误数据
            $cart = new NsCartModel();
            if (empty($sku_info)) {
                $cart->destroy([
                    'buyer_id' => $this->uid,
                    'sku_id' => $sku_id
                ]);
                $redirect = __URL(__URL__ . "/index");
                $this->redirect($redirect);
            }
            
            $goods = new NsGoodsModel();
            $goods_info = $goods->getInfo([
                'goods_id' => $sku_info["goods_id"]
            ], 'max_buy,state,point_exchange_type,point_exchange,picture,goods_id,goods_name,max_use_point');
            
            $cart_list["stock"] = $sku_info['stock']; // 库存
            $cart_list["sku_name"] = $sku_info["sku_name"];
            
            $goods_preference = new GoodsPreference();
            $member_price = $goods_preference->getGoodsSkuMemberPrice($sku_info['sku_id'], $this->uid);
            $cart_list["price"] = $member_price < $sku_info['price'] ? $member_price : $sku_info['price'];
            $cart_list["goods_id"] = $goods_info["goods_id"];
            $cart_list["goods_name"] = $goods_info["goods_name"];
            $cart_list["max_buy"] = $goods_info['max_buy']; // 限购数量
            $cart_list['point_exchange_type'] = $goods_info['point_exchange_type']; // 积分兑换类型 0 非积分兑换 1 只能积分兑换
            $cart_list['point_exchange'] = $goods_info['point_exchange']; // 积分兑换
            if ($goods_info['state'] != 1) {
                $redirect = __URL(__URL__ . "/index");
                $this->redirect($redirect);
            }
            $cart_list["num"] = $num;
            
            $cart_list['max_use_point'] = $goods_info['max_use_point'] * $num;
            
            // 如果购买的数量超过限购，则取限购数量
            if ($goods_info['max_buy'] != 0 && $goods_info['max_buy'] < $num) {
                $num = $goods_info['max_buy'];
            }
            // 如果购买的数量超过库存，则取库存数量
            if ($sku_info['stock'] < $num) {
                $num = $sku_info['stock'];
            }
            // 获取图片信息，如果该商品有SKU主图，就用。否则用商品主图
            $album_picture_model = new AlbumPictureModel();
            $picture_info = $album_picture_model->get($picture == 0 ? $goods_info['picture'] : $picture);
            $cart_list['picture_info'] = $picture_info;
            
            if (count($cart_list) == 0) {
                $redirect = __URL(__URL__ . "/index");
                $this->redirect($redirect);
            }
            $list[] = $cart_list;
            $goods_sku_list = $sku_id . ":" . $num; // 商品skuid集合
            $res["list"] = $list;
        }
        $res["goods_sku_list"] = $order_sku;
        $res["combo_id"] = isset($_SESSION["combo_id"]) ? $_SESSION["combo_id"] : "";
        $res["combo_buy_num"] = isset($_SESSION["combo_buy_num"]) ? $_SESSION["combo_buy_num"] : "";
        return $res;
    }

    /**
     * 积分兑换商品信息
     */
    public function pointExchangeOrderInfo()
    {
        $member = new MemberService();
        $order = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config = new Config();
        $promotion = new Promotion();
        $shop_service = new Shop();
        $goods_express_service = new GoodsExpressService();
        $goods_preference = new GoodsPreference(); // 商品优惠价格操作类
        
        $order_tag = $_SESSION['order_tag'];
        $res = $this->buyNowSession();
        $goods_sku_list = $res["goods_sku_list"];
        $list = $res["list"];
        
        $goods_sku_list = trim($goods_sku_list);
        if (empty($goods_sku_list)) {
            $this->error("待支付订单中商品不可为空");
        }
        $this->assign('goods_sku_list', $goods_sku_list); // 商品sku列表
                                                          
        // 积分兑换只会有一种商品 只有商品兑换类型为 积分与金额同时购买时才计算优惠和商品金额
        $discount_money = 0;
        $count_money = $order->getGoodsSkuListPrice($goods_sku_list); // 商品金额
        if ($list[0]["point_exchange_type"] == 1) {
            $discount_money = $goods_mansong->getGoodsMansongMoney($goods_sku_list); // 计算优惠金额
        }
        $this->assign("discount_money", sprintf("%.2f", $discount_money)); // 总优惠
        $this->assign("count_money", sprintf("%.2f", $count_money)); // 商品金额
        $this->assign("point_exchange_type", $list[0]["point_exchange_type"]); // 积分兑换类型
        
        $addresslist = $member->getMemberExpressAddressList(1, 0, '', ' is_default DESC'); // 地址查询
        if (empty($addresslist["data"])) {
            $this->assign("address_list", 0);
        } else {
            $this->assign("address_list", $addresslist["data"]); // 选择收货地址
        }
        
        $address = $member->getDefaultExpressAddress(); // 查询默认收货地址
        $this->assign("address_default", $address);
        $express = 0;
        $express_company_list = array();
        if (! empty($address)) {
            // 物流公司
            $express_company_list = $goods_express_service->getExpressCompany($this->instance_id, $goods_sku_list, $address['province'], $address['city'], $address['district']);
            if (! empty($express_company_list)) {
                foreach ($express_company_list as $v) {
                    $express = $v['express_fee']; // 取第一个运费，初始化加载运费
                    break;
                }
            }
            $this->assign("address_is_have", 1);
            // 本地配送
            $o2o_distribution = $goods_express_service->getGoodsO2oPrice($count_money - $discount_money, 0, $address['province'], $address['city'], $address['district'], 0);
            if ($o2o_distribution >= 0) {
                $this->assign("o2o_distribution", $o2o_distribution);
                $this->assign("is_open_o2o_distribution", 1);
            } else {
                $this->assign("is_open_o2o_distribution", 0);
            }
        } else {
            $this->assign("is_open_o2o_distribution", 0);
            $this->assign("address_is_have", 0);
        }
        $count = $goods_express_service->getExpressCompanyCount($this->instance_id);
        $this->assign("express_company_count", $count); // 物流公司数量
        $this->assign("express", sprintf("%.2f", $express)); // 运费
        $this->assign("express_company_list", $express_company_list); // 物流公司
                                                                      
        // 计算自提点运费
        $pick_up_money = $order->getPickupMoney($count_money);
        if (empty($pick_up_money)) {
            $pick_up_money = 0;
        }
        $this->assign("pick_up_money", $pick_up_money);
        
        $count_point_exchange = 0;
        foreach ($list as $k => $v) {
            if ($v['point_exchange_type'] == 1) {
                $list[$k]['price'] = sprintf("%.2f", $list[$k]['price']);
                $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
            } else {
                $list[$k]['price'] = 0.00;
                $list[$k]['subtotal'] = 0.00;
            }
            $list[$k]['total_point'] = $list[$k]['point_exchange'] * $list[$k]['num'];
            $count_point_exchange += $v["point_exchange"] * $v["num"];
        }
        $this->assign("itemlist", $list); // 格式化后的列表
        $this->assign("count_point_exchange", $count_point_exchange); // 总积分
        
        $shop_id = $this->instance_id;
        $shop_config = $Config->getShopConfig($shop_id);
        $order_invoice_content = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (! empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        $this->assign("shop_config", $shop_config); // 后台配置
        
        $coupon_list = array();
        if ($list[0]["point_exchange_type"] == 1) {
            $coupon_list = $order->getMemberCouponList($goods_sku_list); // 获取优惠券
            foreach ($coupon_list as $k => $v) {
                $coupon_list[$k]['start_time'] = substr($v['start_time'], 0, stripos($v['start_time'], " ") + 1);
                $coupon_list[$k]['end_time'] = substr($v['end_time'], 0, stripos($v['end_time'], " ") + 1);
            }
        }
        $this->assign("coupon_list", $coupon_list); // 优惠卷
        
        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        if (! empty($address)) {
            $no_mail = checkIdIsinIdArr($address['city'], $promotion_full_mail['no_mail_city_id_array']);
            if ($no_mail) {
                $promotion_full_mail['is_open'] = 0;
            }
        }
        $this->assign("promotion_full_mail", $promotion_full_mail); // 满额包邮
        
        $pickup_point_list = $shop_service->getPickupPointList();
        $pickup_point_list["data"] = $this->pickupPointListSort($address, $pickup_point_list["data"]);
        $this->assign("pickup_point_list", $pickup_point_list); // 自提地址列表
        
        $goods_mansong_gifts = $this->getOrderGoodsMansongGifts($goods_sku_list);
        $this->assign("goods_mansong_gifts", $goods_mansong_gifts); // 赠品列表
        
        $member = new Member();
        $member_account = $member->getMemberAccount($this->uid, $this->instance_id); // 用户余额
        $this->assign("member_account", $member_account); // 用户余额、积分
                                                          
        // 本地配送时间
        $distribution_time = $this->getDistributionTime();
        $this->assign('distribution_time', $distribution_time);
    }

    /**
     * 订单数据存session
     *
     * @return number
     */
    public function orderCreateSession()
    {
        $tag = request()->post('tag', '');
        if (empty($tag)) {
            return - 1;
        }
        if ($tag == 'cart') {
            $_SESSION['order_tag'] = 'cart';
            $_SESSION['cart_list'] = request()->post('cart_id');
            $_SESSION['order_goods_type'] = 1; // 商品类型标识
        }
        if ($tag == 'buy_now') {
            $_SESSION['order_tag'] = 'buy_now';
            $_SESSION['order_sku_list'] = request()->post('sku_id') . ':' . request()->post('num');
            $_SESSION['order_goods_type'] = request()->post("goods_type"); // 商品类型标识
        }
        if ($tag == 'combination_packages') {
            $_SESSION['order_tag'] = 'combination_packages';
            $_SESSION['order_sku'] = request()->post("data");
            $_SESSION['combo_id'] = request()->post("combo_id", "");
            $_SESSION['combo_buy_num'] = request()->post("buy_num", "");
            $_SESSION['order_goods_type'] = request()->post("goods_type"); // 商品类型标识
        }
        if ($tag == 'groupbuy') {
            $_SESSION['order_tag'] = 'groupbuy';
            $_SESSION['order_sku_list'] = request()->post('sku_id') . ':' . request()->post('num');
            $_SESSION['order_goods_type'] = request()->post("goods_type"); // 商品类型标识
        }
        if ($tag == 'js_point_exchange') {
            $_SESSION['order_tag'] = 'js_point_exchange';
            $_SESSION['order_sku_list'] = request()->post('sku_id') . ':' . request()->post('num');
            $_SESSION['order_goods_type'] = request()->post("goods_type"); // 商品类型标识
        }
        if ($tag == 'goods_presell') {
            $_SESSION['order_tag'] = 'goods_presell';
            $_SESSION['order_sku_list'] = request()->post('sku_id') . ':' . request()->post('num');
            $_SESSION['order_goods_type'] = request()->post("goods_type"); // 商品类型标识
        }
        
        return 1;
    }

    /**
     * 创建订单（实物商品）
     */
    public function orderCreate()
    {
        $order = new OrderService();
        // 获取支付编号
        $out_trade_no = $order->getOrderTradeNo();
        $use_coupon = request()->post('use_coupon', 0); // 优惠券
        $integral = request()->post('integral', 0); // 积分
        $goods_sku_list = request()->post('goods_sku_list', ''); // 商品列表
        $leavemessage = request()->post('leavemessage', ''); // 留言
        $user_money = request()->post("account_balance", 0); // 使用余额
        $pay_type = request()->post("pay_type", 1); // 支付方式
        $buyer_invoice = request()->post("buyer_invoice", ""); // 发票
        $pick_up_id = request()->post("pick_up_id", 0); // 自提点
        $shipping_company_id = request()->post("shipping_company_id", 0); // 物流公司
        $shipping_time = request()->post("shipping_time", 0); // 配送时间
        $shipping_type = request()->post("shipping_type", 1); // 配送方式，1：物流，2：自提 3：本地配送
        
        $member = new Member();
        $address = $member->getDefaultExpressAddress();
        $coin = 0; // 购物币
        $buyer_ip = request()->ip();
        $distribution_time_out = request()->post("distribution_time_out", '');
        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        if (! empty($purchase_restriction)) {
            $res = array(
                "code" => 0,
                "message" => $purchase_restriction
            );
            return $res;
        } else {
            
            $order_id = $order->orderCreate('1', $out_trade_no, $pay_type, $shipping_type, '1', $buyer_ip, $leavemessage, $buyer_invoice, $shipping_time, $address['mobile'], $address['province'], $address['city'], $address['district'], $address["address_info"] . '&nbsp;' . $address['address'], $address['zip_code'], $address['consigner'], $integral, $use_coupon, 0, $goods_sku_list, $user_money, $pick_up_id, $shipping_company_id, $coin, $address["phone"], $distribution_time_out);
            $_SESSION['unpaid_goback'] = __URL(__URL__ . "/wap/order/orderdetail?orderId=" . $order_id);
            // 订单创建标识，表示当前生成的订单详情已经创建好了。用途：订单创建成功后，返回上一个界面的路径是当前创建订单的详情，而不是首页
            $_SESSION['order_create_flag'] = 1;
            
            if ($order_id > 0) {
                $order->deleteCart($goods_sku_list, $this->uid);
                $_SESSION['order_tag'] = ""; // 生成订单后，清除购物车
                return AjaxReturn($out_trade_no);
            } else {
                return AjaxReturn($order_id);
            }
        }
    }

    /**
     * 预售订单创建
     */
    public function presellOrderCreate()
    {
        $order = new OrderService();
        // 获取支付编号
        $out_trade_no = $order->getOrderTradeNo();
        $use_coupon = request()->post('use_coupon', 0); // 优惠券
        $integral = request()->post('integral', 0); // 积分
        $goods_sku_list = request()->post('goods_sku_list', ''); // 商品列表
        $leavemessage = request()->post('leavemessage', ''); // 留言
        $user_money = request()->post("account_balance", 0); // 使用余额
        $pay_type = request()->post("pay_type", 1); // 支付方式
        $buyer_invoice = request()->post("buyer_invoice", ""); // 发票
        $pick_up_id = request()->post("pick_up_id", 0); // 自提点
        $shipping_type = request()->post("shipping_type", 1); // 配送方式，1：商家配送，2：自提 3：本地配送
        $shipping_time = request()->post("shipping_time", 0); // 配送时间
        $express_company_id = request()->post("shipping_company_id", 0); // 物流公司
        $is_full_payment = request()->post('is_full_payment', 0);
        $member = new Member();
        $address = $member->getDefaultExpressAddress();
        $coin = 0; // 购物币
        $buyer_ip = request()->ip();
        $distribution_time_out = request()->post("distribution_time_out", '');
        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        if (! empty($purchase_restriction)) {
            $res = array(
                "code" => 0,
                "message" => $purchase_restriction
            );
            return $res;
        } else {
            
            // 预售订单添加
            $order_id = $order->orderCreatePresell(6, $out_trade_no, $pay_type, $shipping_type, '1', $buyer_ip, $leavemessage, $buyer_invoice, $shipping_time, $address['mobile'], $address['province'], $address['city'], $address['district'], $address["address_info"] . '&nbsp;' . $address['address'], $address['zip_code'], $address['consigner'], $integral, $use_coupon, 0, $goods_sku_list, $user_money, $pick_up_id, $express_company_id, $coin, $address["phone"], $is_full_payment, $distribution_time_out);
            // Log::write($order_id);
            if ($order_id > 0) {
                return AjaxReturn($out_trade_no);
            } else {
                return AjaxReturn($order_id);
            }
        }
    }

    /**
     * 创建订单（虚拟商品）
     */
    public function virtualOrderCreate()
    {
        if ($this->getIsOpenVirtualGoodsConfig() == 0) {
            $this->error("未开启虚拟商品功能");
        }
        $order = new OrderService();
        // 获取支付编号
        $out_trade_no = $order->getOrderTradeNo();
        $use_coupon = request()->post('use_coupon', 0); // 优惠券
        $integral = request()->post('integral', 0); // 积分
        $goods_sku_list = request()->post('goods_sku_list', ''); // 商品列表
        $leavemessage = request()->post('leavemessage', ''); // 留言
        $user_money = request()->post("account_balance", 0); // 使用余额
        $pay_type = request()->post("pay_type", 1); // 支付方式
        $buyer_invoice = request()->post("buyer_invoice", ""); // 发票
        $user_telephone = request()->post("user_telephone", ""); // 电话号码
        $express_company_id = 0; // 物流公司
        $shipping_type = 1; // 配送方式，1：物流，2：自提
        $pick_up_id = 0;
        $member = new Member();
        $address = $member->getDefaultExpressAddress();
        $shipping_time = date("Y-m-d H:i:s", time());
        $buyer_ip = request()->ip();
        
        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        if (! empty($purchase_restriction)) {
            $res = array(
                "code" => 0,
                "message" => $purchase_restriction
            );
            return $res;
        } else {
            $order_id = $order->orderCreateVirtual('2', $out_trade_no, $pay_type, $shipping_type, '1', $buyer_ip, $leavemessage, $buyer_invoice, $shipping_time, $integral, $use_coupon, 0, $goods_sku_list, $user_money, $pick_up_id, $express_company_id, $user_telephone);
            $_SESSION['unpaid_goback'] = __URL(__URL__ . "/wap/order/virtualorderdetail?orderId=" . $order_id);
            if ($order_id > 0) {
                return AjaxReturn($out_trade_no);
            } else {
                return AjaxReturn($order_id);
            }
        }
    }

    /**
     * 创建订单（组合商品）
     */
    public function comboPackageOrderCreate()
    {
        $order = new OrderService();
        // 获取支付编号
        $out_trade_no = $order->getOrderTradeNo();
        $use_coupon = request()->post('use_coupon', 0); // 优惠券
        $integral = request()->post('integral', 0); // 积分
        $goods_sku_list = request()->post('goods_sku_list', ''); // 商品列表
        $leavemessage = request()->post('leavemessage', ''); // 留言
        $user_money = request()->post("account_balance", 0); // 使用余额
        $pay_type = request()->post("pay_type", 1); // 支付方式
        $buyer_invoice = request()->post("buyer_invoice", ""); // 发票
        $pick_up_id = request()->post("pick_up_id", 0); // 自提点
        $shipping_type = request()->post("shipping_type", 1); // 配送方式，1：物流，2：自提 3：本地配送
        $shipping_time = request()->post("shipping_time", 0); // 配送时间
        $express_company_id = request()->post("shipping_company_id", 0); // 物流公司
        $combo_package_id = request()->post("combo_package_id", 0); // 组合套餐id
        $buy_num = request()->post("buy_num", 1); // 购买套数
        
        $member = new Member();
        $address = $member->getDefaultExpressAddress();
        $coin = 0; // 购物币
        $buyer_ip = request()->ip();
        $distribution_time_out = request()->post("distribution_time_out", '');
        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        
        if (! empty($purchase_restriction)) {
            $res = array(
                "code" => 0,
                "message" => $purchase_restriction
            );
            return $res;
        } else {
            
            $order_id = $order->orderCreateComboPackage("3", $out_trade_no, $pay_type, $shipping_type, "1", $buyer_ip, $leavemessage, $buyer_invoice, $shipping_time, $address['mobile'], $address['province'], $address['city'], $address['district'], $address["address_info"] . '&nbsp;' . $address['address'], $address['zip_code'], $address['consigner'], $integral, 0, $goods_sku_list, $user_money, $pick_up_id, $express_company_id, $coin, $address["phone"], $combo_package_id, $buy_num, $distribution_time_out);
            if ($order_id > 0) {
                return AjaxReturn($out_trade_no);
            } else {
                return AjaxReturn($order_id);
            }
        }
    }

    /**
     * 创建订单（积分兑换）
     */
    public function pointExchangeOrderCreate()
    {
        $order = new OrderService();
        // 获取支付编号
        $out_trade_no = $order->getOrderTradeNo();
        $use_coupon = request()->post('use_coupon', 0); // 优惠券
        $integral = request()->post('integral', 0); // 积分
        $goods_sku_list = request()->post('goods_sku_list', ''); // 商品列表
        $leavemessage = request()->post('leavemessage', ''); // 留言
        $user_money = request()->post("account_balance", 0); // 使用余额
        $pay_type = 11; // request()->post("pay_type", 1); // 支付方式 积分兑换
        $buyer_invoice = request()->post("buyer_invoice", ""); // 发票
        $pick_up_id = request()->post("pick_up_id", 0); // 自提点
        $shipping_type = request()->post("shipping_type", 1); // 配送方式，1：物流，2：自提 3：本地配送
        $shipping_time = request()->post("shipping_time", 0); // 配送时间
        $express_company_id = request()->post("shipping_company_id", 0); // 物流公司
        $combo_package_id = request()->post("combo_package_id", 0); // 组合套餐id
        $buy_num = request()->post("buy_num", 1); // 购买套数
        $point_exchange_type = request()->post("point_exchange_type", 1);
        $order_goods_type = request()->post("order_goods_type", 1); // 商品类型 0虚拟商品 1实物商品
        $user_telephone = request()->post("user_telephone", ""); // 虚拟商品手机号
        $order_type = $order_goods_type == 1 ? 1 : 2; // 订单类型 1实物订单 2虚拟订单
        
        $member = new Member();
        $address = $member->getDefaultExpressAddress();
        $coin = 0; // 购物币
        $buyer_ip = request()->ip();
        $distribution_time_out = request()->post("distribution_time_out", '');
        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        if (! empty($purchase_restriction)) {
            $res = array(
                "code" => 0,
                "message" => $purchase_restriction
            );
            return $res;
        } else {
            $order_id = $order->orderCreatePointExhange($order_type, $out_trade_no, $pay_type, $shipping_type, "1", $buyer_ip, $leavemessage, $buyer_invoice, $shipping_time, $address['mobile'], $address['province'], $address['city'], $address['district'], $address["address_info"] . '&nbsp;' . $address['address'], $address['zip_code'], $address['consigner'], $integral, $use_coupon, 0, $goods_sku_list, $user_money, $pick_up_id, $express_company_id, $coin, $address['phone'], $point_exchange_type, $order_goods_type, $user_telephone, $distribution_time_out);
            if ($order_id > 0) {
                return AjaxReturn($out_trade_no);
            } else {
                return AjaxReturn($order_id);
            }
        }
    }

    /**
     * 获取当前会员的订单列表
     */
    public function myOrderList()
    {
        $status = request()->get('status', 'all');
        if (request()->isAjax()) {
            $status = request()->post('status', 'all');
            $condition['buyer_id'] = $this->uid;
            $condition['is_deleted'] = 0;
            $condition['order_type'] = array(
                "in",
                "1,3"
            );
            if (! empty($this->shop_id)) {
                $condition['shop_id'] = $this->shop_id;
            }
            
            if ($status != 'all') {
                switch ($status) {
                    case 0:
                        $condition['order_status'] = 0;
                        break;
                    case 1:
                        $condition['order_status'] = 1;
                        break;
                    case 2:
                        $condition['order_status'] = 2;
                        break;
                    case 3:
                        $condition['order_status'] = array(
                            'in',
                            '3,4'
                        );
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
                    case 5:
                        $condition['order_status'] = array(
                            'in',
                            '3,4'
                        );
                        $condition['is_evaluate'] = array(
                            'in',
                            '0,1'
                        );
                        break;
                    default:
                        break;
                }
            }
            $page_index = request()->post("page", 1);
            // 还要考虑状态逻辑
            $order = new OrderService();
            $order_list = $order->getOrderList($page_index, PAGESIZE, $condition, 'create_time desc');
            return $order_list;
        } else {
            $this->assign("status", $status);
            return view($this->style . 'Order/myOrderList');
        }
    }

    /**
     * 获取当前会员的虚拟订单列表
     */
    public function myVirtualOrderList()
    {
        if ($this->getIsOpenVirtualGoodsConfig() == 0) {
            $this->error("未开启虚拟商品功能");
        }
        $status = request()->get('status', 'all');
        if (request()->isAjax()) {
            $status = request()->post('status', 'all');
            $condition['buyer_id'] = $this->uid;
            $condition['is_deleted'] = 0;
            $condition['order_type'] = 2;
            if ($this->instance_id != null) {
                $condition['shop_id'] = $this->instance_id;
            }
            
            if ($status != 'all') {
                switch ($status) {
                    case 0:
                        $condition['order_status'] = 0;
                        break;
                    case 5:
                        $condition['order_status'] = array(
                            'in',
                            '3,4'
                        );
                        $condition['is_evaluate'] = array(
                            'in',
                            '0,1'
                        );
                        break;
                }
            }
            $page_index = request()->post("page", 1);
            // 还要考虑状态逻辑
            $order = new OrderService();
            $order_list = $order->getOrderList($page_index, PAGESIZE, $condition, 'create_time desc');
            return $order_list;
        } else {
            $this->assign("status", $status);
            return view($this->style . 'Order/myVirtualOrderList');
        }
    }

    /**
     * 我要评价
     *
     * @return \think\response\View
     */
    public function reviewCommodity()
    {
        // 先考虑显示的样式
        if (request()->isGet()) {
            $order_id = request()->get('orderId', '');
            // 判断该订单是否是属于该用户的
            $order_service = new OrderService();
            $condition['order_id'] = $order_id;
            $condition['buyer_id'] = $this->uid;
            $condition['review_status'] = 0;
            $condition['order_status'] = array(
                'in',
                '3,4'
            );
            $order_count = $order_service->getUserOrderCountByCondition($condition);
            if ($order_count == 0) {
                $this->error("对不起,您无权进行此操作");
            }
            $order = new OrderOrderService();
            $list = $order->getOrderGoods($order_id);
            $orderDetail = $order->getDetail($order_id);
            $this->assign("order_no", $orderDetail['order_no']);
            $this->assign("order_id", $order_id);
            $this->assign("list", $list);
            // var_dump($order_id);
            // var_dump($list);die;
            return view($this->style . 'Order/reviewCommodity');
            if (($orderDetail['order_status'] == 3 || $orderDetail['order_status'] == 4) && $orderDetail['is_evaluate'] == 0) {} else {
                $redirect = __URL(__URL__ . "/member/index");
                $this->redirect($redirect);
            }
        } else {
            return view($this->style . "Order/myOrderList");
        }
    }

    /**
     * 商品评价提交
     * 创建：李吉
     * 创建时间：2017-02-16 15:22:59
     */
    public function addGoodsEvaluate()
    {
        $order = new OrderService();
        $order_id = request()->post('order_id', '');
        $order_no = request()->post('order_no', '');
        $order_id = intval($order_id);
        $order_no = intval($order_no);
        $goods = request()->post('goodsEvaluate', '');
        $goodsEvaluateArray = json_decode($goods);
        $dataArr = array();
        foreach ($goodsEvaluateArray as $key => $goodsEvaluate) {
            $orderGoods = $order->getOrderGoodsInfo($goodsEvaluate->order_goods_id);
            $data = array(
                
                'order_id' => $order_id,
                'order_no' => $order_no,
                'order_goods_id' => intval($goodsEvaluate->order_goods_id),
                
                'goods_id' => $orderGoods['goods_id'],
                'goods_name' => $orderGoods['goods_name'],
                'goods_price' => $orderGoods['goods_money'],
                'goods_image' => $orderGoods['goods_picture'],
                'shop_id' => $orderGoods['shop_id'],
                'shop_name' => "默认",
                'content' => $goodsEvaluate->content,
                'addtime' => time(),
                'image' => $goodsEvaluate->imgs,
                
                // 'explain_first' => $goodsEvaluate->explain_first,
                'member_name' => $this->user->getMemberDetail()['member_name'],
                'explain_type' => $goodsEvaluate->explain_type,
                'uid' => $this->uid,
                'is_anonymous' => $goodsEvaluate->is_anonymous,
                'scores' => intval($goodsEvaluate->scores)
            );
            $dataArr[] = $data;
        }
        $result = $order->addGoodsEvaluate($dataArr, $order_id);
        if ($result) {
            $Config = new Config();
            $integralConfig = $Config->getIntegralConfig($this->instance_id);
            if ($integralConfig['comment_coupon'] == 1) {
                $rewardRule = new PromoteRewardRule();
                $res = $rewardRule->getRewardRuleDetail($this->instance_id);
                if ($res['comment_coupon'] != 0) {
                    $member = new Member();
                    $retval = $member->memberGetCoupon($this->uid, $res['comment_coupon'], 2);
                }
            }
        }
        return $result;
    }

    /**
     * 追评
     * 李吉
     * 2017-02-17 14:12:15
     */
    public function reviewAgain()
    {
        // 先考虑显示的样式
        if (request()->isGet()) {
            $order_id = request()->get('orderId', '');
            // 判断该订单是否是属于该用户的
            $order_service = new OrderService();
            $condition['order_id'] = $order_id;
            $condition['buyer_id'] = $this->uid;
            $condition['is_evaluate'] = 1;
            $order_count = $order_service->getUserOrderCountByCondition($condition);
            if ($order_count == 0) {
                $this->error("对不起,您无权进行此操作");
            }
            
            $order = new OrderOrderService();
            $list = $order->getOrderGoods($order_id);
            $orderDetail = $order->getDetail($order_id);
            $this->assign("order_no", $orderDetail['order_no']);
            $this->assign("order_id", $order_id);
            $this->assign("list", $list);
            if (($orderDetail['order_status'] == 3 || $orderDetail['order_status'] == 4) && $orderDetail['is_evaluate'] == 1) {
                return view($this->style . 'Order/reviewAgain');
            } else {
                
                $redirect = __URL(__URL__ . "/member/index");
                $this->redirect($redirect);
            }
        } else {
            return view($this->style . "Order/myOrderList");
        }
    }

    /**
     * 增加商品评价
     */
    public function modityCommodity()
    {
        return 1;
    }

    /**
     * 商品-追加评价提交数据
     * 创建：李吉
     * 创建时间：2017-02-16 15:22:59
     */
    public function addGoodsEvaluateAgain()
    {
        $order = new OrderService();
        $order_id = request()->post('order_id', '');
        $order_no = request()->post('order_no', '');
        $order_id = intval($order_id);
        $order_no = intval($order_no);
        $goods = request()->post('goodsEvaluate', '');
        $goodsEvaluateArray = json_decode($goods);
        
        $result = 1;
        foreach ($goodsEvaluateArray as $key => $goodsEvaluate) {
            $res = $order->addGoodsEvaluateAgain($goodsEvaluate->content, $goodsEvaluate->imgs, $goodsEvaluate->order_goods_id);
            if ($res == false) {
                $result = false;
                break;
            }
        }
        if ($result == 1) {
            $data = array(
                'is_evaluate' => 2
            );
            $result = $order->modifyOrderInfo($data, $order_id);
        }
        
        return $result;
    }

    /**
     * 订单详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function orderDetail()
    {
        $order_id = request()->get('orderId', 0);
        if (! is_numeric($order_id)) {
            $this->error("没有获取到订单信息");
        }
        $order_service = new OrderService();
        $detail = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            $this->error("没有获取到订单信息");
        }
        // 通过order_id判断该订单是否属于当前用户
        $condition['order_id'] = $order_id;
        $condition['buyer_id'] = $this->uid;
        $condition['order_type'] = array(
            "in",
            "1,3,7"
        );
        $order_count = $order_service->getOrderCount($condition);
        if ($order_count == 0) {
            $this->error("没有获取到订单信息");
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
            $this->assign('express_name', $express_name);
            $this->assign('express_code', $express_code);
        }
        $this->assign('express_count', $express_count);
        $this->assign('is_show_express_code', $count); // 是否显示运单号（无需物流不显示）
        
        $this->assign("order", $detail);
        
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
        $this->assign("list", $list);
        
        return view($this->style . 'Order/orderDetail');
    }

    /**
     * 虚拟订单详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function virtualOrderDetail()
    {
        if ($this->getIsOpenVirtualGoodsConfig() == 0) {
            $this->error("未开启虚拟商品功能");
        }
        
        $order_id = request()->get('orderId', 0);
        if (! is_numeric($order_id)) {
            $this->error("没有获取到订单信息");
        }
        $order_service = new OrderService();
        $detail = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            $this->error("没有获取到订单信息");
        }
        // 通过order_id判断该订单是否属于当前用户
        $condition['order_id'] = $order_id;
        $condition['buyer_id'] = $this->uid;
        $condition['order_type'] = 2;
        $order_count = $order_service->getOrderCount($condition);
        if ($order_count == 0) {
            $this->error("没有获取到订单信息");
        }
        
        $this->assign("order", $detail);
        
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
        $this->assign("list", $list);
        
        return view($this->style . 'Order/virtualOrderDetail');
    }

    /**
     * 物流详情页
     */
    public function orderExpress()
    {
        $order_id = request()->get('orderId', 0);
        if (! is_numeric($order_id)) {
            $this->error("没有获取到订单信息");
        }
        $order_service = new OrderService();
        $detail = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            $this->error("没有获取到订单信息");
        }
        // 获取物流跟踪信息
        $this->assign("order", $detail);
        return view($this->style . 'Order/orderExpress');
    }

    /**
     * 查询包裹物流信息
     * 2017年6月24日 10:42:34 王永杰
     */
    public function getOrderGoodsExpressMessage()
    {
        $express_id = request()->post("express_id", 0); // 物流包裹id
        $res = - 1;
        if ($express_id) {
            $order_service = new OrderService();
            $res = $order_service->getOrderGoodsExpressMessage($express_id);
            $res = array_reverse($res);
        }
        return $res;
    }

    /**
     * 订单项退款详情
     */
    public function refundDetail()
    {
        $order_goods_id = request()->get('order_goods_id', 0);
        if (! is_numeric($order_goods_id)) {
            $this->error("没有获取到退款信息");
        }
        $order_service = new OrderService();
        $detail = $order_service->getOrderGoodsRefundInfo($order_goods_id);
        $this->assign("order_refund", $detail);
        
        $refund_money = $order_service->orderGoodsRefundMoney($order_goods_id);
        $this->assign('refund_money', sprintf("%.2f", $refund_money));
        
        // 余额退款
        $order_goods_service = new OrderGoods();
        $refund_balance = $order_goods_service->orderGoodsRefundBalance($order_goods_id);
        $this->assign("refund_balance", sprintf("%.2f", $refund_balance));
        
        $this->assign("detail", $detail);
        // 查询店铺默认物流地址
        $express = new Express();
        $address = $express->getDefaultShopExpressAddress($this->instance_id);
        // 查询商家地址
        $shop_info = $order_service->getShopReturnSet($this->instance_id);
        $this->assign("shop_info", $shop_info);
        $this->assign("address_info", $address);
        // 查询订单所退运费
        $freight = $order_service->getOrderRefundFreight($order_goods_id);
        $this->assign("freight", $freight);
        return view($this->style . 'Order/refundDetail');
    }

    /**
     * 申请退款
     */
    public function orderGoodsRefundAskfor()
    {
        $order_id = request()->post('order_id', 0);
        $order_goods_id = request()->post('order_goods_id', 0);
        $refund_type = request()->post('refund_type', 1);
        $refund_require_money = request()->post('refund_require_money', 0);
        $refund_reason = request()->post('refund_reason', '');
        $order_service = new OrderService();
        $retval = $order_service->orderGoodsRefundAskfor($order_id, $order_goods_id, $refund_type, $refund_require_money, $refund_reason);
        return AjaxReturn($retval);
    }

    /**
     * 买家退货
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function orderGoodsRefundExpress()
    {
        $order_id = request()->post('order_id', 0);
        $order_goods_id = request()->post('order_goods_id', 0);
        $refund_express_company = request()->post('refund_express_company', '');
        $refund_shipping_no = request()->post('refund_shipping_no', 0);
        $refund_reason = request()->post('refund_reason', '');
        $order_service = new OrderService();
        $retval = $order_service->orderGoodsReturnGoods($order_id, $order_goods_id, $refund_express_company, $refund_shipping_no);
        return AjaxReturn($retval);
    }

    /**
     * 交易关闭
     */
    public function orderClose()
    {
        $order_service = new OrderService();
        $order_id = request()->post('order_id', '');
        $res = $order_service->orderClose($order_id);
        return AjaxReturn($res);
    }

    /**
     * 订单后期支付页面
     */
    public function orderPay()
    {
        $order_id = request()->get('id', 0);
        $out_trade_no = request()->get('out_trade_no', 0);
        $order_service = new OrderService();
        if ($order_id != 0) {
            // 更新支付流水号
            $order_service -> createNewOutTradeNoReturnBalance($order_id);
            $new_out_trade_no = $order_service->getOrderNewOutTradeNo($order_id);
            $url = __URL(__URL__ . '/wap/pay/pay?out_trade_no=' . $new_out_trade_no);
            header("Location: " . $url);
            exit();
        } else {
            // 待结算订单处理
            if ($out_trade_no != 0) {
                $url = __URL(__URL__ . '/wap/pay/getpayvalue?out_trade_no=' . $out_trade_no);
                exit();
            } else {
                $this->error("没有获取到支付信息");
            }
        }
    }

    /**
     * 收货
     */
    public function orderTakeDelivery()
    {
        $order_service = new OrderService();
        $order_id = request()->post('order_id', '');
        $res = $order_service->OrderTakeDelivery($order_id);
        return AjaxReturn($res);
    }

    /**
     * 删除订单
     */
    public function deleteOrder()
    {
        if (request()->isAjax()) {
            $order_service = new OrderService();
            $order_id = request()->post("order_id", "");
            $res = $order_service->deleteOrder($order_id, 2, $this->uid);
            return AjaxReturn($res);
        }
    }

    /**
     * 获取订单商品满就送赠品，重复赠品累加数量
     * 创建时间：2018年1月24日12:34:10 王永杰
     */
    public function getOrderGoodsMansongGifts($goods_sku_list)
    {
        $res = array();
        $gift_id_arr = array();
        $goods_mansong = new GoodsMansong();
        $mansong_array = $goods_mansong->getGoodsSkuListMansong($goods_sku_list);
        if (! empty($mansong_array)) {
            foreach ($mansong_array as $k => $v) {
                foreach ($v['discount_detail'] as $discount_k => $discount_v) {
                    if (! empty($discount_v[0]['gift_id'])) {
                        $v = $discount_v[0]['gift_id'];
                        array_push($gift_id_arr, $v);
                        break;
                    }
                }
            }
        }
        // 统计每个赠品的数量
        $statistical = array_count_values($gift_id_arr);
        $promotion = new Promotion();
        foreach ($statistical as $k => $v) {
            $detail = $promotion->getPromotionGiftDetail($k);
            $detail['count'] = $v;
            array_push($res, $detail);
        }
        return $res;
    }

    /**
     * 待付款订单需要的数据
     * 2017年6月28日 15:24:48 王永杰
     */
    public function orderGroupBuyInfo()
    {
        $member = new MemberService();
        $order = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config = new Config();
        $promotion = new Promotion();
        $shop_service = new Shop();
        // 检测购物车
        $order_tag = $_SESSION['order_tag'];
        
        $res = $this->groupBuySession();
        $goods_sku_list = $res["goods_sku_list"];
        $list = $res["list"];
        
        $this->assign('goods_sku_list', $goods_sku_list);
        
        $order_group_buy = new OrderGroupBuy();
        $count_money = $order_group_buy->getGoodsSkuGroupBuyPrice($goods_sku_list);
        $this->assign("count_money", sprintf("%.2f", $count_money)); // 商品金额
        
        $address = $member->getDefaultExpressAddress(); // 获取默认收货地址
        $express = 0;
        
        $express_company_list = array();
        $goods_express_service = new GoodsExpressService();
        if (! empty($address)) {
            // 物流公司
            $express_company_list = $goods_express_service->getExpressCompany($this->instance_id, $goods_sku_list, $address['province'], $address['city'], $address['district']);
            if (! empty($express_company_list)) {
                foreach ($express_company_list as $v) {
                    $express = $v['express_fee']; // 取第一个运费，初始化加载运费
                    break;
                }
            }
            $this->assign("address_is_have", 1);
            // 本地配送
            $o2o_distribution = $goods_express_service->getGoodsO2oPrice($count_money, 0, $address['province'], $address['city'], $address['district'], 0);
            if ($o2o_distribution >= 0) {
                $this->assign("o2o_distribution", $o2o_distribution);
                $this->assign("is_open_o2o_distribution", 1);
            } else {
                $this->assign("is_open_o2o_distribution", 0);
            }
        } else {
            $this->assign("address_is_have", 0);
            $this->assign("is_open_o2o_distribution", 0);
        }
        $count = $goods_express_service->getExpressCompanyCount($this->instance_id);
        $this->assign("express_company_count", $count); // 物流公司数量
        $this->assign("express", sprintf("%.2f", $express)); // 运费
        $this->assign("express_company_list", $express_company_list); // 物流公司
        
        $discount_money = 0;
        $this->assign("discount_money", sprintf("%.2f", $discount_money)); // 总优惠
        
        $pick_up_money = $order->getPickupMoney($count_money);
        $this->assign("pick_up_money", $pick_up_money);
        $count_point_exchange = 0;
        $max_use_point = 0;
        foreach ($list as $k => $v) {
            $list[$k]['price'] = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
            $max_use_point += $v["max_use_point"];
        }
        $this->assign("count_point_exchange", $count_point_exchange); // 总积分
        $this->assign("itemlist", $list);
        
        $shop_id = $this->instance_id;
        $shop_config = $Config->getShopConfig($shop_id);
        $order_invoice_content = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (! empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        $this->assign("shop_config", $shop_config); // 后台配置
        
        $member_account = $member->getMemberAccount($this->uid, $this->instance_id);
        if ($member_account['balance'] == '' || $member_account['balance'] == 0) {
            $member_account['balance'] = '0.00';
        }
        $this->assign("member_account", $member_account); // 用户余额
        
        $coupon_list = $order->getMemberCouponList($goods_sku_list);
        $this->assign("coupon_list", $coupon_list); // 获取优惠券
        
        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        if (! empty($address)) {
            $no_mail = checkIdIsinIdArr($address['city'], $promotion_full_mail['no_mail_city_id_array']);
            if ($no_mail) {
                $promotion_full_mail['is_open'] = 0;
            }
        }
        $this->assign("promotion_full_mail", $promotion_full_mail); // 满额包邮
        
        $pickup_point_list = $shop_service->getPickupPointList();
        $pickup_point_list["data"] = $this->pickupPointListSort($address, $pickup_point_list["data"]);
        $this->assign("pickup_point_list", $pickup_point_list); // 自提地址列表
        
        $this->assign("address_default", $address);
        
        $goods_mansong_gifts = $this->getOrderGoodsMansongGifts($goods_sku_list);
        $this->assign("goods_mansong_gifts", $goods_mansong_gifts); // 赠品列表
                                                                    
        // 本地配送时间
        $distribution_time = $this->getDistributionTime();
        $this->assign('distribution_time', $distribution_time);
        
        $default_use_point = 0; // 默认使用积分数
        if ($member_account["point"] >= $max_use_point && $max_use_point != 0) {
            $default_use_point = $max_use_point;
        } else {
            $default_use_point = $member_account["point"];
        }
        // 积分配置
        $point_config = $promotion->getPointConfig();
        if ($max_use_point == 0) {
            $point_config["is_open"] = 0;
        }
        $this->assign("point_config", $point_config);
        $this->assign("max_use_point", $max_use_point);
        $this->assign("default_use_point", $default_use_point);
    }

    /**
     * 团购
     */
    public function groupBuySession()
    {
        $order_sku_list = isset($_SESSION["order_sku_list"]) ? $_SESSION["order_sku_list"] : "";
        if (empty($order_sku_list)) {
            $this->redirect(__URL__); // 没有商品返回到首页
        }
        
        $cart_list = array();
        $order_sku_list = explode(":", $_SESSION["order_sku_list"]);
        $sku_id = $order_sku_list[0];
        $num = $order_sku_list[1];
        
        // 获取商品sku信息
        $goods_sku = new \data\model\NsGoodsSkuModel();
        $sku_info = $goods_sku->getInfo([
            'sku_id' => $sku_id
        ], '*');
        
        // 查询当前商品是否有SKU主图
        $order_goods_service = new OrderGoods();
        $picture = $order_goods_service->getSkuPictureBySkuId($sku_info);
        
        // 清除非法错误数据
        $cart = new NsCartModel();
        if (empty($sku_info)) {
            $cart->destroy([
                'buyer_id' => $this->uid,
                'sku_id' => $sku_id
            ]);
            $this->redirect(__URL__); // 没有商品返回到首页
        }
        $goods = new NsGoodsModel();
        $goods_info = $goods->getInfo([
            'goods_id' => $sku_info["goods_id"]
        ], 'max_buy,state,point_exchange_type,point_exchange,picture,goods_id,goods_name,max_use_point');
        
        $cart_list["stock"] = $sku_info['stock']; // 库存
        $cart_list["sku_id"] = $sku_info["sku_id"];
        $cart_list["sku_name"] = $sku_info["sku_name"];
        
        $goods_preference = new GoodsPreference();
        $member_price = $goods_preference->getGoodsSkuMemberPrice($sku_info['sku_id'], $this->uid);
        $cart_list["price"] = $member_price < $sku_info['promote_price'] ? $member_price : $sku_info['promote_price'];
        
        $cart_list["goods_id"] = $goods_info["goods_id"];
        $cart_list["goods_name"] = $goods_info["goods_name"];
        $cart_list["max_buy"] = $goods_info['max_buy']; // 限购数量
        $cart_list['point_exchange_type'] = $goods_info['point_exchange_type']; // 积分兑换类型 0 非积分兑换 1 只能积分兑换
        $cart_list['point_exchange'] = $goods_info['point_exchange']; // 积分兑换
        if ($goods_info['state'] != 1) {
            $this->redirect(__URL__); // 商品状态 0下架，1正常，10违规（禁售）
        }
        $cart_list["num"] = $num;
        $cart_list["max_use_point"] = $goods_info["max_use_point"] * $num;
        // 团购活动信息
        $group_num = 0;
        $group_price = 0;
        $group_buy_service = new GroupBuy();
        $group_buy_info = $group_buy_service->getGoodsFirstPromotionGroupBuy($sku_info["goods_id"]);
        // 如果购买的数量超过限购，则取限购数量
        if ($goods_info['max_num'] != 0 && $goods_info['max_num'] < $num) {
            $num = $goods_info['max_buy'];
        }
        // 如果购买的数量超过库存，则取库存数量
        if ($sku_info['stock'] < $num) {
            $num = $sku_info['stock'];
        }
        if (($group_buy_info["max_num"] >= $num && $group_buy_info["max_num"] != 0) && ($group_buy_info["min_num"] <= $num && $group_buy_info["min_num"] != 0)) {
            if (! empty($group_buy_info["price_array"])) {
                foreach ($group_buy_info["price_array"] as $price_key => $price_val) {
                    if ($num >= $price_val["num"]) {
                        $group_num = $price_val["num"];
                        $group_price = $price_val["group_price"];
                    }
                }
            }
        } else {
            $redirect = __URL(__URL__ . "/index");
            $this->redirect($redirect);
        }
        // 赋予商品团购价
        $cart_list["price"] = $group_price;
        // 获取图片信息
        $album_picture_model = new AlbumPictureModel();
        $picture_info = $album_picture_model->get($picture == 0 ? $goods_info['picture'] : $picture);
        $cart_list['picture_info'] = $picture_info;
        
        if (count($cart_list) == 0) {
            $this->redirect(__URL__); // 没有商品返回到首页
        }
        $list[] = $cart_list;
        $goods_sku_list = $sku_id . ":" . $num; // 商品skuid集合
        $res["list"] = $list;
        $res["goods_sku_list"] = $goods_sku_list;
        return $res;
    }

    /**
     * 创建订单（团购订单）
     */
    public function groupBuyOrderCreate()
    {
        $order = new OrderService();
        $group_buy_service = new GroupBuy();
        // 获取支付编号
        $out_trade_no = $order->getOrderTradeNo();
        $use_coupon = request()->post('use_coupon', 0); // 优惠券
        $integral = request()->post('integral', 0); // 积分
        $goods_sku_list = request()->post('goods_sku_list', ''); // 商品列表
        $leavemessage = request()->post('leavemessage', ''); // 留言
        $user_money = request()->post("account_balance", 0); // 使用余额
        $pay_type = request()->post("pay_type", 1); // 支付方式
        $buyer_invoice = request()->post("buyer_invoice", ""); // 发票
        $pick_up_id = request()->post("pick_up_id", 0); // 自提点
        $shipping_type = request()->post("shipping_type", 1); // 配送方式，1：物流，2：自提 3：本地配送
        $shipping_time = request()->post("shipping_time", 0); // 配送时间
        $express_company_id = request()->post("shipping_company_id", 0); // 物流公司
        
        $member = new Member();
        $address = $member->getDefaultExpressAddress(); // 收货人信息
        $receiver_mobile = $address["mobile"]; // 收货人手机号
        $receiver_province = $address["province"]; // 收货人地址
        $receiver_city = $address["city"]; // 收货人地址
        $receiver_district = $address["district"]; // 收货人地址
        $receiver_address = $address["address"]; // 收货人地址
        $receiver_zip = $address["zip_code"]; // 收货人邮编
        $receiver_name = $address["consigner"]; // 收货人姓名
        $coin = 0; // 购物币
        $distribution_time_out = request()->post("distribution_time_out", '');
                   
        // 查询商品限购
        $order_id = $group_buy_service->groupBuyOrderCreate(1, $out_trade_no, $pay_type, $shipping_type, 1, 1, $leavemessage, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $integral, $user_money, $goods_sku_list, 0, $pick_up_id, $express_company_id, $coin, $distribution_time_out);
        // Log::write($order_id);
        if ($order_id > 0) {
            $order->deleteCart($goods_sku_list, $this->uid);
            $_SESSION['order_tag'] = ""; // 订单创建成功会把购物车中的标记清楚
            return AjaxReturn($out_trade_no);
        } else {
            return AjaxReturn($order_id);
        }
    }

    /**
     * 订单项退款详情 售后
     */
    public function customerDetail()
    {
        $order_goods_id = request()->get('order_goods_id', 0);
        if (! is_numeric($order_goods_id)) {
            $this->error("没有获取到退款信息");
        }
        $id = 0;
        $order_service = new OrderService();
        $detail = $order_service->getCustomerServiceInfo($id, $order_goods_id);
        $this->assign("order_refund", $detail);
        $refund_money = $order_service->orderGoodsRefundMoney($order_goods_id);
        $this->assign('refund_money', sprintf("%.2f", $refund_money));
        
        // 余额退款
        $order_goods_service = new OrderGoods();
        $refund_balance = $order_goods_service->orderGoodsRefundBalance($order_goods_id);
        $this->assign("refund_balance", sprintf("%.2f", $refund_balance));
        
        $this->assign("detail", $detail);
        // 查询店铺默认物流地址
        $express = new Express();
        $address = $express->getDefaultShopExpressAddress($this->instance_id);
        // 查询商家地址
        $shop_info = $order_service->getShopReturnSet($this->instance_id);
        $this->assign("shop_info", $shop_info);
        $this->assign("address_info", $address);
        
        $this->assign("order_goods_id", $order_goods_id);
        if (empty($detail)) {
            return view($this->style . "Order/customerDetailFirst");
        } else {
            return view($this->style . "Order/customerDetail");
        }
    }

    /**
     * 申请退款 售后
     */
    public function orderGoodsCustomerServiceAskfor()
    {
        $order_goods_id = request()->post('order_goods_id', 0);
        $refund_type = request()->post('refund_type', 1);
        $refund_require_money = request()->post('refund_require_money', 0);
        $refund_reason = request()->post('refund_reason', '');
        $order_service = new OrderService();
        $retval = $order_service->orderGoodsCustomerServiceAskfor($order_goods_id, $refund_type, $refund_require_money, $refund_reason);
        return AjaxReturn($retval);
    }

    /**
     * 买家退货 售后
     */
    public function orderGoodsCustomerExpress()
    {
        $id = request()->post('id', 0);
        $order_goods_id = request()->post('order_goods_id', 0);
        $refund_express_company = request()->post('refund_express_company', '');
        $refund_shipping_no = request()->post('refund_shipping_no', 0);
        $order_service = new OrderService();
        $retval = $order_service->orderGoodsCustomerExpress($id, $order_goods_id, $refund_express_company, $refund_shipping_no);
        return AjaxReturn($retval);
    }

    /**
     * 获取当前会员的订单列表
     */
    public function myBargainOrderList()
    {
        $status = request()->get('status', 'all');
        if (request()->isAjax()) {
            $status = request()->post('status', 'all');
            $condition['buyer_id'] = $this->uid;
            $condition['is_deleted'] = 0;
            $condition['order_type'] = 7;
            if (! empty($this->shop_id)) {
                $condition['shop_id'] = $this->shop_id;
            }
            
            if ($status != 'all') {
                switch ($status) {
                    case 0:
                        $condition['order_status'] = 0;
                        break;
                    case 1:
                        $condition['order_status'] = 1;
                        break;
                    case 2:
                        $condition['order_status'] = 2;
                        break;
                    case 3:
                        $condition['order_status'] = array(
                            'in',
                            '3,4'
                        );
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
                    case 5:
                        $condition['order_status'] = array(
                            'in',
                            '3,4'
                        );
                        $condition['is_evaluate'] = array(
                            'in',
                            '0,1'
                        );
                        break;
                    default:
                        break;
                }
            }
            $page_index = request()->post("page", 1);
            // 还要考虑状态逻辑
            $order = new OrderService();
            $order_list = $order->getOrderList($page_index, PAGESIZE, $condition, 'create_time desc');
            return $order_list;
        } else {
            $this->assign("status", $status);
            return view($this->style . 'Order/myBargainOrderList');
        }
    }

    /**
     * 订单后期预定金支付页面
     */
    public function orderPresellPay()
    {
        $order_id = request()->get('id', 0);
        $order_service = new OrderService();
        
        $presell_order_info = $order_service->getOrderPresellInfo(0, [
            'relate_id' => $order_id
        ]);
        $presell_order_id = $presell_order_info['presell_order_id'];
        
        if ($presell_order_id != 0) {
            // 更新支付流水号
            $order_service -> createNewOutTradeNoReturnBalancePresellOrder($presell_order_id);
            $new_out_trade_no = $order_service->getPresellOrderNewOutTradeNo($presell_order_id);
            $url = __URL(__URL__ . '/wap/pay/pay?out_trade_no=' . $new_out_trade_no);
            header("Location: " . $url);
            exit();
        }
    }
    
    /**
     * 处理自提地址的排序
     * @param unknown $address
     * @param unknown $pickup_point_list
     */
    public function pickupPointListSort($address, $pickup_point_list){
        $arr = array();
        if(!empty($address) && !empty($pickup_point_list)){
            $district_arr = array();
            $city_arr = array();
            $province_arr = array();
            foreach ($pickup_point_list as $key => $pickup_point){
                if($pickup_point["district_id"] == $address["district"]){
                    array_push($district_arr, $pickup_point_list[$key]);
                    unset($pickup_point_list[$key]);
                }elseif($pickup_point["city_id"] == $address["city"]){
                    array_push($city_arr, $pickup_point_list[$key]);
                    unset($pickup_point_list[$key]);
                }elseif($pickup_point["province_id"] == $address["province"]){
                    array_push($province_arr, $pickup_point_list[$key]);
                    unset($pickup_point_list[$key]);
                }
            }
            $arr = array_merge($district_arr, $city_arr, $province_arr, $pickup_point_list);
        }
        return $arr;
    }
    
    /**
     * 买家提货
     */
    public function memberPickup(){
        $order_id = request()->get('orderId', 0);
        $order_service = new OrderService();
        // 通过order_id判断该订单是否属于当前用户
        $condition['order_id'] = $order_id;
        $condition['buyer_id'] = $this->uid;
        $order_count = $order_service->getOrderCount($condition);
        if ($order_count == 0) {
            $this->error("没有获取到订单信息");
        }
        $res = $order_service -> getOrderPickupInfo($order_id);
        if(!empty($res['picked_up_code'])){
            $url = __URL(__URL__ . '/wap/order/orderPickupCodeToExamine?order_id=' . $order_id );
            $upload_path = "upload/qrcode/order_pickup_code_qrcode";
            if (! file_exists($upload_path)) {
                mkdir($upload_path, 0777, true);
            }
            $qrcode_name = 'orderPickupCode_'.$order_id;
            $path = $upload_path .'/'.$qrcode_name;
            getQRcode($url, $upload_path, $qrcode_name);
        }
        $this->assign("qrcode_path", $path.'.png');
        return view($this->style. 'Order/memberPickup');
    }
    
    /**
     * 自提订单门店审核
     */
    public function orderPickupCodeToExamine(){
        $order_id = request()->get('order_id', 0);
        $order_service = new OrderService();
        // 判断该用户是否已登录
        if(empty($this->uid)){
            $_SESSION['login_pre_url'] = __URL(\think\Config::get('view_replace_str.APP_MAIN') . "/order/orderPickupCodeToExamine?order_id=".$order_id);
            $this->redirect("Login/index");
        }
        $res = $order_service -> getOrderPickupInfo($order_id);
        if(empty($res['picked_up_code'])){
            $this->error("未获取到自提信息！");
        }
        // 判断当前用户是否是该门店的审核员
        $isPickedUpAuditor = $order_service -> currUserIsPickedUpAuditor($res['picked_up_id'], $this->uid);
        if(!$isPickedUpAuditor){
            $this->error("您不是该门店的审核员！");
        }
        $detail = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            $this->error("没有获取到订单信息");
        }
        $this->assign("order", $detail);
        return view($this->style . 'Order/orderPickupCodeToExamine');
    }
    
    /**
     * 自提点审核员确认提货成功
     */
    public function pickedUpAuditorConfirmPickup(){
        if(request()->isAjax()){
            $order_id = request()->post("order_id", 0);
            $auditor_id = request()->post("auditor_id", 0);
            $buyer_name = request()->post("buyer_name", '');
            $buyer_phone = request()->post("buyer_phone", '');
            
            $order_service = new OrderService();
            $res = $order_service -> pickedUpAuditorConfirmPickup($order_id, $auditor_id, $buyer_name, $buyer_phone);
            return $res;
        }
    }
}