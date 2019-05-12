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
use data\service\Member;
use data\service\Member as MemberService;
use data\service\Order as OrderService;
use data\service\promotion\GoodsExpress as GoodsExpressService;
use data\service\promotion\GoodsMansong;
use data\service\Promotion;
use data\service\Shop;
use data\service\Pintuan as PintuanService;
use data\service\Order\OrderGoods;
use data\model\NsCartModel;
use data\model\NsGoodsModel;
use data\service\promotion\GoodsPreference;
use data\model\AlbumPictureModel;
use data\service\WebSite;
use data\service\Goods;
use data\service\User;
use app\api\controller\Order as OrderApi;

/**
 * 拼团订单控制器
 *
 * @author Administrator
 *        
 */
class Pintuan extends BaseController
{

    /**
     * 待付款订单
     */
    public function paymentOrder()
    {
        $title = '订单相关数据';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $order_goods_type = request()->post('order_goods_type', '');
        $order_sku_list = request()->post('order_sku_list', '');
        $order_tuangou_group_id = request()->post('order_tuangou_group_id', 0);
        // 判断实物类型：实物商品，虚拟商品
        if ($order_goods_type === "1") {
            $data = $this->orderInfo($order_sku_list, $order_tuangou_group_id);
            if ($data['code'] == - 50 || $data['code'] == - 10) {
                return $this->outMessage($title, '', $data['code'], $data['message']);
            }
            $data['order_tag'] = "pintuan";
            return $this->outMessage($title, $data);
        } else {
            return $this->outMessage($title, '', - 10, '错误的订单');
        }
    }

    /**
     * 待付款订单需要的数据
     * 2017年6月28日 15:24:48 王永杰
     */
    public function orderInfo($order_sku_list, $order_tuangou_group_id)
    {
        $member = new MemberService();
        $order = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config = new Config();
        $promotion = new Promotion();
        $shop_service = new Shop();
        $goods_preference = new GoodsPreference();
        $res = $this->buyNowSession($order_sku_list);
        if ($res['code'] == - 50 || $res['code'] == - 10) {
            return array(
                'code' => $res['code'],
                'message' => $res['message']
            );
        }
        $goods_sku_list = $res["goods_sku_list"];
        $list = $res["list"];
        $data['goods_sku_list'] = $goods_sku_list;
        
        $count_money = 0;
        $max_use_point = $goods_preference->getMaxUsePoint($goods_sku_list);  //最大可使用积分数
        foreach ($list as $k => $v) {
            $list[$k]['price'] = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
            $count_money += $list[$k]['subtotal'];
        }
        
        $data["count_money"] = $count_money; // 商品金额
        
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
            $data["address_is_have"] = 1;
            if (IS_SUPPORT_O2O == 1) {
                // 本地配送
                $o2o_distribution = $goods_express_service->getGoodsO2oPrice($count_money, 0, $address['province'], $address['city'], $address['district'], 0);
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
            $data["address_is_have"] = 0;
            $data["is_open_o2o_distribution"] = 0;
        }
        $count = $goods_express_service->getExpressCompanyCount($this->instance_id);
        $data["express_company_count"] = $count; // 物流公司数量
        $data["express"] = sprintf("%.2f", $express); // 运费
        $data["express_company_list"] = $express_company_list; // 物流公司
        
        $pick_up_money = $order->getPickupMoney($count_money);
        $data["pick_up_money"] = $pick_up_money;
        
        $data["itemlist"] = $list;
        
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
        
        $member_account = $member->getMemberAccount($this->uid, $this->instance_id);
        if ($member_account['balance'] == '' || $member_account['balance'] == 0) {
            $member_account['balance'] = '0.00';
        }
        $data["member_account"] = $member_account; // 用户余额
        
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
        $data["order_tuangou_group_id"] = $order_tuangou_group_id;
        $data["address_default"] = $address;
        
        $default_use_point = 0; // 默认使用积分数
        if ($member_account["point"] >= $max_use_point && $max_use_point != 0) {
            $default_use_point = $max_use_point;
        } else {
            $default_use_point = $member_account["point"];
        }
        // 积分配置
        $point_config = $promotion->getPointConfig();
        if ($max_use_point == 0 || $point_config['convert_rate'] == 0) {
            $point_config["is_open"] = 0;
        }
        $data["point_config"] = $point_config;
        $data["max_use_point"] = $max_use_point;
        $data["default_use_point"] = $default_use_point;
        
        return $data;
    }

    /**
     * 立即购买
     */
    public function buyNowSession($order_sku_list)
    {
        if (empty($order_sku_list)) {
            return array(
                'code' => - 50,
                'message' => '无法获取商品信息'
            ); // 没有商品返回到首页
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
                'message' => '无法获取商品信息'
            ); // 没有商品返回到首页
        }
        $pintuan = new PintuanService();
        $goods = new NsGoodsModel();
        $goods_info = $goods->getInfo([
            'goods_id' => $sku_info["goods_id"]
        ], 'max_buy,state,point_exchange_type,point_exchange,picture,goods_id,goods_name,max_use_point');
        
        $cart_list["stock"] = $sku_info['stock']; // 库存
        $cart_list["sku_id"] = $sku_info["sku_id"];
        $cart_list["sku_name"] = $sku_info["sku_name"];
        
        $goods_preference = new GoodsPreference();
        $member_price = $goods_preference->getGoodsSkuMemberPrice($sku_info['sku_id'], $this->uid);
        $goods_pintuan = $pintuan->getGoodsPintuanDetail($sku_info["goods_id"]);
        $cart_list["price"] = $goods_pintuan['tuangou_money'];
        
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
        
        if (count($cart_list) == 0) {
            return array(
                'code' => - 50,
                'message' => '无法获取商品信息'
            ); // 没有商品返回到首页
        }
        $list[] = $cart_list;
        $goods_sku_list = $sku_id . ":" . $num; // 商品skuid集合
        $res["list"] = $list;
        $res["goods_sku_list"] = $goods_sku_list;
        return $res;
    }

    /**
     * 创建拼团订单
     *
     * @return multitype:number string |Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function pintuanOrderCreate()
    {
        $title = '创建拼团订单';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $order = new OrderService();
        $member = new Member();
    
        $out_trade_no = $order->getOrderTradeNo(); // 订单支付编号
        $pay_type = request()->post("pay_type", 1); // 支付方式
    
        $order_from = 2; // 订单来源
        $buyer_message = request()->post('buyer_message', ''); // 买家留言
        $buyer_invoice = request()->post('buyer_invoice', ''); // 发票
    
        $address = $member->getDefaultExpressAddress(); // 收货人信息
        $receiver_mobile = $address["mobile"]; // 收货人手机号
        $receiver_province = $address["province"]; // 收货人地址
        $receiver_city = $address["city"]; // 收货人地址
        $receiver_district = $address["district"]; // 收货人地址
        $receiver_address = $address["address_info"].'&nbsp;'.$address['address']; // 收货人地址
        $receiver_zip = $address["zip_code"]; // 收货人邮编
        $receiver_name = $address["consigner"]; // 收货人姓名
    
        $point = request()->post('point', 0); // 积分
        $user_money = request()->post('account_balance', 0); // 用户余额
        $goods_sku_list = request()->post('goods_sku_list', ''); // 商品
        $platform_money = request()->post('platform_money', 0); // 优惠券
        $pick_up_id = request()->post('pick_up_id', 0); // 自提点id
        $shipping_company_id = request()->post('shipping_company_id', 0); // 物流公司id
        $coin = 0;
        $tuangou_group_id = request()->post('tuangou_group_id', 0); // 团购拼团id
    
        $shipping_type = request()->post("shipping_type", 1); // 配送方式，1：物流，2：自提 3:本地配送
        $shipping_time = date("Y-m-d H:i:s", time());
        $buyer_ip = request()->ip();
        
        $pintuan = new PintuanService();
        // 创建拼团
        if (! $tuangou_group_id > 0) {
            $tuangou_group_id = $pintuan->tuangouGroupCreate($this->uid, $receiver_mobile, $goods_sku_list);
        }
        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        if (! empty($purchase_restriction)) {
    
            return $this->outMessage($title, '', "-50", $purchase_restriction);
        } else {
            $order_id = $pintuan->pintuanOrderCreate(4, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, 0, $goods_sku_list, $user_money, $pick_up_id, $shipping_company_id, $coin, $tuangou_group_id);
            if ($order_id > 0) {
                $data['out_trade_no'] = $out_trade_no;
                return $this->outMessage($title, $data);
            } else {
                $data['order_id'] = $order_id;
                $order_api = new OrderApi;
                $message = $order_api->orderErrorMessage($order_id, "订单生成失败!");
                return $this->outMessage($title, $data, "-10", $message);
            }
        }
    }
    
    /**
     * 获取当前会员的订单列表
     */
    public function myOrderList()
    {
        $title = '我的订单列表';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $status = request()->post('status', 'all');
        
        $condition['buyer_id'] = $this->uid;
        $condition['is_deleted'] = 0;
        $condition['order_type'] = 4;
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
                case 4:
                    $condition['order_status'] = array(
                        'in',
                        [
                            - 1,
                            - 2
                        ]
                    );
                    break;
                case 6:
                    $condition['order_status'] = array(
                        'in',
                        '6'
                    );
                    break;
                default:
                    break;
            }
        }
        $page_index = request()->post("page", 1);
        // 还要考虑状态逻辑
        $pintuan = new PintuanService();
        $list = $pintuan->getOrderList($page_index, PAGESIZE, $condition, 'create_time desc');
        return $this->outMessage($title, $list);
    }

    /**
     * 拼单列表
     *
     * @return \think\response\Json
     */
    public function mySpellingOrderList()
    {
        $title = '拼单列表';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        
        $page_index = request()->post("page", 1);
        $condition = array();
        $condition["real_num"] = [
            "gt",
            0
        ];
        $condition["group_uid"] = $this->uid;
        // 还要考虑状态逻辑
        $pintuan = new PintuanService();
        $list = $pintuan->getPintuanOrderList($page_index, PAGESIZE, $condition, 'create_time desc');
        $list['current_time'] = time() * 1000;
        return $this->outMessage($title, $list);
    }

    /**
     * 订单详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function orderDetail()
    {
        $title = '订单详情';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $order_id = request()->post('order_id', 0);
        if (! is_numeric($order_id)) {
            return $this->outMessage($title, "", '-10', "没有获取到订单信息");
        }
        $order_service = new PintuanService();
        $detail = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            return $this->outMessage($title, "", '-10', "没有获取到订单信息");
        }
        // 通过order_id判断该订单是否属于当前用户
        $condition['order_id'] = $order_id;
        $condition['buyer_id'] = $this->uid;
        $condition['order_type'] = 4;
        
        $order_count = $order_service->getOrderCount($condition);
        if ($order_count == 0) {
            return $this->outMessage($title, "", '-10', "没有获取到订单信息");
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
            $detail['express_name'] = $express_name;
            $detail['express_code'] = $express_code;
        }
        $detail['express_count'] = $express_count;
        $detail['is_show_express_code'] = $count; // 是否显示运单号（无需物流不显示）
                                                  
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
        $detail["list"] = $list;
        $detail['current_time'] = time() * 1000;
        
        return $this->outMessage($title, $detail);
    }

    /**
     * 拼团分享界面
     * 创建时间：2017年12月28日14:10:47
     * (non-PHPdoc)
     *
     * @see \app\wap\controller\Order::spellGroupShare()
     */
    public function spellGroupShare()
    {
        $title = '拼团分享界面';
        $shop_id = $this->shop_id;
        $goods_id = request()->post("goods_id", "");
        $group_id = request()->post("group_id", "");
        
        if (empty($goods_id) || empty($group_id) || $goods_id == 'undefined' || $group_id == 'undefined') {
            return $this->outMessage($title, "", '-10', "无法获取拼团信息");
        }
        if (strstr($this->share_icon, "http")) {
            $share_logo = $this->share_icon;
        } else {
            $share_logo = 'https://' . $_SERVER['HTTP_HOST'] . config('view_replace_str.') . '/' . $this->share_icon; // 分享时，用到的logo，默认是平台分享图标
        }
        $shop = new Shop();
        $config = $shop->getShopShareConfig($shop_id);
        
        // 当前用户名称
        $current_user = "";
        $user_info = null;
        
        if (! empty($this->uid)) {
            $user = new User();
            $user_info = $user->getUserInfoByUid($this->uid);
            $current_user = "分享人：" . $user_info["nick_name"];
        }
        $pintuan = new PintuanService();
        $goods_pintuan = $pintuan->getGoodsPintuanDetail($goods_id);
        // 商品分享
        $goods = new Goods();
        $goods_detail = $goods->getGoodsDetail($goods_id);
        $share_content["share_title"] = $goods_detail["goods_name"];
        $share_content["share_contents"] = $config["goods_param_1"] . "￥" . $goods_pintuan["tuangou_money"] . ";" . $config["goods_param_2"];
        $share_content['share_nick_name'] = $current_user;
        if (count($goods_detail["img_list"]) > 0) {
            if (strstr($goods_detail["img_list"][0]["pic_cover_mid"], "http")) {
                $share_logo = $goods_detail["img_list"][0]["pic_cover_mid"];
            } else {
                $share_logo = 'https://' . $_SERVER['HTTP_HOST'] . config('view_replace_str.__UPLOAD__') . '/' . $goods_detail["img_list"][0]["pic_cover_mid"]; // 用商品的第一个图片
            }
        }
        $share_logo = str_replace('api.php/', '', $share_logo);
        $pintuan_detail = $pintuan->getGroupDetailByGroupId($group_id);
        $data['share_content'] = $share_content;
        $data["tuangou_group_info"] = $pintuan_detail;
        $data['current_time'] = time() * 1000;
        $data['share_logo'] = $share_logo;
        
        return $this->outMessage($title, $data);
    }
}