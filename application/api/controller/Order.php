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

use data\model\AlbumPictureModel;
use data\model\NsCartModel;
use data\model\NsGoodsModel;
use data\model\NsGoodsSkuModel;
use data\service\Config;
use data\service\Express;
use data\service\Order\OrderGoods;
use data\service\Order as OrderService;
use data\service\Order\Order as OrderOrderService;
use data\service\promotion\GoodsExpress as GoodsExpressService;
use data\service\promotion\GoodsMansong;
use data\service\Promotion;
use data\service\promotion\GoodsPreference;
use data\service\Shop;
use data\service\Member;
use data\service\Goods;
use data\service\GroupBuy;
use data\service\Order\OrderGroupBuy;
use data\service\promotion\PromoteRewardRule;
use data\service\WebSite;

/**
 * 订单控制器
 *
 * @author Administrator
 *        
 */
class Order extends BaseController
{

    /**
     * 获取订单相关数据
     */
    public function getOrderData()
    {
        $title = "获取订单类相关数据";
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
        $unpaid_goback = request()->post('unpaid_goback', '');
        $order_create_flag = request()->post('order_create_flag', '');
        $combo_id = request()->post('combo_id', '');
        $combo_buy_num = request()->post('combo_buy_num', '');
        
        // 订单创建标识，表示当前生成的订单详情已经创建好了。用途：订单创建成功后，返回上一个界面的路径是当前创建订单的详情，而不是首页
        if (! empty($order_create_flag)) {
            $data = array(
                code => 10,
                data => $_SESSION['unpaid_goback']
            );
            return $this->outMessage($title, $data);
        }
        
        $order_tag = request()->post('order_tag', 'buy_now');
        if (empty($order_tag)) {
            return $this->outMessage($title, null, - 50, '无法获取商品信息');
        }
        
        $order_goods_type = request()->post('order_goods_type', '1');
        $order_sku_list = request()->post('order_sku_list', '250:1');
        $cart_list = request()->post('cart_list', '');
        // 判断实物类型：实物商品，虚拟商品
        if ($order_tag == "buy_now" && $order_goods_type === "0") {
            // 虚拟商品
            $data = $this->virtualOrderInfo($order_sku_list);
            if ($data['code'] == - 50) {
                return $this->outMessage($title, null, - 50, $data['message']);
            }
        } elseif ($order_tag == "combination_packages") {
            // 组合套餐
            $data = $this->comboPackageorderInfo($order_sku_list, $combo_id, $combo_buy_num);
            if ($data['code'] == - 50) {
                return $this->outMessage($title, null, - 50, $data['message']);
            }
        } elseif ($order_tag == "groupbuy") {
            // 团购
            $data = $this->orderGroupBuyInfo($order_sku_list);
            if ($data['code'] == - 50) {
                return $this->outMessage($title, null, - 50, $data['message']);
            }
        } elseif ($order_tag == "js_point_exchange") {
            // 积分兑换
            $data = $this->pointExchangeOrderInfo($order_sku_list);
            if ($data['code'] == - 50) {
                return $this->outMessage($title, null, - 50, $data['message']);
            }
        } else {
            // 实物商品
            $data = $this->orderInfo($order_tag, $order_sku_list, $cart_list);
            if ($data['code'] == - 50) {
                return $this->outMessage($title, null, - 50, $data['message']);
            }
        }
        // 配送时间段
        $config = new Config();
        $distribution_time_out = $config->getConfig(0, "DISTRIBUTION_TIME_SLOT");
        if (! empty($distribution_time_out["value"])) {
            $data["distribution_time_out"] = $distribution_time_out["value"];
        } else {
            $data["distribution_time_out"] = "";
        }
        // 当前时间
        $data['currentTime'] = time() * 1000;
        return $this->outMessage($title, $data);
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
    public function orderInfo($order_tag, $order_sku_list, $cart_list)
    {
        $member = new Member();
        $order = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config = new Config();
        $promotion = new Promotion();
        $shop_service = new Shop();
        $goods_preference = new GoodsPreference(); // 商品优惠价格操作类
                                                   // 检测购物车
        $data['order_tag'] = $order_tag;
        
        switch ($order_tag) {
            case "buy_now":
                
                // 立即购买
                $res = $this->buyNowSession($order_sku_list);
                if ($res['code'] == - 50) {
                    return array(
                        'code' => - 50,
                        'message' => $res['message']
                    );
                }
                $goods_sku_list = $res["goods_sku_list"];
                $list = $res["list"];
                break;
            case "cart":
                
                // 加入购物车
                $res = $this->addShoppingCartSession($cart_list);
                if ($res['code'] == - 50) {
                    return array(
                        'code' => - 50,
                        'message' => $res['message']
                    );
                }
                $goods_sku_list = $res["goods_sku_list"];
                $list = $res["list"];
                break;
            case "goods_presell":
                
                // 预售
                $res = $this->buyNowSession($order_sku_list);
                $goods_sku_list = $res["goods_sku_list"];
                $list = $res["list"];
                
                $presell_money = $goods_preference->getGoodsPresell($res["goods_sku_list"]);
                $data['presell_money'] = $presell_money;
                break;
        }
        $data['goods_sku_list'] = $goods_sku_list;
        
        $discount_money = $goods_mansong->getGoodsMansongMoney($goods_sku_list);
        $data['discount_money'] = sprintf("%.2f", $discount_money); // 总优惠
        
        $count_money = $order->getGoodsSkuListPrice($goods_sku_list);
        $data['count_money'] = sprintf("%.2f", $count_money); // 商品金额
        
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
            $data['address_is_have'] = 1;
            if (IS_SUPPORT_O2O == 1) {
                // 本地配送
                $o2o_distribution = $goods_express_service->getGoodsO2oPrice($count_money - $discount_money, 0, $address['province'], $address['city'], $address['district'], 0);
                
                if ($o2o_distribution >= 0) {
                    $data["o2o_distribution"] = $o2o_distribution;
                    $data["is_open_o2o_distribution"] = 1;
                } else {
                    $data["is_open_o2o_distribution"] = 0;
                }
            } else {
                $data["is_open_o2o_distribution"] = 0;
            }
        } else {
            $data['address_is_have'] = 0;
            $data["is_open_o2o_distribution"] = 0;
        }
        $count = $goods_express_service->getExpressCompanyCount($this->instance_id);
        $data['express_company_count'] = $count; // 物流公司数量
        $data['express'] = sprintf("%.2f", $express); // 运费
        $data['express_company_list'] = $express_company_list; // 物流公司
        
        $pick_up_money = $order->getPickupMoney($count_money);
        $data['pick_up_money'] = $pick_up_money;
        
        $count_point_exchange = 0;
        $max_use_point = 0; // 最大可使用积分数
        
        foreach ($list as $k => $v) {
            $list[$k]['price'] = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
            $max_use_point += $v["max_use_point"];
        }
        $data['count_point_exchange'] = $count_point_exchange; // 总积分
        $data['itemlist'] = $list;
        
        $shop_id = $this->instance_id;
        $shop_config = $Config->getShopConfig($shop_id);
        $order_invoice_content = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (! empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        $data['shop_config'] = $shop_config; // 后台配置
        
        $member_account = $member->getMemberAccount($this->uid, $this->instance_id);
        if ($member_account['balance'] == '' || $member_account['balance'] == 0) {
            $member_account['balance'] = '0.00';
        }
        $data['member_account'] = $member_account; // 用户余额
        
        if ($order_tag !== 'goods_presell') {
            $coupon_list = $order->getMemberCouponList($goods_sku_list);
        } else {
            $coupon_list = null;
        }
        
        if (! empty($coupon_list)) {
            $new_coupon_list = array();
            foreach ($coupon_list as $k => $v) {
                $new_coupon_list[$k] = array(
                    'money' => $v['money'],
                    'at_least' => $v['at_least'],
                    'coupon_id' => $v['coupon_id'],
                    'coupon_code' => $v['coupon_code'],
                    'coupon_name' => $v['coupon_name'],
                    'coupon_type_id' => $v['coupon_type_id'],
                    'create_order_id' => $v['create_order_id'],
                    'end_time' => $v['end_time'],
                    'fetch_time' => $v['fetch_time'],
                    'get_type' => $v['get_type'],
                    'shop_id' => $v['shop_id'],
                    'start_time' => $v['start_time'],
                    'state' => $v['state'],
                    'uid' => $v['uid'],
                    'use_order_id' => $v['use_order_id'],
                    'use_time' => $v['use_time']
                );
            }
            
            array_multisort($new_coupon_list, SORT_DESC);
        }
        $data['old_coupon_list'] = $new_coupon_list;
        $data['coupon_list'] = $new_coupon_list; // 获取优惠券
        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        if (! empty($address)) {
            $no_mail = checkIdIsinIdArr($address['city'], $promotion_full_mail['no_mail_city_id_array']);
            if ($no_mail) {
                $promotion_full_mail['is_open'] = 0;
            }
        }
        $data['promotion_full_mail'] = $promotion_full_mail; // 满额包邮
        
        $pickup_point_list = $shop_service->getPickupPointList();
        $pickup_point_list["data"] = $this->pickupPointListSort($address, $pickup_point_list["data"]);
        $data['pickup_point_list'] = $pickup_point_list; // 自提地址列表
        
        $data['address_default'] = $address;
        
        $goods_mansong_gifts = $this->getOrderGoodsMansongGifts($goods_sku_list);
        $data['goods_mansong_gifts'] = $goods_mansong_gifts; // 赠品列表
                                                             
        // 本地配送时间
        $distribution_time = $this->getDistributionTime();
        $data['distribution_time'] = $distribution_time;
        
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
        $data["point_config"] = $point_config;
        $data["max_use_point"] = $max_use_point;
        $data["default_use_point"] = $default_use_point;
        
        return $data;
    }

    /**
     * 待付款订单需要的数据 虚拟订单
     * 2017年6月28日 15:24:48 王永杰
     */
    public function virtualOrderInfo($order_sku_list)
    {
        if ($this->getIsOpenVirtualGoodsConfig() == 0) {
            return array(
                'code' => - 50,
                'message' => "未开启虚拟商品功能"
            );
        }
        $member = new Member();
        $order = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config = new Config();
        $promotion = new Promotion();
        $shop_service = new Shop();
        $goods_preference = new GoodsPreference();
        $res = $this->buyNowSession($order_sku_list);
        if ($res['code'] == - 50) {
            return array(
                'code' => $res['code'],
                'message' => $res['message']
            );
        }
        $goods_sku_list = $res["goods_sku_list"];
        $list = $res["list"];
        $shop_id = $this->instance_id;
        $data['goods_sku_list'] = $goods_sku_list;
        
        $discount_money = $goods_mansong->getGoodsMansongMoney($goods_sku_list);
        $data['discount_money'] = sprintf("%.2f", $discount_money); // 总优惠
        
        $count_money = $order->getGoodsSkuListPrice($goods_sku_list);
        $data['count_money'] = sprintf("%.2f", $count_money); // 商品金额
        $count_point_exchange = 0;
        
        $max_use_point = $goods_preference->getMaxUsePoint($goods_sku_list); // 最大可使用积分数
        foreach ($list as $k => $v) {
            $list[$k]['price'] = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
        }
        $data['count_point_exchange'] = $count_point_exchange; // 总积分
        $data['itemlist'] = $list;
        
        $shop_config = $Config->getShopConfig($shop_id);
        $order_invoice_content = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (! empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        $data['shop_config'] = $shop_config; // 后台配置
        
        $member_account = $member->getMemberAccount($this->uid, $shop_id);
        if ($member_account['balance'] == '' || $member_account['balance'] == 0) {
            $member_account['balance'] = '0.00';
        }
        $data['member_account'] = $member_account; // 用户余额
        $coupon_list = $order->getMemberCouponList($goods_sku_list);
        if (! empty($coupon_list)) {
            $new_coupon_list = array();
            foreach ($coupon_list as $k => $v) {
                $new_coupon_list[$k] = array(
                    'money' => $v['money'],
                    'at_least' => $v['at_least'],
                    'coupon_id' => $v['coupon_id'],
                    'coupon_code' => $v['coupon_code'],
                    'coupon_name' => $v['coupon_name'],
                    'coupon_type_id' => $v['coupon_type_id'],
                    'create_order_id' => $v['create_order_id'],
                    'end_time' => $v['end_time'],
                    'fetch_time' => $v['fetch_time'],
                    'get_type' => $v['get_type'],
                    'shop_id' => $v['shop_id'],
                    'start_time' => $v['start_time'],
                    'state' => $v['state'],
                    'uid' => $v['uid'],
                    'use_order_id' => $v['use_order_id'],
                    'use_time' => $v['use_time']
                );
            }
            
            array_multisort($new_coupon_list, SORT_DESC);
        }
        $data['old_coupon_list'] = $new_coupon_list;
        $data['coupon_list'] = $new_coupon_list; // 获取优惠券
        
        $user_telephone = $member->getUserTelephone();
        $data['user_telephone'] = $user_telephone;
        
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
        $data["point_config"] = $point_config;
        $data["max_use_point"] = $max_use_point;
        $data["default_use_point"] = $default_use_point;
        
        return $data;
    }

    /**
     * 待付款订单需要的数据 组合套餐
     * 2017年11月22日 10:07:26 王永杰
     */
    public function comboPackageorderInfo($order_sku_list, $combo_id, $combo_buy_num)
    {
        $member = new Member();
        $order = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config = new Config();
        $promotion = new Promotion();
        $shop_service = new Shop();
        $goods_express_service = new GoodsExpressService();
        $goods_preference = new GoodsPreference();
        $res = $this->combination_packagesSession($order_sku_list, $combo_id, $combo_buy_num); // 获取组合套餐session
        if ($res['code'] == - 50) {
            return array(
                'code' => $res['code'],
                'message' => $res['message']
            );
        }
        // 套餐信息
        $combo_id = $res["combo_id"];
        $combo_detail = $promotion->getComboPackageDetail($combo_id);
        $data['combo_detail'] = $combo_detail;
        $data['combo_buy_num'] = $res["combo_buy_num"];
        
        $goods_sku_list = $res["goods_sku_list"];
        $list = $res["list"];
        
        $goods_sku_list = trim($goods_sku_list);
        if (empty($goods_sku_list)) {
            return array(
                'code' => - 50,
                'message' => '待支付订单中商品不可为空'
            );
        }
        $data['goods_sku_list'] = $goods_sku_list; // 商品sku列表
        
        $combo_package_price = $combo_detail["combo_package_price"] * $res["combo_buy_num"]; // 套餐总金额
        $data["combo_package_price"] = $combo_package_price;
        
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
            $data['address_is_have'] = 1;
            if (IS_SUPPORT_O2O == 1) {
                // 本地配送
                $o2o_distribution = $goods_express_service->getGoodsO2oPrice($combo_package_price, 0, $address['province'], $address['city'], $address['district'], 0);
                if ($o2o_distribution >= 0) {
                    $data["o2o_distribution"] = $o2o_distribution;
                    $data["is_open_o2o_distribution"] = 1;
                } else {
                    $data["is_open_o2o_distribution"] = 0;
                }
            } else {
                $data["is_open_o2o_distribution"] = 0;
            }
        } else {
            $data['address_is_have'] = 0;
            $data["is_open_o2o_distribution"] = 0;
        }
        $count = $goods_express_service->getExpressCompanyCount($this->instance_id);
        $data['express_company_count'] = $count; // 物流公司数量
        $data['express'] = sprintf("%.2f", $express); // 运费
        $data['express_company_list'] = $express_company_list; // 物流公司
        
        $count_money = $order->getComboPackageGoodsSkuListPrice($goods_sku_list); // 商品金额
        $data['count_money'] = sprintf("%.2f", $count_money);
        
        $discount_money = $count_money - ($combo_detail["combo_package_price"] * $res["combo_buy_num"]); // 计算优惠金额
        $discount_money = $discount_money < 0 ? 0 : $discount_money;
        $data['discount_money'] = sprintf("%.2f", $discount_money); // 总优惠
                                                                    
        // 计算自提点运费
        $pick_up_money = $order->getPickupMoney($combo_package_price);
        if (empty($pick_up_money)) {
            $pick_up_money = 0;
        }
        $data['pick_up_money'] = $pick_up_money;
        $count_point_exchange = 0;
        $max_use_point = $goods_preference->getMaxUsePoint($goods_sku_list); // 最大可使用积分数
        foreach ($list as $k => $v) {
            $list[$k]['price'] = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
        }
        $data['itemlist'] = $list;
        $shop_id = $this->instance_id;
        $shop_config = $Config->getShopConfig($shop_id); // 后台配置
        $order_invoice_content = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (! empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        
        $data['shop_config'] = $shop_config;
        $member_account = $member->getMemberAccount($this->uid, $this->instance_id);
        if ($member_account['balance'] == '' || $member_account['balance'] == 0) {
            $member_account['balance'] = '0.00';
        }
        $data['member_account'] = $member_account; // 用户余额
        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        if (! empty($address)) {
            $no_mail = checkIdIsinIdArr($address['city'], $promotion_full_mail['no_mail_city_id_array']);
            if ($no_mail) {
                $promotion_full_mail['is_open'] = 0;
            }
        }
        $data['promotion_full_mail'] = $promotion_full_mail; // 满额包邮
        
        $pickup_point_list = $shop_service->getPickupPointList();
        $data['pickup_point_list'] = $pickup_point_list; // 自提地址列表
        
        $data['address_default'] = $address;
        
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
        $data['point_config'] = $point_config;
        $data['default_use_point'] = $default_use_point;
        return $data;
    }

    /**
     * 待付款订单需要的数据(团购)
     * 2017年6月28日 15:24:48 王永杰
     */
    public function orderGroupBuyInfo($order_sku_list)
    {
        $member = new Member();
        $order = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config = new Config();
        $promotion = new Promotion();
        $shop_service = new Shop();
        
        // 检测购物车
        $res = $this->groupBuySession($order_sku_list);
        if ($res['code'] == - 50) {
            return array(
                'code' => $res['code'],
                'message' => $res['message']
            );
        }
        $goods_sku_list = $res["goods_sku_list"];
        $list = $res["list"];
        
        $data['goods_sku_list'] = $goods_sku_list;
        
        $order_group_buy = new OrderGroupBuy();
        $count_money = $order_group_buy->getGoodsSkuGroupBuyPrice($goods_sku_list);
        $data['count_money'] = sprintf("%.2f", $count_money); // 商品金额
        
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
            $data['address_is_have'] = 1;
            if (IS_SUPPORT_O2O == 1) {
                // 本地配送
                $o2o_distribution = $goods_express_service->getGoodsO2oPrice($count_money, 0, $address['province'], $address['city'], $address['district'], 0);
                if ($o2o_distribution >= 0) {
                    $data["o2o_distribution"] = $o2o_distribution;
                    $data['is_open_o2o_distribution'] = 1;
                } else {
                    $data['is_open_o2o_distribution'] = 0;
                }
            } else {
                $data['is_open_o2o_distribution'] = 0;
            }
        } else {
            $data['address_is_have'] = 0;
            $data['is_open_o2o_distribution'] = 0;
        }
        $count = $goods_express_service->getExpressCompanyCount($this->instance_id);
        $data['express_company_count'] = $count; // 物流公司数量
        $data['express'] = sprintf("%.2f", $express); // 运费
        $data['express_company_list'] = $express_company_list; // 物流公司
        
        $discount_money = 0;
        $data['discount_money'] = sprintf("%.2f", $discount_money); // 总优惠
        
        $pick_up_money = $order->getPickupMoney($count_money);
        $data['pick_up_money'] = $pick_up_money;
        $count_point_exchange = 0;
        $max_use_point = 0;
        foreach ($list as $k => $v) {
            $list[$k]['price'] = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
            $max_use_point += $v["max_use_point"];
        }
        $data['count_point_exchange'] = $count_point_exchange; // 总积分
        $data['itemlist'] = $list;
        
        $shop_id = $this->instance_id;
        $shop_config = $Config->getShopConfig($shop_id);
        $order_invoice_content = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (! empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        $data['shop_config'] = $shop_config; // 后台配置
        
        $member_account = $member->getMemberAccount($this->uid, $this->instance_id);
        if ($member_account['balance'] == '' || $member_account['balance'] == 0) {
            $member_account['balance'] = '0.00';
        }
        $data['member_account'] = $member_account; // 用户余额
        
        $coupon_list = $order->getMemberCouponList($goods_sku_list);
        if (! empty($coupon_list)) {
            $new_coupon_list = array();
            foreach ($coupon_list as $k => $v) {
                $new_coupon_list[$k] = array(
                    'money' => $v['money'],
                    'at_least' => $v['at_least'],
                    'coupon_id' => $v['coupon_id'],
                    'coupon_code' => $v['coupon_code'],
                    'coupon_name' => $v['coupon_name'],
                    'coupon_type_id' => $v['coupon_type_id'],
                    'create_order_id' => $v['create_order_id'],
                    'end_time' => $v['end_time'],
                    'fetch_time' => $v['fetch_time'],
                    'get_type' => $v['get_type'],
                    'shop_id' => $v['shop_id'],
                    'start_time' => $v['start_time'],
                    'state' => $v['state'],
                    'uid' => $v['uid'],
                    'use_order_id' => $v['use_order_id'],
                    'use_time' => $v['use_time']
                );
            }
            
            array_multisort($new_coupon_list, SORT_DESC);
        }
        $data['old_coupon_list'] = $new_coupon_list;
        $data['coupon_list'] = $new_coupon_list; // 获取优惠券
        
        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        if (! empty($address)) {
            $no_mail = checkIdIsinIdArr($address['city'], $promotion_full_mail['no_mail_city_id_array']);
            if ($no_mail) {
                $promotion_full_mail['is_open'] = 0;
            }
        }
        $data["promotion_full_mail"] = $promotion_full_mail; // 满额包邮
        
        $pickup_point_list = $shop_service->getPickupPointList();
        $data["pickup_point_list"] = $pickup_point_list; // 自提地址列表
        
        $data["address_default"] = $address;
        
        $goods_mansong_gifts = $this->getOrderGoodsMansongGifts($goods_sku_list);
        $data["goods_mansong_gifts"] = $goods_mansong_gifts; // 赠品列表
                                                             
        // 本地配送时间
        $distribution_time = $this->getDistributionTime();
        $data['distribution_time'] = $distribution_time;
        
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
        $data["point_config"] = $point_config;
        $data["max_use_point"] = $max_use_point;
        $data["default_use_point"] = $default_use_point;
        
        return $data;
    }

    /**
     * 团购
     */
    public function groupBuySession($order_sku_list)
    {
        if (empty($order_sku_list)) {
            return array(
                'code' => - 50,
                'message' => '无法获取所选商品信息'
            );
        }
        
        $cart_list = array();
        $order_sku_list = explode(":", $order_sku_list);
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
            return array(
                'code' => - 50,
                'message' => '无法获取所选商品信息'
            );
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
            $message = $goods_info['state'] == 0 ? '商品已下架' : '商品违规禁售';
            return array(
                'code' => - 50,
                'message' => $message
            );
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
            return array(
                'code' => - 50,
                'message' => '无法获取所选商品信息'
            );
        }
        // 赋予商品团购价
        $cart_list["price"] = $group_price;
        // 获取图片信息
        $album_picture_model = new AlbumPictureModel();
        $picture_info = $album_picture_model->get($picture == 0 ? $goods_info['picture'] : $picture);
        $cart_list['picture_info'] = $picture_info;
        
        if (count($cart_list) == 0) {
            return array(
                'code' => - 50,
                'message' => '无法获取所选商品信息'
            ); // 没有商品返回到首页
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
        $title = '创建团购订单';
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
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
                   
        // 查询商品限购
        $order_id = $group_buy_service->groupBuyOrderCreate(1, $out_trade_no, $pay_type, $shipping_type, 1, 1, $leavemessage, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $integral, $user_money, $goods_sku_list, 0, $pick_up_id, $express_company_id, $coin);
        // Log::write($order_id);
        if ($order_id > 0) {
            $order->deleteCart($goods_sku_list, $this->uid);
            $data['out_trade_no'] = $out_trade_no;
            return $this->outMessage($title, $data);
        } else {
            $data['order_id'] = $order_id;
            $message = $this->orderErrorMessage($order_id, "订单生成失败!");
            return $this->outMessage($title, $data, "-10", $message);
        }
    }

    /**
     * 加入购物车
     *
     * @return unknown
     */
    public function addShoppingCartSession($session_cart_list)
    {
        // 加入购物车
        if ($session_cart_list == "") {
            return array(
                'code' => - 50,
                'message' => '无法获取所选商品信息'
            ); // 没有商品
        }
        
        $cart_id_arr = explode(",", $session_cart_list);
        $goods = new Goods();
        $cart_list = $goods->getCartList($session_cart_list);
        if (count($cart_list) == 0) {
            return array(
                'code' => - 50,
                'message' => '无法获取所选商品信息'
            ); // 没有商品 // 没有商品
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
    public function buyNowSession($order_sku_list)
    {
        if (empty($order_sku_list)) {
            return array(
                'code' => - 50,
                'message' => '无法获取所选商品信息'
            ); // 没有商品
        }
        
        $cart_list = array();
        $order_sku_list = explode(":", $order_sku_list);
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
            return array(
                'code' => - 50,
                'message' => '无法获取所选商品信息'
            ); // 没有商品返回到首页
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
            $message = $goods_info['state'] == 0 ? '商品已下架' : '商品违规禁售';
            return array(
                'code' => - 50,
                'message' => $message
            );
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
            return array(
                'code' => - 50,
                'message' => '无法获取所选商品信息'
            ); // 没有商品返回到首页
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
    public function combination_packagesSession($order_sku, $combo_id, $combo_buy_num)
    {
        // $order_sku = isset($_SESSION["order_sku"]) ? $_SESSION["order_sku"] : "";
        if (empty($order_sku)) {
            return array(
                'code' => - 50,
                'message' => '无法获取所选商品信息'
            );
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
                return array(
                    'code' => - 50,
                    'message' => '无法获取所选商品信息'
                );
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
                $message = $goods_info['state'] == 0 ? '商品已下架' : '商品违规禁售';
                return array(
                    'code' => - 50,
                    'message' => $message
                );
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
                return array(
                    'code' => - 50,
                    'message' => '无法获取所选商品信息'
                );
            }
            $list[] = $cart_list;
            $goods_sku_list = $sku_id . ":" . $num; // 商品skuid集合
            $res["list"] = $list;
        }
        $res["goods_sku_list"] = $order_sku;
        $res["combo_id"] = $combo_id;
        $res["combo_buy_num"] = $combo_buy_num;
        return $res;
    }

    /**
     * 积分兑换商品信息
     */
    public function pointExchangeOrderInfo($order_sku_list)
    {
        $member = new Member();
        $order = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config = new Config();
        $promotion = new Promotion();
        $shop_service = new Shop();
        $goods_express_service = new GoodsExpressService();
        $goods_preference = new GoodsPreference(); // 商品优惠价格操作类
        
        $res = $this->buyNowSession($order_sku_list);
        $goods_sku_list = $res["goods_sku_list"];
        $list = $res["list"];
        
        $goods_sku_list = trim($goods_sku_list);
        if (empty($goods_sku_list)) {
            return array(
                'code' => - 50,
                'message' => '待支付订单中商品不可为空'
            );
        }
        $data['goods_sku_list'] = $goods_sku_list; // 商品sku列表
                                                   
        // 积分兑换只会有一种商品 只有商品兑换类型为 积分与金额同时购买时才计算优惠和商品金额
        $discount_money = 0;
        $count_money = $order->getGoodsSkuListPrice($goods_sku_list); // 商品金额
        if ($list[0]["point_exchange_type"] == 1) {
            $discount_money = $goods_mansong->getGoodsMansongMoney($goods_sku_list); // 计算优惠金额
        }
        $data["discount_money"] = sprintf("%.2f", $discount_money); // 总优惠
        $data["count_money"] = sprintf("%.2f", $count_money); // 商品金额
        $data["point_exchange_type"] = $list[0]["point_exchange_type"]; // 积分兑换类型
        
        $addresslist = $member->getMemberExpressAddressList(1, 0, '', ' is_default DESC'); // 地址查询
        if (empty($addresslist["data"])) {
            $data["address_list"] = 0;
        } else {
            $data["address_list"] = $addresslist["data"]; // 选择收货地址
        }
        
        $address = $member->getDefaultExpressAddress(); // 查询默认收货地址
        $data["address_default"] = $address;
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
            $data["address_is_have"] = 1;
            if (IS_SUPPORT_O2O == 1) {
                // 本地配送
                $o2o_distribution = $goods_express_service->getGoodsO2oPrice($count_money - $discount_money, 0, $address['province'], $address['city'], $address['district'], 0);
                if ($o2o_distribution >= 0) {
                    $data["o2o_distribution"] = $o2o_distribution;
                    $data["is_open_o2o_distribution"] = 1;
                } else {
                    $data["is_open_o2o_distribution"] = 0;
                }
            } else {
                $data["is_open_o2o_distribution"] = 0;
            }
        } else {
            $data["is_open_o2o_distribution"] = 0;
            $data["address_is_have"] = 0;
        }
        $count = $goods_express_service->getExpressCompanyCount($this->instance_id);
        $data["express_company_count"] = $count; // 物流公司数量
        $data["express"] = sprintf("%.2f", $express); // 运费
        $data["express_company_list"] = $express_company_list; // 物流公司
                                                               
        // 计算自提点运费
        $pick_up_money = $order->getPickupMoney($count_money);
        if (empty($pick_up_money)) {
            $pick_up_money = 0;
        }
        $data["pick_up_money"] = $pick_up_money;
        
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
        $data["itemlist"] = $list; // 格式化后的列表
        $data["count_point_exchange"] = $count_point_exchange; // 总积分
        
        $shop_id = $this->instance_id;
        $shop_config = $Config->getShopConfig($shop_id);
        $order_invoice_content = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (! empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        $data["shop_config"] = $shop_config; // 后台配置
        
        $coupon_list = array();
        if ($list[0]["point_exchange_type"] == 1) {
            $coupon_list = $order->getMemberCouponList($goods_sku_list); // 获取优惠券
            foreach ($coupon_list as $k => $v) {
                $coupon_list[$k]['start_time'] = substr($v['start_time'], 0, stripos($v['start_time'], " ") + 1);
                $coupon_list[$k]['end_time'] = substr($v['end_time'], 0, stripos($v['end_time'], " ") + 1);
            }
        }
        if (! empty($coupon_list)) {
            $new_coupon_list = array();
            foreach ($coupon_list as $k => $v) {
                $new_coupon_list[$k] = array(
                    'money' => $v['money'],
                    'at_least' => $v['at_least'],
                    'coupon_id' => $v['coupon_id'],
                    'coupon_code' => $v['coupon_code'],
                    'coupon_name' => $v['coupon_name'],
                    'coupon_type_id' => $v['coupon_type_id'],
                    'create_order_id' => $v['create_order_id'],
                    'end_time' => $v['end_time'],
                    'fetch_time' => $v['fetch_time'],
                    'get_type' => $v['get_type'],
                    'shop_id' => $v['shop_id'],
                    'start_time' => $v['start_time'],
                    'state' => $v['state'],
                    'uid' => $v['uid'],
                    'use_order_id' => $v['use_order_id'],
                    'use_time' => $v['use_time'],
                    'start_time' => $v['start_time'],
                    'end_time' => $v['end_time']
                );
            }
            
            array_multisort($new_coupon_list, SORT_DESC);
        }
        $data['old_coupon_list'] = $new_coupon_list;
        $data['coupon_list'] = $new_coupon_list; // 获取优惠券
        
        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        if (! empty($address)) {
            $no_mail = checkIdIsinIdArr($address['city'], $promotion_full_mail['no_mail_city_id_array']);
            if ($no_mail) {
                $promotion_full_mail['is_open'] = 0;
            }
        }
        $data["promotion_full_mail"] = $promotion_full_mail; // 满额包邮
        
        $pickup_point_list = $shop_service->getPickupPointList();
        $pickup_point_list["data"] = $this->pickupPointListSort($address, $pickup_point_list["data"]);
        $data["pickup_point_list"] = $pickup_point_list; // 自提地址列表
        
        $goods_mansong_gifts = $this->getOrderGoodsMansongGifts($goods_sku_list);
        $data["goods_mansong_gifts"] = $goods_mansong_gifts;
        
        $member = new Member();
        $member_account = $member->getMemberAccount($this->uid, $this->instance_id); // 用户余额
        $data["member_account"] = $member_account; // 用户余额、积分
                                                   
        // 本地配送时间
        $distribution_time = $this->getDistributionTime();
        $data['distribution_time'] = $distribution_time;
        
        return $data;
    }

    /**
     * 创建订单
     */
    public function orderCreate()
    {
        $title = "创建订单";
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
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
        $pick_up_id = request()->post("pick_up_id", 0); // 自提点
        $shipping_company_id = request()->post("shipping_company_id", 0); // 物流公司
        $shipping_time = request()->post("shipping_time", 0); // 配送时间
        $shipping_type = request()->post("shipping_type", 1); // 配送方式，1：物流，2：自提 3：本地配送
        $distribution_time_out = request()->post("distribution_time_out", '');
        
        $member = new Member();
        $address = $member->getDefaultExpressAddress();
        $coin = 0; // 购物币
        $buyer_ip = request()->ip();
        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        if (! empty($purchase_restriction)) {
            
            return $this->outMessage($title, '', "-50", $purchase_restriction);
        } else {
            
            $order_id = $order->orderCreate('1', $out_trade_no, $pay_type, $shipping_type, '1', $buyer_ip, $leavemessage, $buyer_invoice, $shipping_time, $address['mobile'], $address['province'], $address['city'], $address['district'], $address["address_info"] . '&nbsp;' . $address['address'], $address['zip_code'], $address['consigner'], $integral, $use_coupon, 0, $goods_sku_list, $user_money, $pick_up_id, $shipping_company_id, $coin, $address["phone"], $distribution_time_out);
            // 订单创建标识，表示当前生成的订单详情已经创建好了。用途：订单创建成功后，返回上一个界面的路径是当前创建订单的详情，而不是首页
            if ($order_id > 0) {
                $order->deleteCart($goods_sku_list, $this->uid);
                $data = array(
                    'out_trade_no' => $out_trade_no
                );
                return $this->outMessage($title, $data);
            } else {
                $data = array(
                    'order_id' => $order_id
                );
                $message = $this->orderErrorMessage($order_id, "订单生成失败!");
                return $this->outMessage($title, $data, "-10", $message);
            }
        }
    }

    /**
     * 订单错误消息
     *
     * @param unknown $order_id            
     * @param unknown $message            
     */
    public function orderErrorMessage($order_id, $message)
    {
        switch ($order_id) {
            case - 4012:
                $message = '当前收货地址暂不支持配送';
                break;
            case - 4005:
                $message = '订单已支付';
                break;
            case - 4010:
                $message = '店铺积分功能未开启';
                break;
            case - 4011:
                $message = '用户购物币不足';
                break;
            case - 4004:
                $message = '用户积分不足';
                break;
            case - 4003:
                $message = '库存不足';
                break;
            case - 4007:
                $message = '当前用户积分不足';
                break;
            case - 4008:
                $message = '当前用户余额不足';
                break;
            case - 4014:
                $message = '当前地址不支持货到付款';
                break;
        }
        return $message;
    }

    /**
     * 预售订单创建
     */
    public function presellOrderCreate()
    {
        $title = '创建预售订单';
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
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
        $pick_up_id = request()->post("pick_up_id", 0); // 自提点
        $shipping_type = request()->post("shipping_type", 1); // 配送方式，1：商家配送，2：自提 3：本地配送
        $shipping_time = request()->post("shipping_time", 0); // 配送时间
        $express_company_id = request()->post("shipping_company_id", 0); // 物流公司
        $is_full_payment = request()->post('is_full_payment', 0);
        $distribution_time_out = request()->post("distribution_time_out", '');
        
        $member = new Member();
        $address = $member->getDefaultExpressAddress();
        $coin = 0; // 购物币
        $buyer_ip = request()->ip();
        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        if (! empty($purchase_restriction)) {
            
            return $this->outMessage($title, '', "-50", $purchase_restriction);
        } else {
            
            $order_id = $order->orderCreatePresell(6, $out_trade_no, $pay_type, $shipping_type, '1', $buyer_ip, $leavemessage, $buyer_invoice, $shipping_time, $address['mobile'], $address['province'], $address['city'], $address['district'], $address["address_info"] . '&nbsp;' . $address['address'], $address['zip_code'], $address['consigner'], $integral, $use_coupon, 0, $goods_sku_list, $user_money, $pick_up_id, $express_company_id, $coin, $address["phone"], $is_full_payment, $distribution_time_out);
            // 订单创建标识，表示当前生成的订单详情已经创建好了。用途：订单创建成功后，返回上一个界面的路径是当前创建订单的详情，而不是首页
            if ($order_id > 0) {
                $data = array(
                    'out_trade_no' => $out_trade_no
                );
                return $this->outMessage($title, $data);
            } else {
                $data = array(
                    'order_id' => $order_id
                );
                $message = $this->orderErrorMessage($order_id, "订单生成失败!");
                return $this->outMessage($title, $data, "-10", $message);
            }
        }
    }

    /**
     * 创建订单（虚拟商品）
     */
    public function virtualOrderCreate()
    {
        $title = '创建虚拟商品订单';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", - 50, "无法获取会员登录信息");
        }
        if ($this->getIsOpenVirtualGoodsConfig() == 0) {
            return $this->outMessage($title, - 50, - 50, '未开启虚拟商品功能');
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
            return $this->outMessage($title, - 50, - 50, $purchase_restriction);
        } else {
            $order_id = $order->orderCreateVirtual('2', $out_trade_no, $pay_type, $shipping_type, '1', $buyer_ip, $leavemessage, $buyer_invoice, $shipping_time, $integral, $use_coupon, 0, $goods_sku_list, $user_money, $pick_up_id, $express_company_id, $user_telephone);
            if ($order_id > 0) {
                $data = array(
                    'out_trade_no' => $out_trade_no
                );
                return $this->outMessage($title, $data);
            } else {
                $data = array(
                    'order_id' => $order_id
                );
                $message = $this->orderErrorMessage($order_id, "订单生成失败!");
                return $this->outMessage($title, $data, "-10", $message);
            }
        }
    }

    /**
     * 创建订单（组合商品）
     */
    public function comboPackageOrderCreate()
    {
        $title = '创建组合商品订单';
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
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
        $pick_up_id = request()->post("pick_up_id", 0); // 自提点
        $shipping_type = request()->post("shipping_type", 1); // 配送方式，1：物流，2：自提 3：本地配送
        $shipping_time = request()->post("shipping_time", 0); // 配送时间
        $express_company_id = request()->post("shipping_company_id", 0); // 物流公司
        $combo_package_id = request()->post("combo_package_id", 0); // 组合套餐id
        $buy_num = request()->post("buy_num", 1); // 购买套数
        $distribution_time_out = request()->post("distribution_time_out", '');
        
        $member = new Member();
        $address = $member->getDefaultExpressAddress();
        $coin = 0; // 购物币
        $buyer_ip = request()->ip();
        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        
        if (! empty($purchase_restriction)) {
            $res = array(
                "code" => 0,
                "message" => $purchase_restriction
            );
            return $this->outMessage($title, - 50, - 50, $purchase_restriction);
        } else {
            
            $order_id = $order->orderCreateComboPackage("3", $out_trade_no, $pay_type, $shipping_type, "1", $buyer_ip, $leavemessage, $buyer_invoice, $shipping_time, $address['mobile'], $address['province'], $address['city'], $address['district'], $address["address_info"] . '&nbsp;' . $address['address'], $address['zip_code'], $address['consigner'], $integral, 0, $goods_sku_list, $user_money, $pick_up_id, $express_company_id, $coin, $address["phone"], $combo_package_id, $buy_num, $distribution_time_out);
            if ($order_id > 0) {
                $data = array(
                    'out_trade_no' => $out_trade_no
                );
                return $this->outMessage($title, $data);
            } else {
                $data = array(
                    'order_id' => $order_id
                );
                $message = $this->orderErrorMessage($order_id, "订单生成失败!");
                return $this->outMessage($title, $data, "-10", $message);
            }
        }
    }

    /**
     * 创建订单（积分兑换）
     */
    public function pointExchangeOrderCreate()
    {
        $title = '创建积分商品兑换订单';
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
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
        $distribution_time_out = request()->post("distribution_time_out", '');
        
        $member = new Member();
        $address = $member->getDefaultExpressAddress();
        $coin = 0; // 购物币
        $buyer_ip = request()->ip();
        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        
        if (! empty($purchase_restriction)) {
            $res = array(
                "code" => 0,
                "message" => $purchase_restriction
            );
            return $this->outMessage($title, - 50, - 50, $purchase_restriction);
        } else {
            
            $order_id = $order->orderCreatePointExhange($order_type, $out_trade_no, $pay_type, $shipping_type, "1", $buyer_ip, $leavemessage, $buyer_invoice, $shipping_time, $address['mobile'], $address['province'], $address['city'], $address['district'], $address["address_info"] . '&nbsp;' . $address['address'], $address['zip_code'], $address['consigner'], $integral, $use_coupon, 0, $goods_sku_list, $user_money, $pick_up_id, $express_company_id, $coin, $address['phone'], $point_exchange_type, $order_goods_type, $user_telephone, $distribution_time_out);
            if ($order_id > 0) {
                $data = array(
                    'out_trade_no' => $out_trade_no
                );
                return $this->outMessage($title, $data);
            } else {
                $data = array(
                    'order_id' => $order_id
                );
                $message = $this->orderErrorMessage($order_id, "订单生成失败!");
                return $this->outMessage($title, $data, "-10", $message);
            }
        }
    }

    /**
     * 获取当前会员的订单列表
     */
    public function getOrderList()
    {
        $title = "获取会员订单列表";
        if (empty($this->uid)) {
            return $this->outMessage($title, null, '-9999', "无法获取会员登录信息");
        }
        $page_index = request()->post("page", 1);
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
        
        // 还要考虑状态逻辑
        $order = new OrderService();
        $order_list = $order->getOrderList($page_index, PAGESIZE, $condition, 'create_time desc');
        return $this->outMessage($title, $order_list);
    }

    /**
     * 获取当前会员的虚拟订单列表
     */
    public function myVirtualOrderList()
    {
        $title = "获取会员虚拟订单列表";
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
        
        if ($this->getIsOpenVirtualGoodsConfig() == 0) {
            return $this->outMessage($title, - 50, '-50', "未开启虚拟商品功能");
        }
        
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
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
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
        $condition['order_type'] = array(
            "in",
            "1,3,7"
        );
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

    /**
     * 虚拟订单详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function virtualOrderDetail()
    {
        $title = '虚拟订单详情';
        
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
        
        if ($this->getIsOpenVirtualGoodsConfig() == 0) {
            return $this->outMessage($title, - 50, '-50', "未开启虚拟商品功能");
        }
        
        $order_id = request()->post('order_id', 0);
        if (! is_numeric($order_id)) {
            return $this->outMessage($title, "", '-20', "没有获取到订单信息");
        }
        $order_service = new OrderService();
        $detail = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            return $this->outMessage($title, "", '-20', "没有获取到订单信息");
        }
        // 通过order_id判断该订单是否属于当前用户
        $condition['order_id'] = $order_id;
        $condition['buyer_id'] = $this->uid;
        $condition['order_type'] = 2;
        $order_count = $order_service->getOrderCount($condition);
        if ($order_count == 0) {
            return $this->outMessage($title, "", '-20', "没有获取到订单信息");
        }
        
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

    /**
     * 物流详情
     */
    public function orderExpress()
    {
        $title = "订单物流信息";
        $order_id = request()->post('orderId', 0);
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
        if (empty($order_id)) {
            return $this->outMessage($title, - 50, '-50', "没有获取到订单信息");
        }
        // 获取物流跟踪信息
        $order_service = new OrderService();
        $detail = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            return $this->outMessage($title, - 50, '-50', "没有获取到订单信息");
        }
        return $this->outMessage($title, $detail);
    }

    /**
     * 查询包裹物流信息
     * 2017年6月24日 10:42:34 王永杰
     */
    public function getOrderGoodsExpressMessage()
    {
        $title = "物流包裹信息";
        $express_id = request()->post("express_id", 0); // 物流包裹id
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
        $res = - 1;
        if ($express_id) {
            $order_service = new OrderService();
            $res = $order_service->getOrderGoodsExpressMessage($express_id);
            $res = array_reverse($res);
        }
        return $this->outMessage($title, $res);
    }

    /**
     * 订单项退款详情
     */
    public function refundDetail()
    {
        $title = "退款详情";
        $order_goods_id = request()->post('order_goods_id', 0);
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
        if (empty($order_goods_id)) {
            return $this->outMessage($title, - 50, '-50', "没有获取到退款信息");
        }
        
        $order_service = new OrderService();
        $detail = $order_service->getOrderGoodsRefundInfo($order_goods_id);
        $refund_money = $order_service->orderGoodsRefundMoney($order_goods_id);
        
        // 余额退款
        $order_goods_service = new OrderGoods();
        $refund_balance = $order_goods_service->orderGoodsRefundBalance($order_goods_id);
        // 查询店铺默认物流地址
        $express = new Express();
        $address = $express->getDefaultShopExpressAddress($this->instance_id);
        // 查询商家地址
        $shop_info = $order_service->getShopReturnSet($this->instance_id);
        // 查询订单所退运费
        $freight = $order_service->getOrderRefundFreight($order_goods_id);
        
        $data = array(
            'refund_detail' => $detail,
            'refund_money' => sprintf("%.2f", $refund_money),
            'refund_balance' => sprintf("%.2f", $refund_balance),
            'address_info' => $address,
            'shop_address' => $shop_info,
            'freight' => $freight
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 申请退款
     */
    public function orderGoodsRefundAskfor()
    {
        $title = "申请退款";
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
        $order_id = request()->post('order_id', 0);
        if (empty($order_id)) {
            return $this->outMessage($title, - 50, '-50', "无法获取订单信息");
        }
        $order_goods_id = request()->post('order_goods_id', 0);
        $refund_type = request()->post('refund_type', 1);
        $refund_require_money = request()->post('refund_require_money', 0);
        $refund_reason = request()->post('refund_reason', '');
        $order_service = new OrderService();
        $retval = $order_service->orderGoodsRefundAskfor($order_id, $order_goods_id, $refund_type, $refund_require_money, $refund_reason);
        return $this->outMessage($title, $retval);
    }

    /**
     * 买家退货
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function orderGoodsRefundExpress()
    {
        $title = "买家退货";
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
        $order_id = request()->post('order_id', 0);
        if (empty($order_id)) {
            return $this->outMessage($title, - 50, '-50', "无法获取订单");
        }
        $order_goods_id = request()->post('order_goods_id', 0);
        $refund_express_company = request()->post('refund_express_company', '');
        $refund_shipping_no = request()->post('refund_shipping_no', 0);
        $order_service = new OrderService();
        $retval = $order_service->orderGoodsReturnGoods($order_id, $order_goods_id, $refund_express_company, $refund_shipping_no);
        return $this->outMessage($title, $retval);
    }

    /**
     * 订单项退款详情 售后
     */
    public function customerDetail()
    {
        $title = "退款详情";
        $order_goods_id = request()->post('order_goods_id', 0);
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
        if (empty($order_goods_id)) {
            return $this->outMessage($title, - 50, '-50', "没有获取到退款信息");
        }
        $id = 0;
        $order_service = new OrderService();
        $detail = $order_service->getCustomerServiceInfo($id, $order_goods_id);
        $refund_money = $order_service->orderGoodsRefundMoney($order_goods_id);
        
        // 余额退款
        $order_goods_service = new OrderGoods();
        $refund_balance = $order_goods_service->orderGoodsRefundBalance($order_goods_id);
        // 查询店铺默认物流地址
        $express = new Express();
        $address = $express->getDefaultShopExpressAddress($this->instance_id);
        // 查询商家地址
        $shop_info = $order_service->getShopReturnSet($this->instance_id);
        $data = array(
            'refund_detail' => $detail,
            'refund_money' => sprintf("%.2f", $refund_money),
            'refund_balance' => sprintf("%.2f", $refund_balance),
            'shop_espress_address' => $address,
            'shop_address' => $shop_info
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 申请退款 售后
     */
    public function orderGoodsCustomerServiceAskfor()
    {
        $title = '申请退款 售后';
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
        $order_goods_id = request()->post('order_goods_id', 0);
        $refund_type = request()->post('refund_type', 1);
        $refund_require_money = request()->post('refund_require_money', 0);
        $refund_reason = request()->post('refund_reason', '');
        $order_service = new OrderService();
        $retval = $order_service->orderGoodsCustomerServiceAskfor($order_goods_id, $refund_type, $refund_require_money, $refund_reason);
        return $this->outMessage($title, $retval);
    }

    /**
     * 买家退货 售后
     */
    public function orderGoodsCustomerExpress()
    {
        $title = "买家退货 售后";
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
        $id = request()->post('id', 0);
        $order_goods_id = request()->post('order_goods_id', 0);
        $refund_express_company = request()->post('refund_express_company', '');
        $refund_shipping_no = request()->post('refund_shipping_no', 0);
        $order_service = new OrderService();
        $retval = $order_service->orderGoodsCustomerExpress($id, $order_goods_id, $refund_express_company, $refund_shipping_no);
        return $this->outMessage($title, $retval);
    }

    /**
     * 交易关闭
     */
    public function orderClose()
    {
        $title = "关闭订单";
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
        $order_service = new OrderService();
        $order_id = request()->post('order_id', '');
        if (empty($order_id)) {
            return $this->outMessage($title, - 50, '-50', "无法获取订单");
        }
        $res = $order_service->orderClose($order_id);
        return $this->outMessage($title, $res);
    }

    /**
     * 收货
     */
    public function orderTakeDelivery()
    {
        $title = "订单收货";
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
        $order_service = new OrderService();
        $order_id = request()->post('order_id', '');
        if (empty($order_id)) {
            return $this->outMessage($title, - 50, '-50', "无法获取订单");
        }
        $res = $order_service->OrderTakeDelivery($order_id);
        return $this->outMessage($title, $res);
    }

    /**
     * 删除订单
     */
    public function deleteOrder()
    {
        $title = "删除订单";
        if (empty($this->uid)) {
            return $this->outMessage($title, - 999, '-9999', "无法获取会员登录信息");
        }
        
        $order_service = new OrderService();
        $order_id = request()->post("order_id", "");
        if (empty($order_id)) {
            return $this->outMessage($title, - 50, '-50', "无法获取订单信息");
        }
        $res = $order_service->deleteOrder($order_id, 2, $this->uid);
        return $this->outMessage($title, $res);
    }

    /**
     * 订单评价
     */
    public function reviewCommodity()
    {
        $title = '订单评价';
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
        $order_id = request()->post('orderId', '');
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
            return $this->outMessage($title, - 50, '-50', "对不起,您无权进行此操作");
        }
        $order = new OrderOrderService();
        $list = $order->getOrderGoods($order_id);
        $orderDetail = $order->getDetail($order_id);
        $data['order_no'] = $orderDetail['order_no'];
        $data['list'] = $list;
        
        if (($orderDetail['order_status'] == 3 || $orderDetail['order_status'] == 4) && $orderDetail['is_evaluate'] == 0) {} else {
            return $this->outMessage($title, null, - 20);
        }
        
        return $this->outMessage($title, $data);
    }

    /**
     * 商品评价提交
     * 创建：李吉
     * 创建时间：2017-02-16 15:22:59
     */
    public function addGoodsEvaluate()
    {
        $title = "评价商品提交";
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
        $order = new OrderService();
        $order_id = request()->post('order_id', '');
        $order_no = request()->post('order_no', '');
        $order_id = intval($order_id);
        $order_no = intval($order_no);
        $goods = request()->post('goodsEvaluate', '');
        $goodsEvaluateArray = json_decode($goods);
        $member = new Member();
        $member_detail = $member->getMemberDetail($this->instance_id);
        
        if (! empty($member_detail['member_name'])) {
            $member_name = $member_detail['member_name'];
        } else {
            $member_name = '***';
        }
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
                
                'member_name' => $member_name,
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
        return $this->outMessage($title, $result);
    }

    /**
     * 追评
     * 李吉
     * 2017-02-17 14:12:15
     */
    public function reviewAgain()
    {
        $title = '追评';
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
        $order_id = request()->post('orderId', '');
        // 判断该订单是否是属于该用户的
        $order_service = new OrderService();
        $condition['order_id'] = $order_id;
        $condition['buyer_id'] = $this->uid;
        $condition['is_evaluate'] = 1;
        $order_count = $order_service->getUserOrderCountByCondition($condition);
        if ($order_count == 0) {
            return $this->outMessage($title, - 50, - 50, "对不起,您无权进行此操作");
        }
        
        $order = new OrderOrderService();
        $list = $order->getOrderGoods($order_id);
        $orderDetail = $order->getDetail($order_id);
        
        $data = array(
            'order_no' => $orderDetail['order_no'],
            'order_id' => $order_id,
            'list' => $list
        );
        if (($orderDetail['order_status'] == 3 || $orderDetail['order_status'] == 4) && $orderDetail['is_evaluate'] == 1) {
            return $this->outMessage($title, $data);
        } else {
            return $this->outMessage($title, null, - 20);
        }
    }

    /**
     * 商品-追加评价提交数据
     * 创建：李吉
     * 创建时间：2017-02-16 15:22:59
     */
    public function addGoodsEvaluateAgain()
    {
        $title = "追评商品提交";
        if (empty($this->uid)) {
            return $this->outMessage($title, - 9999, '-9999', "无法获取会员登录信息");
        }
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
        
        return $this->outMessage($title, $result);
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
     * 获取当前会员的砍价订单列表
     */
    public function myBargainOrderList()
    
    {
        $title = "获取会员砍价订单列表";
        if (empty($this->uid)) {
            return $this->outMessage($title, - 50, '-50', "无法获取会员登录信息");
        }
        // $bargain = new Bargain ();
        // $config = $bargain->getConfig ();
        $is_support = IS_SUPPORT_BARGAIN;
        if ($is_support == 0) {
            return $this->outMessage($title, - 50, '-50', "未开启砍价商品功能");
        }
        
        $status = request()->post('status', 'all');
        
        $condition['buyer_id'] = $this->uid;
        $condition['is_deleted'] = 0;
        $condition['order_type'] = 7;
        
        if ($this->instance_id != null) {
            $condition['shop_id'] = $this->instance_id;
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
        
        return $this->outMessage($title, $order_list);
    }

    /**
     * 订单中点击“去支付”的操作，获取外部交易号，用于后续支付
     * 创建时间：2018年5月21日15:39:08
     */
    public function orderPay()
    {
        $title = "订单中点击“去支付”的操作";
        $order_id = request()->post('order_id', 0);
        $out_trade_no = "";
        $order_service = new OrderService();
        if ($order_id != 0) {
            // 更新支付流水号
            $order_service->createNewOutTradeNoReturnBalance($order_id);
            $out_trade_no = $order_service->getOrderNewOutTradeNo($order_id);
            return $this->outMessage($title, $out_trade_no);
        } else {
            return $this->outMessage($title, $out_trade_no, '-50', "缺少参数order_id");
        }
    }

    /**
     * 订单中点击“查看物流”的操作
     * 创建时间：2018年5月21日16:29:38
     *
     * @return Ambigous <\think\response\Json, string>
     */
    public function checkLogistics()
    {
        $title = "订单中点击“查看物流”的操作";
        $order_id = request()->post('order_id', 0);
        if (! is_numeric($order_id)) {
            return $this->outMessage($title, null, '-50', "缺少参数order_id");
        }
        $order_service = new OrderService();
        $detail = $order_service->getOrderDetail($order_id);
        if (! empty($detail) && ! empty($detail['goods_packet_list'])) {
            
            $res['goods_packet_list'] = $detail['goods_packet_list'];
            foreach ($res['goods_packet_list'] as $k => $v) {
                if ($v['is_express'] == 1 && $v['express_id'] > 0) {
                    $res['goods_packet_list'][$k]['express_message'] = $order_service->getOrderGoodsExpressMessage($v['express_id']);
                } else {
                    $res['goods_packet_list'][$k]['express_message'] = null;
                }
            }
            return $this->outMessage($title, $res);
        }
    }

    /**
     * 处理自提地址的排序
     * 
     * @param unknown $address            
     * @param unknown $pickup_point_list            
     */
    public function pickupPointListSort($address, $pickup_point_list)
    {
        $arr = array();
        if (! empty($address) && ! empty($pickup_point_list)) {
            $district_arr = array();
            $city_arr = array();
            $province_arr = array();
            foreach ($pickup_point_list as $key => $pickup_point) {
                if ($pickup_point["district_id"] == $address["district"]) {
                    array_push($district_arr, $pickup_point_list[$key]);
                    unset($pickup_point_list[$key]);
                } elseif ($pickup_point["city_id"] == $address["city"]) {
                    array_push($city_arr, $pickup_point_list[$key]);
                    unset($pickup_point_list[$key]);
                } elseif ($pickup_point["province_id"] == $address["province"]) {
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
    public function memberPickup()
    {
        $order_id = request()->get('orderId', 0);
        $order_service = new OrderService();
        // 通过order_id判断该订单是否属于当前用户
        $condition['order_id'] = $order_id;
        $condition['buyer_id'] = $this->uid;
        $order_count = $order_service->getOrderCount($condition);
        if ($order_count == 0) {
            $this->error("没有获取到订单信息");
        }
        $res = $order_service->getOrderPickupInfo($order_id);
        if (! empty($res['picked_up_code'])) {
            $url = __URL(__URL__ . '/wap/order/orderPickupCodeToExamine?order_id=' . $order_id);
            $upload_path = "upload/qrcode/order_pickup_code_qrcode";
            if (! file_exists($upload_path)) {
                mkdir($upload_path, 0777, true);
            }
            $qrcode_name = 'orderPickupCode_' . $order_id;
            $path = $upload_path . '/' . $qrcode_name;
            getQRcode($url, $upload_path, $qrcode_name);
        }
        $this->assign("qrcode_path", $path . '.png');
        return view($this->style . 'Order/memberPickup');
    }

    /**
     * 自提订单门店审核
     */
    public function orderPickupCodeToExamine()
    {
        $order_id = request()->get('order_id', 0);
        $order_service = new OrderService();
        // 判断该用户是否已登录
        if (empty($this->uid)) {
            $_SESSION['login_pre_url'] = __URL(\think\Config::get('view_replace_str.APP_MAIN') . "/order/orderPickupCodeToExamine?order_id=" . $order_id);
            $this->redirect("Login/index");
        }
        $res = $order_service->getOrderPickupInfo($order_id);
        if (empty($res['picked_up_code'])) {
            $this->error("未获取到自提信息！");
        }
        // 判断当前用户是否是该门店的审核员
        $isPickedUpAuditor = $order_service->currUserIsPickedUpAuditor($res['picked_up_id'], $this->uid);
        if (! $isPickedUpAuditor) {
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
    public function pickedUpAuditorConfirmPickup()
    {
        if (request()->isAjax()) {
            $order_id = request()->post("order_id", 0);
            $auditor_id = request()->post("auditor_id", 0);
            $buyer_name = request()->post("buyer_name", '');
            $buyer_phone = request()->post("buyer_phone", '');
            
            $order_service = new OrderService();
            $res = $order_service->pickedUpAuditorConfirmPickup($order_id, $auditor_id, $buyer_name, $buyer_phone);
            return $res;
        }
    }
}