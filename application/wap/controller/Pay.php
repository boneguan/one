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
namespace app\wap\controller;

use data\service\Config;
use data\service\Member as MemberService;
use data\service\Order;
use data\service\UnifyPay;
use data\service\WebSite;
use think\Controller;
use think\Log;
use data\service\Pay\UnionPay;
use data\service\Member;
use data\model\NsMemberRechargeModel;
use data\model\NsOrderModel;
use data\model\NsTuangouGroupModel;
use Qiniu\json_decode;
\think\Loader::addNamespace('data', 'data/');

/**
 * 支付控制器
 *
 * @author Administrator
 *        
 */
class Pay extends BaseController
{

    public $style;

    public $shop_config;

    public function __construct()
    {
        parent::__construct();
        $this->web_site = new WebSite();
        $config = new Config();
        $web_info = $this->web_site->getWebSiteInfo();
        $this->assign("web_info", $web_info);
        $this->assign("shopname", $web_info['title']);
        $this->assign("title", $web_info['title']);
        
        // 使用那个手机模板
        $use_wap_template = $config->getUseWapTemplate(0);
        if (empty($use_wap_template)) {
            $use_wap_template['value'] = 'default_new';
        }
        // 检查模版是否存在
        if (! checkTemplateIsExists("wap", $use_wap_template['value'])) {
            $this->error("模板配置有误，请联系商城管理员");
        }
        
        $this->style = "wap/" . $use_wap_template['value'] . "/";
        $this->assign("style", "wap/" . $use_wap_template['value']);
        $seoConfig = $config->getSeoConfig(0);
        // 购物设置
        $this->shop_config = $config->getShopConfig(0);
        $this->assign("shop_config", $this->shop_config);
        $this->assign("seoconfig", $seoConfig);	
        if($this->uid){
        	$member = new MemberService();
        	$member_info = $member->getMemberDetail();
        	$this->assign('member_info', $member_info);
        }
        $unpaid_goback = "";
        if (isset($_SERVER['HTTP_REFERER'])) {
            if (strpos($_SERVER['HTTP_REFERER'], "paymentorder")) {
                // 如果上一个界面是待付款订单，则直接返回当前的订单详情
                $unpaid_goback = isset($_SESSION['unpaid_goback']) ? $_SESSION['unpaid_goback'] : '';
            } else {
                $unpaid_goback = $_SERVER['HTTP_REFERER'];
            }
        }
        $this->assign("unpaid_goback", $unpaid_goback); // 返回到订单
    }
    
    /* 演示版本 */
    public function demoVersion()
    {
        return view($this->style . 'Pay/demoVersion');
    }

    /**
     * 获取支付相关信息
     */
    public function getPayValue()
    {		
        $out_trade_no = request()->get('out_trade_no', '');
        if (empty($out_trade_no)) {
            $this->error("没有获取到支付信息");
        }
        
        $order_model = new NsOrderModel();
        
        //拼团付款限制
        $is_support_pintuan = IS_SUPPORT_PINTUAN;
        if($is_support_pintuan == 1){
            $order_info = $order_model->getInfo(['out_trade_no'=>$out_trade_no], "tuangou_group_id");
            if($order_info['tuangou_group_id'] > 0){       
            	$tuangou_group = new NsTuangouGroupModel();
            	$pingtuan_info = $tuangou_group->getInfo(['group_id'=>$order_info['tuangou_group_id']], "tuangou_num, status");
            
            	$condition_1['order_status'] = ["in","1,2,3,4"];
            	$condition_1['tuangou_group_id'] = $order_info['tuangou_group_id'];
            
            	$order_list_count = $order_model->getCount($condition_1);
            
            	if($pingtuan_info['tuangou_num'] <= $order_list_count || !in_array($pingtuan_info['status'], [0, 1])){      		 
            		$this->error("该拼团已完成或已关闭");
            		return;
            	}
            }
        }
        
        $pay = new UnifyPay();

        $pay_config = $pay->getPayConfig();
        $alipay_is_use = json_decode($pay_config['ali_pay_config']['value'], true);
        $pay_config['ali_pay_config']['is_use'] = $alipay_is_use['is_use'];
        
        $this->assign("pay_config", $pay_config);
        $pay_value = $pay->getPayInfo($out_trade_no);
        
        if (empty($pay_value)) {
            $this->error("订单主体信息已发生变动!", __URL(__URL__ . "wap/member/index"));
        }
        
        if ($pay_value['pay_status'] != 0) {
            // 订单已经支付
            $this->error("订单已经支付或者订单价格为0.00，无需再次支付!", __URL(__URL__ . "wap/member/index"));
        }
        if ($pay_value['type'] == 1) {
            // 订单
            $order_status = $this->getOrderStatusByOutTradeNo($out_trade_no);
            // 订单关闭状态下是不能继续支付的
            if ($order_status == 5) {
                $this->error("订单已关闭", __URL(__URL__ . "wap/member/index"));
            }
        }
        
        $zero1 = time(); // 当前时间 ,注意H 是24小时 h是12小时
        $zero2 = $pay_value['create_time'];
        if($this->shop_config['order_buy_close_time'] == 0 || $this->shop_config['order_buy_close_time'] == ""){
        	$this->assign('pay_value', $pay_value);
        	if (request()->isMobile()) {
        		$this->user = new Member();
        		$this->shop_name = $this->user->getInstanceName();
        		$this->assign("platform_shopname", $this->shop_name); // 平台店铺名称
        		return view($this->style . 'Pay/getPayValue'); // 手机端
        	} else {
        		return view($this->style . 'Pay/pcOptionPaymentMethod'); // PC端
        	}
        }else{
        	if ($zero1 > ($zero2 + ($this->shop_config['order_buy_close_time'] * 60))){
        		$this->error("订单已关闭");
        	} else {
        		$this->assign('pay_value', $pay_value);
        		if (request()->isMobile()) {
        			$this->user = new Member();
        			$this->shop_name = $this->user->getInstanceName();
        			$this->assign("platform_shopname", $this->shop_name); // 平台店铺名称
        			return view($this->style . 'Pay/getPayValue'); // 手机端
        		} else {
        			return view($this->style . 'Pay/pcOptionPaymentMethod'); // PC端
        		}
        	}
        }

    }

    /**
     * 支付完成后回调界面
     *
     * status 1 成功
     *
     * @return \think\response\View
     */
    public function payCallback()
    {
        $out_trade_no = request()->get('out_trade_no', ''); // 流水号
        $msg = request()->get('msg', ''); // 测试，-1：在其他浏览器中打开，1：成功，2：失败
        $this->assign("status", $msg);
        $order_no = $this->getOrderNoByOutTradeNo($out_trade_no);
        $this->assign("order_no", $order_no);
        if (request()->isMobile()) {
            return view($this->style . "Pay/payCallback");
        } else {
            return view($this->style . "Pay/payCallbackPc");
        }
    }

    /**
     * 订单微信支付
     */
    public function wchatPay()
    {
        $out_trade_no = request()->get('no', '');
        
        if (! is_numeric($out_trade_no)) {
            $this->error("没有获取到支付信息");
        }
        
        $red_url = str_replace("/index.php", "", __URL__);
        $red_url = str_replace("index.php", "", $red_url);
        $red_url = $red_url . "/weixinpay.php";
        $pay = new UnifyPay();
        
        if (! isWeixin()) {
            // 扫码支付
            if (request()->isMobile()) {
                $res = $pay->wchatPay($out_trade_no, 'MWEB', $red_url);
                if (empty($res['mweb_url'])) {
                    $this->error($res['return_msg']);
                    die();
                }
                
                $this->redirect($res["mweb_url"]);
            } else {
                $res = $pay->wchatPay($out_trade_no, 'NATIVE', $red_url);
                if ($res["return_code"] == "SUCCESS") {
                    if (empty($res['code_url'])) {
                        $code_url = "生成支付二维码失败!";
                    } else {
                        $code_url = $res['code_url'];
                    }
                    if (! empty($res["err_code"]) && $res["err_code"] == "ORDERPAID" && $res["err_code_des"] == "该订单已支付") {
                        $this->error($res["err_code_des"],__URL(__URL__ . "/member/index"));
                    }
                } else {
                    $code_url = "生成支付二维码失败!";
                }
                $path = getQRcode($code_url, "upload/qrcode/pay", $out_trade_no);
                $this->assign("path", __ROOT__ . '/' . $path);
                $pay_value = $pay->getPayInfo($out_trade_no);
                $this->assign('pay_value', $pay_value);
                return view($this->style . "Pay/pcWeChatPay");
            }
        } else {
            // jsapi支付
            $res = $pay->wchatPay($out_trade_no, 'JSAPI', $red_url);
            if (! empty($res["return_code"]) && $res["return_code"] == "FAIL" && $res["return_msg"] == "JSAPI支付必须传openid") {
                $this->error($res['return_msg'],__URL(__URL__ . "/wap/member/index"));
            } else {
                $retval = $pay->getWxJsApi($res);
                $this->assign("out_trade_no", $out_trade_no);
                $this->assign('jsApiParameters', $retval);
                return view($this->style . 'Pay/weixinPay');
            }
        }
    }

    /**
     * 微信支付异步回调（只有异步回调对订单进行处理）
     */
    public function wchatUrlBack()
    {
        $postStr = file_get_contents('php://input');
        if (! empty($postStr)) {
            libxml_disable_entity_loader(true);
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $pay = new UnifyPay();
            $check_sign = $pay->checkSign($postObj, $postObj->sign);
            if ($postObj->result_code == 'SUCCESS' && $check_sign == 1) {
                
                $retval = $pay->onlinePay($postObj->out_trade_no, 1, '');
                $xml = "<xml>
                    <return_code><![CDATA[SUCCESS]]></return_code>
                    <return_msg><![CDATA[支付成功]]></return_msg>
                </xml>";
                echo $xml;
            } else {
                $xml = "<xml>
                    <return_code><![CDATA[FAIL]]></return_code>
                    <return_msg><![CDATA[支付失败]]></return_msg>
                </xml>";
                echo $xml;
            }
        }
    }

    /**
     * 微信支付同步回调（不对订单处理）
     */
    public function wchatPayResult()
    {
        $out_trade_no = request()->get('out_trade_no', '');
        $msg = request()->get('msg', '');
        $this->assign("status", $msg);
        $order_no = $this->getOrderNoByOutTradeNo($out_trade_no);
       
        $this->assign("order_no", $order_no);
        if (request()->isMobile()) {
            return view($this->style . "Pay/payCallback");
        } else {
            return view($this->style . "Pay/payCallbackPc");
        }
    }

    /**
     * 微信支付状态
     */
    public function wchatPayStatu()
    {
        if (request()->isAjax()) {
            $out_trade_no = request()->post("out_trade_no", "");
            $pay = new UnifyPay();
            $payResult = $pay->getPayInfo($out_trade_no);
            if ($payResult['pay_status'] > 0) {
                return $retval = array(
                    "code" => 1,
                    "message" => ''
                );
            }
        }
    }

    /**
     * 支付宝支付
     */
    public function aliPay()
    {
        $out_trade_no = request()->get('no', '');
        if (! is_numeric($out_trade_no)) {
            $this->error("没有获取到支付信息");
        }
        if (! isWeixin()) {
        	$notify_url = str_replace("/index.php", '', __URL__);
        	$notify_url = str_replace("index.php", '', $notify_url);
        	$notify_url = $notify_url . "/alipay.php";
            $return_url = __URL(__URL__ . '/wap/Pay/aliPayReturn');
            $show_url = __URL(__URL__ . '/wap/Pay/aliUrlBack');
            $pay = new UnifyPay();
            Log::write("支付宝------------------------------------" . $notify_url);
            $res = $pay->aliPay($out_trade_no, $notify_url, $return_url, $show_url);
            echo "<meta charset='UTF-8'><script>window.location.href='" . $res . "'</script>";
        } else {
            // echo "点击右上方在浏览器中打开";
            $this->assign("status", - 1);
            $order_no = $this->getOrderNoByOutTradeNo($out_trade_no);
            $this->assign("order_no", $order_no);
            if (request()->isMobile()) {
                return view($this->style . "Pay/payCallback");
            } else {
                return view($this->style . "Pay/payCallbackPc");
            }
        }
    }

    /**
     * 支付宝支付异步回调
     */
    public function aliUrlBack()
    {
        Log::write("支付宝------------------------------------进入回调用");
        $pay = new UnifyPay();
        $params = request()->post();
        $verify_result = $pay->getVerifyResult($params, 'notify');
        if ($verify_result) { // 验证成功
            $out_trade_no = request()->post('out_trade_no', '');
            // 支付宝交易号
            $trade_no = request()->post('trade_no', '');
            
            // 交易状态
            $trade_status = request()->post('trade_status', '');
            
            Log::write("支付宝------------------------------------交易状态：" . $trade_status);
            if ($trade_status == 'TRADE_FINISHED') {
                // 判断该笔订单是否在商户网站中已经做过处理
                // 如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
                // 如果有做过处理，不执行商户的业务程序
                // 注意：
                // 退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
                
                // 调试用，写文本函数记录程序运行情况是否正常
                // logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
                $retval = $pay->onlinePay($out_trade_no, 2, $trade_no);
                Log::write("支付宝------------------------------------retval：" . $retval);
                // $res = $order->orderOnLinePay($out_trade_no, 2);
            } else 
                if ($trade_status == 'TRADE_SUCCESS') {
                    // 判断该笔订单是否在商户网站中已经做过处理
                    // 如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
                    // 如果有做过处理，不执行商户的业务程序
                    
                    // 注意：
                    // 付款完成后，支付宝系统发送该交易状态通知
                    $retval = $pay->onlinePay($out_trade_no, 2, $trade_no);
                    Log::write("支付宝------------------------------------retval：" . $retval);
                    // $res = $order->orderOnLinePay($out_trade_no, 2);
                    // 调试用，写文本函数记录程序运行情况是否正常
                    // logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
                }
            
            // ——请根据您的业务逻辑来编写程序（以上代码仅作参考）——
            
            echo "success"; // 请不要修改或删除
                                
            // $this->assign("status", 1);
                                // $this->assign("out_trade_no", $out_trade_no);
                                // return view($this->style . "Pay/payCallback");
                                
            // ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        } else {
            // 验证失败
            echo "fail";
            
            // $this->assign("status", 2);
            // $this->assign("out_trade_no", $out_trade_no);
            // return view($this->style . "Pay/payCallback");
            // 调试用，写文本函数记录程序运行情况是否正常
        } // logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
    }

    /**
     * 支付宝支付同步会调（页面）（不对订单进行处理）
     */
    public function aliPayReturn()
    {
        $out_trade_no = request()->get('out_trade_no', '');
        
        $order_no = $this->getOrderNoByOutTradeNo($out_trade_no);
        $this->assign("order_no", $order_no);
        $pay = new UnifyPay();
        $parmas = request()->get();
        $verify_result = $pay->getVerifyResult($parmas, 'return');
        if ($verify_result) {
            $trade_no = request()->get('trade_no', '');
            $trade_status = request()->get('trade_status', '');
            
            $config = new Config();
            $edition = $config->getEditionSetting($this->instance_id);
            $edition = json_decode($edition['value'], true);
            if($edition['is_use'] == 0){
            	if ($trade_no == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS') {
            		// return view($this->style . 'Pay/pay_success');
            		// echo "<h1>支付成功</h1>";
            		$this->assign("status", 1);
            		if (request()->isMobile()) {
            			return view($this->style . "Pay/payCallback");
            		} else {
            			return view($this->style . "Pay/payCallbackPc");
            		}
            	} else {
            		echo "trade_status=" . $trade_status;
            	}
            }else{
            	$this->assign("status", 1);
            	if (request()->isMobile()) {
            		return view($this->style . "Pay/payCallback");
            	} else {
            		return view($this->style . "Pay/payCallbackPc");
            	}
            }
            $this->assign("orderNumber", $out_trade_no);
            $this->assign("msg", 1);
        } else {
            $this->assign("orderNumber", $out_trade_no);
            $this->assign("msg", 0);
            // echo "<h1>支付失败</h1>";
            $this->assign("status", 2);
            if (request()->isMobile()) {
                return view($this->style . "Pay/payCallback");
            } else {
                return view($this->style . "Pay/payCallbackPc");
            }
            // echo "验证失败";
        }
    }

    /**
     * 根据流水号查询订单编号，
     * 创建时间：2017年10月9日 18:36:54
     *
     * @param unknown $out_trade_no            
     * @return string
     */
    public function getOrderNoByOutTradeNo($out_trade_no)
    {
        $pay = new UnifyPay();
        $order = new Order();
        $member_pay_model = new NsMemberRechargeModel();
        
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
        	/* $order_no = [];
            $pay_info = $member_pay_model->getInfo(["out_trade_no"=>$out_trade_no]);
            if($pay_info){
            	$order_no['out_trade_no'] = $out_trade_no;
            	$order_no['uid'] = $pay_info['uid'];
            	$order_no['shop_id'] = $pay_value['shop_id'];
            } */
        }
        return $order_no;
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
        $order = new Order();
        $order_status = $order->getOrderStatusByOutTradeNo($out_trade_no);
        if (! empty($order_status)) {
            return $order_status['order_status'];
        }
        return 0;
    }

    /**
     * 银联卡支付
     */
    public function unionpay()
    {
        $out_trade_no = request()->get('no', '');
        if (! is_numeric($out_trade_no)) {
            $this->error("没有获取到支付信息");
        }
        
        $pay = new UnifyPay();
        $pay_info = $pay->getPayInfo($out_trade_no);
        
        $unionpay = new UnionPay();
        $txnTime = date('YmdHis', $pay_info['create_time']);
        $unionpay->frontConsume($out_trade_no, $txnTime, $pay_info['pay_money']);
        
        // $unionpay->backReceive();
    }

    /**
     * 银联交易完成后台回调（异步）
     */
    public function backReceive()
    {
        $orderId = $_POST['orderId']; // 其他字段也可用类似方式获取
        $queryId = $_POST['queryId'];
        $txnTime = $_POST['txnTime'];
        
        $respCode = $_POST['respCode'];
        // 判断respCode=00、A6后，对涉及资金类的交易，请再发起查询接口查询，确定交易成功后更新数据库。
        if ($respCode == 00 || $respCode == 'A6') {
            
            $pay = new UnifyPay();
            $result = $pay->backReceive($orderId, $txnTime, $queryId);
        } else {
            echo '交易失败';
        }
    }

    /**
     * 银联交易完成前台回调（异步只有跳转了页面才可调用）
     */
    public function frontReceive()
    {
        $respCode = $_POST['respCode'];
        $orderId = $_POST['orderId']; // 其他字段也可用类似方式获取
        $txnTime = $_POST['txnTime'];
        // 判断respCode=00、A6后，对涉及资金类的交易，请再发起查询接口查询，确定交易成功后更新数据库。
        $result = 0;
        if ($respCode == 00 || $respCode == 'A6') {
            
            $pay = new UnifyPay();
            $result = $pay->frontReceive($orderId, $txnTime);
        } else {
            // echo '交易失败';
        }
        
        $out_trade_no = $orderId; // 流水号
        $msg = request()->get('msg', ''); // 测试，-1：在其他浏览器中打开，1：成功，2：失败
        $this->assign("status", $result);
        $order_no = $this->getOrderNoByOutTradeNo($out_trade_no);
        $this->assign("order_no", $order_no);
        if (request()->isMobile()) {
            return view($this->style . "Pay/payCallback");
        } else {
            return view($this->style . "Pay/payCallbackPc");
        }
    }
    
    // public function unionpayRefund(){
    // $unionpay = new unionpay();
    // $txnTime = date('YmdHis', time());
    // $unionpay->refund(time(), '777290058156992', '721802281909022013068', $txnTime, 1000);
    // }
    
    /**
     * 余额支付选择界面
     */
    public function pay(){
        // 实例化类
        $member = new Member();
        $pay = new UnifyPay();
        $config = new Config();
        $uid = $member->getCurrUserId();
        
        if(request() -> isAjax()){
            $out_trade_no = request()->post("out_trade_no", 0);
            
          	 $order_model = new NsOrderModel();
          	 //拼团支付阻断
          	 $is_support_pintuan = IS_SUPPORT_PINTUAN;
          	 if($is_support_pintuan == 1){
                 $order_info = $order_model->getInfo(['out_trade_no'=>$out_trade_no], "tuangou_group_id");
                 if($order_info['tuangou_group_id'] > 0){
                 $tuangou_group = new NsTuangouGroupModel();
                 $pingtuan_info = $tuangou_group->getInfo(['group_id'=>$order_info['tuangou_group_id']], "tuangou_num, status");
                
                 $condition_1['order_status'] = ["in","1,2,3,4"];
                 $condition_1['tuangou_group_id'] = $order_info['tuangou_group_id'];
                
                 $order_list_count = $order_model->getCount($condition_1);
    	             if($pingtuan_info['tuangou_num'] <= $order_list_count || !in_array($pingtuan_info['status'], [0, 1])){	            
        	             $res = [
                            'code'=> -1,
                     		'message'=>"该拼团已完成或已关闭"
        	             ];
        	             return $res;
    	             }
                 }
          	}
          	 
            $is_use_balance = request()->post("is_use_balance", 0);
            $res = $pay -> orderPaymentUserBalance($out_trade_no, $is_use_balance, $uid);
            return $res;
        }else{
            $out_trade_no = request()->get("out_trade_no", 0); 
            // 支付信息
            $pay_value = $pay->getPayInfo($out_trade_no);
            $this->assign("pay_value", $pay_value);
            
            if (empty($out_trade_no) || !is_numeric($out_trade_no) || empty($pay_value)) {
                $this->error("没有获取到支付信息");
            }
            if(empty($uid)){
                $this->error("没有获取到用户信息");
            }
            
            // 此次交易最大可用余额
            $member_balance = $pay->getMaxAvailableBalance($out_trade_no, $uid);
            $this->assign("member_balance", $member_balance);
            
            $shop_id = 0;
            $shop_config = $config->getConfig($shop_id, "ORDER_BALANCE_PAY");
            
            // 如果商家未开启余额支付或余额未0 直接跳转到支付界面
            if($shop_config['value'] == 0 || $member_balance == 0){
                $this->redirect(__URL(__URL__ . "wap/pay/getPayValue?out_trade_no=".$out_trade_no));
            }
           
            // 支付方式配置
            $pay_config = $pay->getPayConfig();
            $alipay_is_use = json_decode($pay_config['ali_pay_config']['value'], true);
       		$pay_config['ali_pay_config']['is_use'] = $alipay_is_use['is_use'];
            $this->assign("pay_config", $pay_config);
            $order_status = $this->getOrderStatusByOutTradeNo($out_trade_no);
            // 订单关闭状态下是不能继续支付的
            if ($order_status == 5) {
                $this->error("订单已关闭");
            }
            
            // 还需支付的金额
            $need_pay_money = round($pay_value['pay_money'], 2) - round($member_balance, 2);
            $this->assign("need_pay_money", sprintf("%.2f", $need_pay_money));
            $zero1 = time(); // 当前时间 ,注意H 是24小时 h是12小时
            $zero2 = $pay_value['create_time'];
            if($this->shop_config['order_buy_close_time'] == 0 || $this->shop_config['order_buy_close_time'] == ""){
            	if (request()->isMobile()) {
            		return view($this->style . 'Pay/wapPay');
            	}else{
            		return view($this->style . "Pay/pcPay");
            	}
            }else{
            	if ($zero1 > ($zero2 + ($this->shop_config['order_buy_close_time'] * 60)) && $this->shop_config['order_buy_close_time'] != 0 ) {
            		$this->error("订单已关闭");
            	}else{
            		if (request()->isMobile()) {
            			return view($this->style . 'Pay/wapPay');
            		}else{
            			return view($this->style . "Pay/pcPay");
            		}
            	}
            }
            
        }
    }
}