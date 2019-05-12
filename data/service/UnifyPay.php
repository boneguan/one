<?php
/**
 * UnifyPay.php
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

/**
 * 统一支付接口服务层
 */
use data\service\BaseService as BaseService;
use data\api\IUnifyPay;
use data\model\NsOrderPaymentModel;
use data\model\NsMemberBalanceWithdrawModel;
use data\model\UserModel;
use data\service\Pay\WeiXinPay;
use data\service\Pay\AliPay;
use data\service\Config;
use app\wap\controller\Assistant;
use data\service\niubusiness\NbsBusinessAssistant;
use think\Log;
use think\Cache;
use data\service\Pay\PayParam;
use data\service\Pay\UnionPay;
use data\service\Member\MemberAccount;
use data\model\NsOrderModel;
use data\model\NsOrderPresellModel;
use data\service\Pay\AliPayVerify;
use Qiniu\json_decode;

class UnifyPay extends BaseService implements IUnifyPay
{

    function __construct()
    {
        parent::__construct();
    }
    /**
     * 支付状态设计：0待支付，1已支付  -1已关闭
     * @see \data\api\IUnifyPay::createOutTradeNo()
     */
     public function createOutTradeNo()
    {
        $cache = Cache::get("niubfd".time());
        if(empty($cache))
        {
            Cache::set("niubfd".time(), 1000);
            $cache = Cache::get("niubfd".time());
        }else{
            $cache = $cache+1;
            Cache::set("niubfd".time(), $cache);
        }
        $no = time().rand(1000,9999).$cache;
        return $no;
    }
    /**
     * 获取支付配置(non-PHPdoc)
     * @see \data\api\IUnifyPay::getPayConfig()
     */
    public function getPayConfig()
    {
       
        $instance_id = 0;
        $config = new Config();
        $wchat_pay = $config->getWpayConfig($instance_id);       
        $ali_pay = $config->getAlipayStatus($instance_id);
        $union_pay = $config ->getUnionpayConfig($instance_id);
        $data_config = array(
            'wchat_pay_config' => $wchat_pay,
            'ali_pay_config'   => $ali_pay,
            'union_pay_config' => $union_pay
        );
        return $data_config;
    }
    /**
     * 创建待支付单据
     * @param unknown $pay_no
     * @param unknown $pay_body
     * @param unknown $pay_detail
     * @param unknown $pay_money
     * @param unknown $type  订单类型  1. 商城订单  2.
     * @param unknown $pay_money
     */
    public function createPayment($shop_id, $out_trade_no, $pay_body, $pay_detail, $pay_money, $type, $type_alis_id)
    {
        $pay = new NsOrderPaymentModel();
        $data = array(
            'shop_id'       => $shop_id,
            'out_trade_no'  => $out_trade_no,
            'type'          => $type,
            'type_alis_id'  => $type_alis_id,
            'pay_body'      => $pay_body,
            'pay_detail'    => $pay_detail,
            'pay_money'     => $pay_money,
            'create_time'   => time(),
            'original_money' => $pay_money
        );
        if($pay_money <= 0)
        {
            $data['pay_status'] = 1;
        }
        $res = $pay->save($data);
        return $res;
    }

    /**
     * (non-PHPdoc)
     * @see \data\api\IUnifyPay::updatePayment()
     */
    public function updatePayment($out_trade_no,$shop_id, $pay_body, $pay_detail, $pay_money, $type, $type_alis_id)
    {
        $pay = new NsOrderPaymentModel();
        $data = array(
            'shop_id'       => $shop_id,
            'type'          => $type,
            'type_alis_id'  => $type_alis_id,
            'pay_body'      => $pay_body,
            'pay_detail'    => $pay_detail,
            'pay_money'     => $pay_money,
            'modify_time'   => time()
        );
        if($pay_money <= 0)
        {
            $data['pay_status'] = 1;
        }
        $res = $pay->save($data,['out_trade_no'=>$out_trade_no]);
        return $res;
    }
    
    /**
     * (non-PHPdoc)
     * @see \data\api\IUnifyPay::delPayment()
     */
    public function delPayment($out_trade_no){
        $pay = new NsOrderPaymentModel();
        $res = $pay->where('out_trade_no',$out_trade_no)->delete();
        return $res;
    }
    
    /**
     * 线上支付主动根据支付方式执行支付成功的通知
     * @param unknown $out_trade_no
     */
    public function onlinePay($out_trade_no, $pay_type, $trade_no)
    {
        $pay = new NsOrderPaymentModel();
        $ns_order = new NsOrderModel();
        try{
            $pay_info = $pay->getInfo(['out_trade_no' => $out_trade_no]);
            if($pay_info['pay_status'] == 1)
            {
                return 1;
                exit();
            }
            $data = array(
                'pay_status'     => 1,
                'pay_type'       => $pay_type,
                'pay_time'       => time(),
                'trade_no'      => $trade_no
            );
            $retval = $pay->save($data, ['out_trade_no' => $out_trade_no]);
            
            if($pay_info['balance_money'] > 0){
                $ns_order -> save([
                    "user_platform_money" => $pay_info['balance_money'],
                    "pay_money" => $pay_info['pay_money']
                ], ['out_trade_no' => $out_trade_no]);
            }
            
            $pay_info = $pay->getInfo(['out_trade_no' => $out_trade_no], 'type');
            switch ( $pay_info['type']){
                case 1:
                    $order = new Order();
                    $order -> onLinePaymentUpdateBalance($out_trade_no);
                    $order->orderOnLinePay($out_trade_no, $pay_type);
                    break;
                case 2:
                    $assistant = new NbsBusinessAssistant();
                    $assistant->payOnlineBusinessAssistantApply($out_trade_no);
                    break;
                case 4:
                    //充值
                    $member = new Member();
                    $member->payMemberRecharge($out_trade_no, $pay_type);
                    //账户余额充值调用钩子                   
                    break;
                case 5: //预售订单支付
                    $order = new Order();
                    $order -> onLinePresellOrderUpdateBalance($out_trade_no);
                    $order->presellOrderOnLinePay($out_trade_no, $pay_type);
                    break;
                default:
                    break;
            }
            return 1;
        }catch(\Exception $e)
        {
            Log::write("weixin-------------------------------".$e->getMessage());
            return $e->getMessage();
        }
    
    }
    /**
     * 只是执行单据支付，不进行任何处理用于执行支付后被动调用
     * @param unknown $out_trade_no
     * @param unknown $pay_type
     */
    public function offLinePay($out_trade_no, $pay_type)
    {
        $pay = new NsOrderPaymentModel();
        $data = array(
            'pay_status'     => 1,
            'pay_type'       => $pay_type,
            'pay_time'   => time()
        );
        $retval = $pay->save($data, ['out_trade_no' => $out_trade_no]);
        return $retval;
    }
    /**
     * 获取支付信息
     * @param unknown $out_trade_no
     */
    public function getPayInfo($out_trade_no)
    {
        $pay = new NsOrderPaymentModel();
        $info = $pay->getInfo(['out_trade_no' => $out_trade_no], '*');
        return $info;
    }
    /**
     * 重新设置编号，用于修改价格订单
     * @param unknown $out_trade_no
     * @param unknown $new_no
     * @return Ambigous <number, \think\false, boolean, string>
     */
    public function modifyNo($out_trade_no, $new_no)
    {
        $pay = new NsOrderPaymentModel();
        $this->closePaymentPartyInterface($out_trade_no);
        $data = array(
            "out_trade_no" => $new_no
        );
        $retval = $pay->where(['out_trade_no' => $out_trade_no])->update($data);
        return $retval;
    }
    /**
     * 关闭订单(数据库操作)
     * @param unknown $out_trade_no
     * @return unknown
     */
    public function closePayment($out_trade_no)
    {
        $pay = new NsOrderPaymentModel();
        $data = array(
            'pay_status' => -1
        );
        $retval = $pay->save($data,['out_trade_no' => $out_trade_no]);
        return $retval;
    }
    /**
     * 关闭第三方接口
     * @param unknown $out_trade_no
     */
    public function closePaymentPartyInterface($out_trade_no)
    {
        //检测已开通接口
        $data = $this->getPayInfo($out_trade_no);
        if(!empty($data))
        {
            if($data['pay_type'] == 1)
            {
                //微信支付
                $weixin_pay = new WeiXinPay();
                $weixin_pay->setOrderClose($out_trade_no);
            
            }elseif ($data['pay_type'] == 2)
            {
                //支付宝支付
               // $ali_pay = new AliPay();
                $alipayverify = new AliPayVerify();
                $ali_pay = $alipayverify->aliPayClass();
                $ali_pay->setOrderClose($out_trade_no);
            
            }elseif($data['pay_type'] == 3)
            {
                //银联卡支付
            }
        }
      
    }
    /**
     * 修改支付价格
     * @param unknown $out_trade_no
     */
    public function modifyPayMoney($out_trade_no, $pay_money)
    {
        $pay = new NsOrderPaymentModel();
        $data = array(
            'pay_money' => $pay_money,
            'original_money' => $pay_money
        );
        $retval = $pay->save($data, ['out_trade_no' => $out_trade_no]);
    }
	/* (non-PHPdoc)
     * @see \data\api\IUnifyPay::wchatPay()
     */
    public function wchatPay($out_trade_no, $trade_type, $red_url, $applet_openid="")
    {
        $data = $this->getPayInfo($out_trade_no);
        //修改支付信息
        $pay = new NsOrderPaymentModel();
        $pay->save(['pay_type' => 1], ['out_trade_no' => $out_trade_no]);
        if($data < 0)
        {
            return $data;
        }
        $weixin_pay = new WeiXinPay();
        if($trade_type == 'JSAPI')
        {
            $openid = $weixin_pay->get_openid();
            $product_id = '';
        }
        if($trade_type == 'NATIVE')
        {
            $openid = '';
            $product_id = $out_trade_no;
        }
        if($trade_type == 'MWEB')
        {
            $openid = '';
            $product_id = $out_trade_no;
        }
        if($trade_type== 'APPLET'){
            $openid = $applet_openid;
        }
        
        $retval = $weixin_pay->setWeiXinPay($data['pay_body'], $data['pay_detail'], $data['pay_money']*100, $out_trade_no, $red_url, $trade_type, $openid, $product_id);
        return $retval;
        
        // TODO Auto-generated method stub
        
    }

	/* (non-PHPdoc)
     * @see \data\api\IUnifyPay::aliPay()
     */
    public function aliPay($out_trade_no, $notify_url, $return_url, $show_url)
    {
        $data = $this->getPayInfo($out_trade_no);
        if($data < 0)
        {
            return $data;
        }
        $pay = new NsOrderPaymentModel(); 
        $pay->save(['pay_type' => 2], ['out_trade_no' => $out_trade_no]);
        //$ali_pay = new AliPay();
        $aliPayVerify = new AliPayVerify();
        $ali_pay = $aliPayVerify->aliPayClass();
        $retval = $ali_pay->setAliPay($out_trade_no, $data['pay_body'], $data['pay_detail'], $data['pay_money'], 3, $notify_url, $return_url, $show_url);
        return $retval;
        // TODO Auto-generated method stub
        
    }
    /**
     * (non-PHPdoc)
     * @see \data\api\IUnifyPay::getWxJsApi()
     */
    public function getWxJsApi($UnifiedOrderResult)
    {
        $weixin_pay = new WeiXinPay();
        $retval = $weixin_pay->GetJsApiParameters($UnifiedOrderResult);
        return $retval;
    }
    /**
     * (non-PHPdoc)
     * @see \data\api\IOrder::getVerifyResult()
     */
    public function getVerifyResult($params, $type){       
        $alipayverfiy = new AliPayVerify();
        $pay = $alipayverfiy->aliPayClass();
        
        $verify = $pay->getVerifyResult($params, $type);
        return $verify;
    }
    /**
     * 微信支付检测签名串
     * @param unknown $post_obj
     * @param unknown $sign
     */
    public function checkSign($post_obj, $sign)
    {
        $weixin_pay = new WeiXinPay();
        $retval = $weixin_pay->checkSign($post_obj, $sign);
        return $retval;
    }
    /**
     * 微信退款
     * @param unknown $refund_no
     * @param unknown $out_trade_no
     * @param unknown $refund_fee
     * @param unknown $total_fee
     */
    public function setWeiXinRefund($refund_no, $out_trade_no, $refund_fee, $total_fee)
    {
        $weixin_pay = new WeiXinPay();
        $retval = $weixin_pay->setWeiXinRefund($refund_no, $out_trade_no, $refund_fee, $total_fee);
        return $retval;
    }
    /**
     * 支付宝原路退款
     * @param unknown $refund_no
     * @param unknown $out_trade_no商户订单号不是支付流水号
     * @param unknown $refund_fee
     */
    public function aliPayRefund($refund_no, $out_trade_no, $refund_fee)
    {
        //$pay = new AliPay();
        $alipayverify = new AliPayVerify();
        $pay = $alipayverify->aliPayClass();
        $retval = $pay->aliPayRefund($refund_no, $out_trade_no, $refund_fee);
        return $retval;
    }
    
    public function enterprisePayment(){
        $weixin_pay = new WeiXinPay();
        $openid = 'oPgfq0rrpqNxgT9MHF1YRttD5oyI';
        $partner_trade_no = '201801041030004';
        $amount = '500';
        $re_user_name = '高伟';
        $desc = '转账到个人零钱';
        $retval = $weixin_pay->EnterprisePayment($openid, $partner_trade_no, $amount, $re_user_name, $desc);
        return $retval;
    }
    /**
     * 获取提现转账所需要的信息
     * @param unknown $withdraw_id
     */
    public function getMemberWithdrawDetail($withdraw_id){
        $member_balance_withdraw = new NsMemberBalanceWithdrawModel();
        $retval = $member_balance_withdraw->getInfo([
            'id' => $withdraw_id
        ], '*');
        if (! empty($retval)) {
            $user = new UserModel();
            $userinfo = $user->getInfo([
                'uid' => $retval['uid']
            ]);
            $retval['openid'] = $userinfo["wx_openid"];
        }
        return $retval;
    }
    /**
     * 提现 微信转账
     * @param unknown $openid
     * @param unknown $partner_trade_no
     * @param unknown $amount
     * @param unknown $realname
     * @param unknown $desc
     */
    public function wechatTransfers($openid, $partner_trade_no, $amount, $realname, $desc){
        $weixin_pay = new WeiXinPay();
        $retval = $weixin_pay->EnterprisePayment($openid, $partner_trade_no, $amount, $realname, $desc);
        return $retval;
    }
    /**
     * 支付宝转账
     * @param unknown $out_biz_no
     * @param unknown $ali_account
     * @param unknown $money
     * @return \data\extend\alipay\提交表单HTML文本
     */
    public function aliayTransfers($out_biz_no, $ali_account, $money){
        //$aliay_pay=new AliPay();
        $alipayverify = new AliPayVerify();
        $aliay_pay = $alipayverify->aliPayClass();
        $result=$aliay_pay->aliPayTransfer($out_biz_no, $ali_account, $money);
        return $result["response"]["alipay"];
    }
    
 /**
     * 银联交易成功
     */
    public function backReceive($orderId, $txnTime, $queryId){
        
        $unionpay = new UnionPay();
        $res = $unionpay->signatureValidate();
        
        if($res == 1){ //签名验证通过才可
            
            //接口查询是否数据库已更新
            $result_arr = $unionpay->query($orderId, $txnTime);
            
            if(empty($result_arr)) return 0; //为空代表交易失败了
            
            if($result_arr['txnType'] == '01'){  //消费完成执行
            
                $this->onlinePay($orderId, 3, $queryId);
                return 1;
            }elseif ($result_arr['txnType'] == '04'){ //退款执行
            
            }
        }
      
        return 0;
    }
    
    /**
     * 银联前台通知验证 返回1为成功，其他都为失败
     * @param unknown $orderId
     * @param unknown $txnTime
     */
    public function frontReceive($orderId, $txnTime){
        
        $unionpay = new UnionPay();
        $res = $unionpay->signatureValidate();
        
        if($res == 1){ //签名验证通过才可
            
            //接口查询是否数据库已更新
            $result_arr = $unionpay->query($orderId, $txnTime);
            
            if(empty($result_arr)) return 0; //为空代表交易失败了
            
            if($result_arr['txnType'] == '01'){  //消费完成执行
            
                return 1;
            }elseif ($result_arr['txnType'] == '04'){ //退款执行
            
            }
        }
        return 0;
    }
    
    /**
     * 获取此次交易最大可使用余额
     * @param unknown $out_trade_no
     * @param unknown $uid
     */
    public function getMaxAvailableBalance($out_trade_no, $uid){
        $pay = new NsOrderPaymentModel();
        $info = $pay->getInfo(['out_trade_no' => $out_trade_no, 'pay_status' => 0], 'pay_money');
        
        $member = new MemberAccount();
        $member_balance = $member -> getMemberBalance($uid);
        
        if($member_balance > $info['pay_money']){
            $member_balance = $info['pay_money'];
        }
        return $member_balance;
    }
    
    /**
     * 订单使用余额
     * @param unknown $out_trade_no
     * @param unknown $is_use_balance
     * 返回值 0继续支付  1余额支付跳转到个人中心 -1支付异常
     */
    public function orderPaymentUserBalance($out_trade_no, $is_use_balance, $uid){
        // 判断是否使用
        if($is_use_balance > 0){
            $pay = new NsOrderPaymentModel();
            $member_account = new MemberAccount();
            
            $pay -> startTrans();
            $info = $pay->getInfo(['out_trade_no' => $out_trade_no, 'pay_status' => 0], 'original_money');
            if(!empty($info['original_money'])){
                try {
                    // 如果可使用余额为0则继续支付
                    $member_balance = $this->getMaxAvailableBalance($out_trade_no, $uid);
                    if($member_balance == 0){
                        return array(
                            "code" => 0,
                            "message" => ""
                        );
                    }
                    $data = array(
                        "pay_money" => round(($info['original_money'] - $member_balance), 2),
                        "balance_money" => $member_balance,
                    );
                    // 如果原始支付金额减去所用余额不为0的话 继续使用其他支付方式支付
                    if(($info['original_money'] - $member_balance) > 0){
                        $pay -> save($data, ['out_trade_no' => $out_trade_no]);
                        $member_account -> addMemberAccountData(0, 2, $uid, 0,  $member_balance * (- 1), 1, $info['type_alis_id'], "订单支付使用余额，锁定使用余额");
                        $pay -> commit();
                        return array(
                            "code" => 0,
                            "message" => ""
                        );
                    }elseif(($info['original_money'] - $member_balance) == 0){
                        // 如果原始支付金额减去所用余额为0的话 订单使用余额支付
                        $data["pay_status"] = 1;
                        $data["pay_time"] = time();
                        $data["pay_type"] = 5;
                        
                        $order = new Order();
                        $ns_order = new NsOrderModel();
                        $order_info = $ns_order -> getInfo(['out_trade_no' => $out_trade_no], "order_id,order_type");
                        // 针对普通订单 只有一条交易号
                        if(!empty($order_info)){
                            // 更改订单表支付金额 和 使用余额数
                            $ns_order -> save([
                                "user_platform_money" =>$member_balance,
                                "pay_money" => round(($info['original_money'] - $member_balance), 2)
                            ], ['out_trade_no' => $out_trade_no]);
                            // 订单线上支付
                            $order -> orderOnLinePay($out_trade_no, 5);
                            // 添加账户流水
                            $member_account -> addMemberAccountData(0, 2, $uid, 0,  $member_balance * (- 1), 1, $info['type_alis_id'], "商城订单");
                            // 更改支付流水表信息
                            $pay -> save($data, ['out_trade_no' => $out_trade_no]);
                            $pay -> commit();
                            return array(
                                "code" => 1,
                                "message" => ""
                            );
                        }else{
                            // 针对预售订单 会有两条交易号
                            $ns_order_presell = new NsOrderPresellModel();
                            $order_presell_info = $ns_order_presell -> getInfo(['out_trade_no' => $out_trade_no], "relate_id");
                            if(!empty($order_presell_info)){
                                // 预售订单线上支付
                                $order -> presellOrderOnLinePay($out_trade_no, 5);
                                // 更改预售订单表支付金额 和 使用余额数
                                $ns_order_presell -> save([
                                    "platform_money" =>$member_balance,
                                    "presell_pay" => round(($info['original_money'] - $member_balance), 2)
                                ], ['out_trade_no' => $out_trade_no]);
                                // 更改订单表支付金额 和 使用余额数
                                $ns_order -> save([
                                    "user_platform_money" =>$member_balance,
                                ], ['order_id' => $order_presell_info['relate_id']]);
                                // 添加账户流水
                                $member_account -> addMemberAccountData(0, 2, $uid, 0,  $member_balance * (- 1), 1, $info['type_alis_id'], "商城订单");
                                // 更改支付流水表信息
                                $pay -> save($data, ['out_trade_no' => $out_trade_no]);
                                $pay -> commit();
                                return array(
                                    "code" => 1,
                                    "message" => ""
                                );
                            }else{
                                $pay -> rollback();
                                return array(
                                    "code" => -1,
                                    "message" => "支付发生异常，未获取到支付信息"
                                );
                            }
                        }
                    }elseif(($info['original_money'] - $member_balance) < 0){
                        // 如果原始支付金额减去所用余额小于0的话 回滚所有操作
                        $pay -> rollback();
                        return array(
                            "code" => -1,
                            "message" => "支付发生异常"
                        );
                    }
                } catch (\Exception $e) {
                    $pay -> rollback();
                    return array(
                        "code" => -1,
                        "message" => $e->getMessage()
                    );
                }
            }else{
                return array(
                    "code" => -1,
                    "message" => "订单已经支付或者订单价格为0.00，无需再次支付!"
                );
            }
        }else{
            return array(
                "code" => 0,
                "message" => ""
            );
        }
    }
}
