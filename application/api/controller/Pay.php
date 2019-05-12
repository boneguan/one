<?php
/**
 * Pay.php
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

use data\service\UnifyPay;
use data\service\Member;
use data\service\Order;
use data\service\Config;

/**
 * 支付控制器
 *
 * @author Administrator
 *        
 */
class Pay extends BaseController
{

    public $shop_config;
    
    public function __construct()
    {
        parent::__construct();
        $config = new Config();
        $this->shop_config = $config->getShopConfig(0);
    }

    /**
     * 获取支付相关信息
     */
    public function getPayValue()
    {
        $title = "获取支付信息";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $out_trade_no = request()->post('out_trade_no', '');
        if (empty($out_trade_no)) {
            return $this->outMessage($title, "", - 50, "缺少必填参数out_trade_no");
        }
        $pay = new UnifyPay();
        $member = new Member();
        $pay_value = $pay->getPayInfo($out_trade_no);
        
        if ($pay_value['pay_status'] != 0) {
            // 订单已经支付
            return $this->outMessage($title, '', - 50, '订单已经支付或者订单价格为0.00，无需再次支付!');
        }
        if ($pay_value['type'] == 1) {
            // 订单
            $order_status = $this->getOrderStatusByOutTradeNo($out_trade_no);
            // 订单关闭状态下是不能继续支付的
            if ($order_status == 5) {
                return $this->outMessage($title, '', - 50, '订单已关闭');
            }
        }
        $zero1 = time(); // 当前时间 ,注意H 是24小时 h是12小时
        $zero2 = $pay_value['create_time'];
        if ($zero1 > ($zero2 + ($this->shop_config['order_buy_close_time'] * 60))) {
            return $this->outMessage($title, '', - 50, '订单已关闭');
        } else {
            $member_info = $member->getMemberDetail();
            $data = array(
                'pay_value' => $pay_value,
                'nick_name' => $member_info['member_name']
            );
            return $this->outMessage($title, $data);
        }
    }

    /**
     * 预售定金待支付
     */
    public function orderPresellPay()
    {
        $title = '预售定金待支付';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $order_id = request()->post('id', 0);
        $order_service = new Order();
        
        $presell_order_info = $order_service->getOrderPresellInfo(0, [
            'relate_id' => $order_id
        ]);
        $presell_order_id = $presell_order_info['presell_order_id'];
        
        if ($presell_order_id != 0) {
            // 更新支付流水号
            $order_service -> createNewOutTradeNoReturnBalancePresellOrder($presell_order_id);
            $new_out_trade_no = $order_service->getPresellOrderNewOutTradeNo($presell_order_id);
            return $this->outMessage($title, $new_out_trade_no);
        } else {
            return $this->outMessage($title, '', - 1, '无法获取支付信息');
        }
    }

    /**
     * 根据外部交易号查询订单状态，订单关闭状态下是不能继续支付的
     * 创建时间：2017年10月13日 14:35:59 王永杰
     *
     * @param unknown $out_trade_no            
     * @return number
     */
    public function getOrderStatusByOutTradeNo($out_trade_no)
    {
        $title = "";
        $order = new Order();
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $order_status = $order->getOrderStatusByOutTradeNo($out_trade_no);
        if (! empty($order_status)) {
            return $order_status['order_status'];
        }
        return 0;
    }

    /**
     * 小程序支付
     */
    public function appletWechatPay()
    {
        $title = "订单支付!";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $out_trade_no = request()->post('out_trade_no', '');
        $openid = request()->post('openid', '');
        if (empty($out_trade_no)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数out_trade_no");
        }
        $red_url = str_replace("/index.php", "", __URL__);
        $red_url = str_replace("/api.php", "", __URL__);
        $red_url = str_replace("index.php", "", $red_url);
        $red_url = $red_url . "/weixinpay.php";
        $pay = new UnifyPay();
        $config = new Config();
        
        $res = $pay->wchatPay($out_trade_no, 'APPLET', $red_url, $openid);
        $wchat_config = $config->getWpayConfig($this->instance_id);
        
        if ($res["result_code"] == "SUCCESS" && $res["return_code"] == "SUCCESS") {
            $appid = $res["appid"];
            $nonceStr = $res["nonce_str"];
            $package = $res["prepay_id"];
            $signType = "MD5";
            $key = $wchat_config['value']['mch_key'];
            $timeStamp = time();
            $sign_string = "appId=$appid&nonceStr=$nonceStr&package=prepay_id=$package&signType=$signType&timeStamp=$timeStamp&key=$key";
            $paySign = strtoupper(md5($sign_string));
            $res["timestamp"] = $timeStamp;
            $res["PaySign"] = $paySign;
        }
        return $this->outMessage($title, $res);
    }

    /**
     * 根据流水号查询订单编号，
     * 创建时间：2017年10月9日 18:36:54
     *
     * @param unknown $out_trade_no            
     * @return string
     */
    public function getOrderNoByOutTradeNo()
    {
        $title = '查询订单号';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $out_trade_no = request()->post('out_trade_no', '');
        if (empty($out_trade_no)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数out_trade_no");
        }
        $order = new Order();
        $pay = new UnifyPay();
        $pay_value = $pay->getPayInfo($out_trade_no);
        $order_no = "";
        if ($pay_value['type'] == 1) {
            // 订单
            $list = $order->getOrderNoByOutTradeNo($out_trade_no);
            if (! empty($list)) {
                foreach ($list as $v) {
                    $order_no .= $v['order_no'];
                }
            }
        } elseif ($pay_value['type'] == 4) {
            // 余额充值不进行处理
        }
        return $this->outMessage($title, array(
            'order_no' => $order_no
        ));
    }

    public function onlinePaymentCallback()
    {
        $title = "在线支付回调";
        $out_trade_no = request()->post("out_trade_no", "");
        $pay_type = request()->post("pay_type", "");
        $trade_no = request()->post("trade_no", "");
        
        if (empty($out_trade_no)) {
            return $this->outMessage($title, - 50, '-50', "缺少必填参数out_trade_no");
        }
        if (empty($pay_type)) {
            return $this->outMessage($title, - 50, '-50', "缺少必填参数pay_type");
        }
        $pay = new UnifyPay();
        $res = $pay->onlinePay($out_trade_no, $pay_type, $trade_no);
        
        return $this->outMessage($title, $res);
    }

    /**
     * 获取支付方式配置信息
     * 创建时间：2018年6月20日10:33:26
     * 
     * @return Ambigous <\think\response\Json, string>
     */
    public function getPayConfig()
    {
        $title = "获取支付方式配置信息";
        $pay = new UnifyPay();
        $res = $pay_config = $pay->getPayConfig();
        if (! empty($res)) {
            return $this->outMessage($title, $res);
        } else {
            return $this->outMessage($title, null, "-9999", "未获取到数据");
        }
    }

    /**
     * 余额支付选择界面
     */
    public function pay()
    {
        $title = '订单支付！';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $member = new Member();
        $pay = new UnifyPay();
        $config = new Config();
        $uid = $member->getCurrUserId();
        
        $out_trade_no = request()->post("out_trade_no", 0);
        
        // 支付信息
        $pay_value = $pay->getPayInfo($out_trade_no);
        
        if (empty($out_trade_no) || ! is_numeric($out_trade_no) || empty($pay_value)) {
            return $this->outMessage($title, "", '-10', "没有获取到支付信息");
        }
        
        // 此次交易最大可用余额
        $member_balance = $pay->getMaxAvailableBalance($out_trade_no, $uid);
        $data["member_balance"] = $member_balance;
        
        $shop_id = 0;
        $shop_config = $config->getConfig($shop_id, "ORDER_BALANCE_PAY");
        
        // 支付方式配置
        $pay_config = $pay->getPayConfig();
        
        $order_status = $this->getOrderStatusByOutTradeNo($out_trade_no);
        // 订单关闭状态下是不能继续支付的
        if ($order_status == 5) {
            return $this->outMessage($title, "", '-10', "订单已关闭");
        }
        
        // 还需支付的金额
        $need_pay_money = round($pay_value['pay_money'], 2) - round($member_balance, 2);
        
        $zero1 = time(); // 当前时间 ,注意H 是24小时 h是12小时
        $zero2 = $pay_value['create_time'];
        $this->shop_config = $config->getShopConfig(0);
        if ($zero1 > ($zero2 + ($this->shop_config['order_buy_close_time'] * 60))) {
            return $this->outMessage($title, "", '-10', "订单已关闭");
        } else {
            $data["pay_value"] = $pay_value;
            $data["need_pay_money"] = sprintf("%.2f", $need_pay_money);
            $data["shop_config"] = $shop_config;
            $data["pay_config"] = $pay_config;
            
            return $this->outMessage($title, $data);
        }
    }

    /**
     * 订单绑定余额 （若存在余额支付）
     */
    public function orderBindBalance()
    {
        $title = '余额支付';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $member = new Member();
        $pay = new UnifyPay();
        $uid = $member->getCurrUserId();
        $out_trade_no = request()->post("out_trade_no", 0);
        $is_use_balance = request()->post("is_use_balance", 0);
        $res = $pay->orderPaymentUserBalance($out_trade_no, $is_use_balance, $uid);
        return $this->outMessage($title, $res);
    }
}