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
namespace app\shop\controller;

use data\service\Express;
use data\service\Member;
use data\service\Order as OrderService;
use data\service\promotion\PromoteRewardRule;
use data\service\Config as Config;
use data\service\GroupBuy;

/**
 * 订单控制器
 * 创建人：李吉
 * 创建时间：2017-02-06 10:59:23
 */
class Order extends BaseController
{

    public function __construct()
    {
        parent::__construct();
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
        $shipping_type = request()->post("shipping_type",1); // 配送方式，1：商家配送，2：自提  3：本地配送
        $shipping_time = request()->post("shipping_time", 0); // 配送时间
        $express_company_id = request()->post("express_company_id", 0); // 物流公司
        $buyer_ip = request()->ip();
        $distribution_time_out = request()->post('distribution_time_out', ''); // 配送指定时间段
        
        $member = new Member();
        $address = $member->getDefaultExpressAddress();
        $coin = 0; // 购物币
        
        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        if (! empty($purchase_restriction)) {
            $res = array(
                "code" => 0,
                "message" => $purchase_restriction
            );
            return $res;
        } else {
            $order_id = $order->orderCreate('1', $out_trade_no, $pay_type, $shipping_type, '1', $buyer_ip, $leavemessage, $buyer_invoice, $shipping_time, $address['mobile'], $address['province'], $address['city'], $address['district'], $address["address_info"].'&nbsp;'.$address['address'], $address['zip_code'], $address['consigner'], $integral, $use_coupon, 0, $goods_sku_list, $user_money, $pick_up_id, $express_company_id, $coin, $address["phone"], $distribution_time_out);
            // Log::write($order_id);
            if ($order_id > 0) {
                $order->deleteCart($goods_sku_list, $this->uid);
                $_SESSION['order_tag'] = ""; // 订单创建成功会把购物车中的标记清楚
                return AjaxReturn($out_trade_no);
            } else {
                return AjaxReturn($order_id);
            }
        }
    }
    
    /**
     * 预售订单创建
     */
    public function presellOrderCreate(){
        
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
        $shipping_type = request()->post("shipping_type",1); // 配送方式，1：商家配送，2：自提  3：本地配送
        $shipping_time = request()->post("shipping_time", 0); // 配送时间
        $express_company_id = request()->post("express_company_id", 0); // 物流公司
        $is_full_payment = request()->post('is_full_payment', 0);
        $member = new Member();
        $address = $member->getDefaultExpressAddress();
        $coin = 0; // 购物币
        $buyer_ip = request()->ip();
        $distribution_time_out = request()->post('distribution_time_out', ''); // 配送指定时间段
        
        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        if (! empty($purchase_restriction)) {
            $res = array(
                "code" => 0,
                "message" => $purchase_restriction
            );
            return $res;
        } else {
            
            //预售订单添加
            $order_id = $order->orderCreatePresell(6, $out_trade_no, $pay_type, $shipping_type, '1', $buyer_ip, $leavemessage, $buyer_invoice, $shipping_time, $address['mobile'], $address['province'], $address['city'], $address['district'], $address["address_info"].'&nbsp;'.$address['address'], $address['zip_code'], $address['consigner'], $integral, $use_coupon, 0, $goods_sku_list, $user_money, $pick_up_id, $express_company_id, $coin, $address["phone"], $is_full_payment, $distribution_time_out);
            // Log::write($order_id);
            if ($order_id > 0) {
                return AjaxReturn($out_trade_no);
            } else {
                return AjaxReturn($order_id);
            }
        }
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
        $shipping_type = request()->post("shipping_type",1); // 配送方式，1：商家配送，2：自提  3：本地配送
        $shipping_time = request()->post("shipping_time", 0); // 配送时间
        $express_company_id = request()->post("express_company_id", 0); // 物流公司
    
        $member = new Member();
        $address = $member->getDefaultExpressAddress(); // 收货人信息
        $receiver_mobile = $address["mobile"]; // 收货人手机号
        $receiver_province = $address["province"]; // 收货人地址
        $receiver_city = $address["city"]; // 收货人地址
        $receiver_district = $address["district"]; // 收货人地址
        $receiver_address = $address["address_info"].'&nbsp;'.$address['address']; // 收货人地址
        $receiver_zip = $address["zip_code"]; // 收货人邮编
        $receiver_name = $address["consigner"]; // 收货人姓名
        $coin = 0; // 购物币
        $buyer_ip = request()->ip();
        $distribution_time_out = request()->post('distribution_time_out', ''); // 配送指定时间段
         
        // 查询商品限购
        $order_id = $group_buy_service->groupBuyOrderCreate(1, $out_trade_no, $pay_type, $shipping_type, 1, $buyer_ip, $leavemessage, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $integral, 0, $goods_sku_list, $user_money, $pick_up_id, $express_company_id, $coin, $distribution_time_out);

        if ($order_id > 0) {
            return AjaxReturn($out_trade_no);
        } else {
            return AjaxReturn($order_id);
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
        $pick_up_id = 0; // 自提点
        $shipping_type = 1; // 配送方式，1：物流，2：自提
        $express_company_id = 0; // 物流公司
        $member = new Member();
        $shipping_time = date("Y-m-d H::i:s", time());
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
            // Log::write($order_id);
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
    public function createComboPackageOrder()
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
        $express_company_id = request()->post("express_company_id", 0); // 物流公司
        $combo_package_id = request()->post("combo_package_id", 0); // 组合套餐id
        $buy_num = request()->post("buy_num", 1); // 购买套数
        
        $member = new Member();
        $address = $member->getDefaultExpressAddress();
        $coin = 0; // 购物币
        $buyer_ip = request()->ip();
        $distribution_time_out = request()->post('distribution_time_out', ''); // 配送指定时间段
        
        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        if (! empty($purchase_restriction)) {
            $res = array(
                "code" => 0,
                "message" => $purchase_restriction
            );
            return $res;
        } else {
            $order_id = $order->orderCreateComboPackage("3", $out_trade_no, $pay_type, $shipping_type, "1", $buyer_ip, $leavemessage, $buyer_invoice, $shipping_time, $address['mobile'], $address['province'], $address['city'], $address['district'], $address["address_info"].'&nbsp;'.$address['address'], $address['zip_code'], $address['consigner'], $integral, 0, $goods_sku_list, $user_money, $pick_up_id, $express_company_id, $coin, $address["phone"], $combo_package_id, $buy_num, $purchase_restriction);
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
    public function pointExchangeOrderCreate(){
        $order = new OrderService();
        // 获取支付编号
        $out_trade_no = $order->getOrderTradeNo();
        $use_coupon = request()->post('use_coupon', 0); // 优惠券
        $integral = request()->post('integral', 0); // 积分
        $goods_sku_list = request()->post('goods_sku_list', ''); // 商品列表
        $leavemessage = request()->post('leavemessage', ''); // 留言
        $user_money = request()->post("account_balance", 0); // 使用余额
        $pay_type = 11; //request()->post("pay_type", 1); // 支付方式 积分兑换
        $buyer_invoice = request()->post("buyer_invoice", ""); // 发票
        $pick_up_id = request()->post("pick_up_id", 0); // 自提点
        $shipping_type = request()->post("shipping_type", 1); // 配送方式，1：物流，2：自提 3：本地配送
        $shipping_time = request()->post("shipping_time", 0); // 配送时间
        $express_company_id = request()->post("express_company_id", 0); // 物流公司
        $combo_package_id = request()->post("combo_package_id", 0); // 组合套餐id
        $buy_num = request()->post("buy_num", 1); // 购买套数
        $point_exchange_type = request()->post("point_exchange_type", 1);
        $order_goods_type = request()->post("order_goods_type", 1); //商品类型 0虚拟商品 1实物商品
        $user_telephone = request()->post("user_telephone", ""); //虚拟商品手机号
        $order_type = $order_goods_type == 1 ? 1 : 2; //订单类型 1实物订单 2虚拟订单
        
        $member = new Member();
        $address = $member->getDefaultExpressAddress();
        $coin = 0; // 购物币
        $buyer_ip = request()->ip();
        $distribution_time_out = request()->post('distribution_time_out', ''); // 配送指定时间段
        
        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        if (! empty($purchase_restriction)) {
            $res = array(
                "code" => 0,
                "message" => $purchase_restriction
            );
            return $res;
        } else {
            $order_id = $order->orderCreatePointExhange($order_type, $out_trade_no, $pay_type, $shipping_type, "1", $buyer_ip, $leavemessage, $buyer_invoice, $shipping_time, $address['mobile'], $address['province'], $address['city'], $address['district'], $address["address_info"].'&nbsp;'.$address['address'], $address['zip_code'], $address['consigner'], $integral, $use_coupon, 0, $goods_sku_list, $user_money, $pick_up_id, $express_company_id, $coin, $address['phone'], $point_exchange_type, $order_goods_type, $user_telephone, $distribution_time_out);
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
            $status = request()->post("status","all");
            $condition['buyer_id'] = $this->uid;
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
                        $condition['order_status'] = 3;
                        break;
                    case 4:
                        $condition['order_status'] = array(
                            'in',
                            [
                                - 1,
                                - 2,
                                4
                            ]
                        );
                        break;
                    default:
                        break;
                }
            }
            // 还要考虑状态逻辑
            
            $order = new OrderService();
            $order_list = $order->getOrderList(1, 0, $condition, 'create_time desc');
            return $order_list['data'];
        } else {
            $this->assign("status", $status);
            return view($this->style . 'Order/myOrderList');
        }
    }

    /**
     * 订单详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function orderDetail()
    {
        $order_id = request()->get('orderId', 0);
        if ($order_id == 0) {
            $this->error("没有获取到订单信息");
        }
        $order_service = new OrderService();
        $detail = $order_service->getOrderDetail($order_id);
        $this->assign("order", $detail);
        return view($this->style . 'Order/orderDetail');
    }

    /**
     * 订单项退款详情
     */
    public function refundDetail()
    {
        $order_goods_id = request()->get('order_goods_id', 0);
        if ($order_goods_id == 0) {
            $this->error("没有获取到退款信息");
        }
        $order_service = new OrderService();
        $detail = $order_service->getOrderGoodsRefundInfo($order_goods_id);
        $this->assign("order_refund", $detail);
        $refund_money = $order_service->orderGoodsRefundMoney($order_goods_id);
        $this->assign('refund_money', $refund_money);
        $this->assign("detail", $detail);
        // 查询店铺默认物流地址
        $express = new Express();
        $address = $express->getDefaultShopExpressAddress($this->instance_id);
        $this->assign("address_info", $address);
        return view($this->style . 'Order/refundDetail');
    }

    /**
     * 申请退款
     */
    public function orderGoodsRefundAskfor()
    {
        $order_id = request()->post("order_id", 0);
        $order_goods_id = request()->post("order_goods_id", 0);
        $refund_type = request()->post("refund_type", 1);
        $refund_require_money = request()->post("refund_require_money", 0);
        $refund_reason = request()->post("refund_reason", "");
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
        $order_id = request()->post("order_id",0);
        $order_goods_id = request()->post("order_goods_id",0);
        $refund_express_company = request()->post("refund_express_company","");
        $refund_shipping_no = request()->post("refund_shipping_no",0);
        $refund_reason = request()->post("refund_reason","");
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
        $order_id = request()->post("order_id","");
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
     * 订单后期预定金支付页面
     */
    public function orderPresellPay()
    {
        $order_id = request()->get('id', 0);
        $order_service = new OrderService();
        
        $presell_order_info = $order_service->getOrderPresellInfo(0, ['relate_id'=>$order_id]);
        $presell_order_id = $presell_order_info['presell_order_id'];
   
        if ( $presell_order_id!= 0) {
            // 更新支付流水号
            $order_service -> createNewOutTradeNoReturnBalancePresellOrder($presell_order_id);
            $new_out_trade_no = $order_service->getPresellOrderNewOutTradeNo($presell_order_id);
            $url = __URL(__URL__ . '/wap/pay/pay?out_trade_no=' . $new_out_trade_no);
            header("Location: " . $url);
            exit();
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
     * 商品评价
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
     * 商品-追加评价
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
     * 申请  售后
     */
    public function orderGoodsCustomerServiceAskfor()
    {
        $order_goods_id = request()->post("order_goods_id", 0);
        $refund_type = request()->post("refund_type", 1);
        $refund_money = request()->post("refund_money", 0);
        $refund_reason = request()->post("refund_reason", "");
        $order_service = new OrderService();
        $retval = $order_service->orderGoodsCustomerServiceAskfor($order_goods_id, $refund_type, $refund_money, $refund_reason);
        return AjaxReturn($retval);
    }
    
    /**
     * 买家退货  售后
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function orderGoodsCustomerExpress()
    {
        $id = request()->post("id",0);
        $order_goods_id = request()->post("order_goods_id",0);
        $refund_express_company = request()->post("refund_express_company","");
        $refund_shipping_no = request()->post("refund_shipping_no",0);

        $order_service = new OrderService();
        $retval = $order_service->orderGoodsCustomerExpress($id, $order_goods_id, $refund_express_company, $refund_shipping_no);
        return AjaxReturn($retval);
    }
    
    /**
     * 买家提货
     */
    public function memberPickup(){
        if(request()->isAjax()){
            $order_id = request()->post("order_id",0);
            $order = new OrderService();
            $res = $order -> getOrderPickupInfo($order_id);
            if(!empty($res['picked_up_code'])){
                $url = __URL(__URL__ . '/wap/order/orderPickupCodeToExamine?order_id=' . $order_id );
                $upload_path = "upload/qrcode/order_pickup_code_qrcode";
                if (! file_exists($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }
                $qrcode_name = 'orderPickupCode_'.$order_id;
                $path = $upload_path .'/'.$qrcode_name;
                getQRcode($url, $upload_path, $qrcode_name);
                return $result = array(
                    "code" => 1,
                    "path" => $path.'.png'  
                );
            }else{
                return $result = array(
                    "code" => 0,
                    "message" => "未获取到自提信息"
                );
            }
        }
    }
}