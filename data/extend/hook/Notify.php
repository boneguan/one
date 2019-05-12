<?php
namespace data\extend\hook;

use data\model\WebSiteModel;
use data\model\UserModel;
use data\model\ConfigModel;
use data\model\NoticeTemplateModel;
use data\model\NsOrderGoodsModel;
use data\model\NsOrderModel;
use phpDocumentor\Reflection\Types\This;
use data\model\NsOrderGoodsExpressModel;
use think\Log;
use data\model\NsOrderPaymentModel;
use data\service\Notice;
use app\admin\controller\Tuangou;
use data\model\NsTuangouGroupModel;
use data\model\NsPromotionBargainLaunchModel;
use data\model\NsGoodsModel;
class Notify
{
    public $result=array(
        "code"=>0,
        "message"=>"success",
        "param"=>""
    );
    /**
     * 邮件的配置信息
     * @var unknown
    */
    public $email_is_open=0;
    public $email_host="";
    public $email_port="";
    public $email_addr="";
    public $email_id="";
    public $email_pass="";
    public $email_is_security=false;
    /**
     * 短信的配置信息
     * @var unknown
     */
    public $mobile_is_open;
    public $appKey="";
    public $secretKey="";
    public $freeSignName="";
    
    public $shop_name;
    
    public $ali_use_type=0;
    /**
     * 得到系统通知的配置信息
     * @param unknown $shop_id
     */
    private function getShopNotifyInfo($shop_id){
    
        $website_model=new WebSiteModel();
        $website_obj=$website_model->getInfo("1=1", "title");
        if(empty($website_obj)){
            $this->shop_name="NiuShop开源商城";
        }else{
            $this->shop_name=$website_obj["title"];
        }
    
        $config_model=new ConfigModel();
        #查看邮箱是否开启
        $email_info=$config_model->getInfo(["instance_id"=>$shop_id, "`key`"=>"EMAILMESSAGE"], "*");
        if(!empty($email_info)){
            $this->email_is_open=$email_info["is_use"];
            $value=$email_info["value"];
            if(!empty($value)){
                $email_array=json_decode($value, true);
                $this->email_host=$email_array["email_host"];
                $this->email_port=$email_array["email_port"];
                $this->email_addr=$email_array["email_addr"];
                $this->email_id=$email_array["email_id"];
                $this->email_pass=$email_array["email_pass"];
                $this->email_is_security=$email_array["email_is_security"];
            }
        }
        $mobile_info=$config_model->getInfo(["instance_id"=>$shop_id, "`key`"=>"MOBILEMESSAGE"], "*");
        if(!empty($mobile_info)){
            $this->mobile_is_open=$mobile_info["is_use"];
            $value=$mobile_info["value"];
            if(!empty($value)){
                $mobile_array=json_decode($value, true);
                $this->appKey=$mobile_array["appKey"];
                $this->secretKey=$mobile_array["secretKey"];
                $this->freeSignName=$mobile_array["freeSignName"];
                $this->ali_use_type=$mobile_array["user_type"];
                if(empty($this->ali_use_type)){
                    $this->ali_use_type=0;
                }
            }
        }
    }
   
    /**
     * 查询模板的信息
     * @param unknown $shop_id
     * @param unknown $template_code
     * @param unknown $type
     * @return unknown
     */
    private function getTemplateDetail($shop_id, $template_code, $type, $notify_type = "user"){
       $template_model=new NoticeTemplateModel();
       $template_obj=$template_model->getInfo(["instance_id"=>$shop_id, "template_type"=>$type, "template_code"=>$template_code, "notify_type" => $notify_type]);
       return $template_obj;
    }
    /**
     * 处理阿里大于 的返回数据
     * @param unknown $result
     */
    private function dealAliSmsResult($result){
        $deal_result=array();
        try {
            if($this->ali_use_type==0){
                #旧用户发送
                if(!empty($result)){
                    if(!isset($result->result)){
                        $result=json_decode(json_encode($result), true);
                        #发送失败
                        $deal_result["code"]=$result["code"];
                        $deal_result["message"]=$result["sub_msg"];
                    }else{
                        #发送成功
                        $deal_result["code"]=0;
                        $deal_result["message"]="发送成功";
                    }
                }
            }else{
                #新用户发送
                if(!empty($result)){
                    if($result->Code=="OK"){
                        #发送成功
                        $deal_result["code"]=0;
                        $deal_result["message"]="发送成功";
                    }else{
                        #发送失败
                        $deal_result["code"]=-1;
                        $deal_result["message"]=$result->Message;
                    }
                }
          }
        } catch (\Exception $e) {
            $deal_result["code"]=-1;
            $deal_result["message"]="发送失败!";
        }
        
        return $deal_result;
    }
    /**
     * 用户注册成功后
     * @param string $params
     */
    public function registAfter($params=null){
        /**
         * 店铺id
         */
        $shop_id=$params["shop_id"];
        #查询系统配置信息
        $this->getShopNotifyInfo(0);
        /**
         * 用户id
         */
        $user_id=$params["user_id"];
        
        $user_model=new UserModel();
        $user_obj=$user_model->get($user_id);
        $mobile="";
        $user_name="";
        $email="";
        if(empty($user_obj)){
            $user_name="用户";
        }else{
            $user_name=$user_obj["nick_name"];
            $mobile=$user_obj["user_tel"];
            $email=$user_obj["user_email"];
        }
        #短信验证
        if(!empty($mobile) && $this->mobile_is_open==1){
        	
            $template_obj=$this->getTemplateDetail($shop_id, "after_register", "sms");
            if(!empty($template_obj) && $template_obj["is_enable"]==1){
            	
                $sms_params=array(
                    "shop_name"=>$this->shop_name,
                    "shopname"=>$this->shop_name,
                    "user_name"=>$user_name,
                    "username"=>$user_name
                );
                $this->createNoticeSmsRecords($template_obj, $shop_id, $params["user_id"], $mobile, $sms_params, "注册成功短信通知", 17);
            }
        }
        #邮箱验证
        if(!empty($email) && $this->email_is_open==1){
            $template_obj=$this->getTemplateDetail($shop_id, "after_register", "email");
            if(!empty($template_obj) && $template_obj["is_enable"]==1){
                $content=$template_obj["template_content"];
                $content=str_replace("{商场名称}", $this->shop_name, $content);
                $content=str_replace("{用户名称}", $user_name, $content);
                $send_title=$template_obj["template_title"];
                $send_title=str_replace("{商场名称}", $this->shop_name, $send_title);
                $send_title=str_replace("{用户名称}", $user_name, $send_title);
              	// $result=emailSend($this->email_host, $this->email_id, $this->email_pass, $this->email_port, $this->email_is_security, $this->email_addr, $email, $send_title, $content, $this->shop_name);
                $this->createNoticeEmailRecords($shop_id, $params["user_id"], $email, $send_title, $content, 5);
            }
        }
    }

    /**
     * 注册短信验证
     * @param string $params
     */
    public function registSmsValidation($params=null){
        $rand = rand(100000,999999);
        $mobile=$params["mobile"];
        $shop_id=$params["shop_id"];
        #查询系统配置信息
        $this->getShopNotifyInfo($shop_id);
        $result="";
        if(!empty($mobile) && $this->mobile_is_open==1){
            $template_obj=$this->getTemplateDetail($shop_id, "register_validate", "sms");
            if(!empty($template_obj) && $template_obj["is_enable"]==1){
                $sms_params=array(
                    "number"=>$rand.""
                );
                $this->result["param"]=$rand;
                if(!empty($this->appKey) && !empty($this->secretKey) && !empty($template_obj["sign_name"]) && !empty($template_obj["template_title"])){
                    $result=aliSmsSend($this->appKey, $this->secretKey,
                        $template_obj["sign_name"], json_encode($sms_params), $mobile, $template_obj["template_title"], $this->ali_use_type);
                    $result=$this->dealAliSmsResult($result);
                    $this->result["code"]=$result["code"];
                    $this->result["message"]=$result["message"];
                    $this->result["param"]=$rand;
                }else{
                    $this->result["code"]=-1;
                    $this->result["message"]="短信配置信息有误!";
                    $this->result["param"]=0;
                }
            }else{
                $this->result["code"]=-1;
                $this->result["message"]="短信通知模板有误!";
                $this->result["param"]=0;
            }
        }else{
            $this->result["code"]=-1;
            $this->result["message"]="店家没有开启短信验证";
            $this->result["param"]=0;
        }
        $send_result = $this->result["code"] < 0 ? -1 : 1;
        $this->createVerificationCodeRecords($template_obj, $shop_id, 0, 1, $mobile, 9, "用户注册短信验证码", "验证码", $this->result["message"], $send_result);
        return $this->result;
    }
    
    /**
     * 注册邮箱验证
     * 已测试
     * @param string $params
     */
    public function registEmailValidation($params=null){
        $rand = rand(100000,999999);
        $email=$params["email"];
        $shop_id=$params["shop_id"];
        #查询系统配置信息
        $this->getShopNotifyInfo($shop_id);
        if(!empty($email) && $this->email_is_open==1){
            $template_obj=$this->getTemplateDetail($shop_id, "register_validate", "email");
            if(!empty($template_obj) && $template_obj["is_enable"]==1){
                $content=$template_obj["template_content"];
                $content=str_replace("{验证码}", $rand, $content);
                if(!empty($this->email_host) && !empty($this->email_id) && !empty($this->email_pass) && !empty($this->email_addr)){
                    $result=emailSend($this->email_host, $this->email_id, $this->email_pass, $this->email_port, $this->email_is_security, $this->email_addr, $email, $template_obj["template_title"], $content, $this->shop_name);
                    $this->result["param"]=$rand;
                    if($result){
                        $this->result["code"]=0;
                        $this->result["message"]="发送成功!";
                    }else{
                        $this->result["code"]=-1;
                        $this->result["message"]="发送失败!";
                    }
                }else{
                    $this->result["code"]=-1;
                    $this->result["message"]="邮箱配置信息有误!";
                }
            }else{
                $this->result["code"]=-1;
                $this->result["message"]="配置邮箱注册验证模板有误!";
            }
        }else{
            $this->result["code"]=-1;
            $this->result["message"]="店家没有开启邮箱验证";
        }
        $send_result = $this->result["code"] < 0 ? -1 : 1;
        $this->createVerificationCodeRecords($template_obj, $shop_id, 0, 2, $email, 10, "用户注册邮箱验证码", "验证码", $this->result["message"], $send_result);
        return $this->result;
        
    }
    /**
     * 订单发货
     * @param string $params
     */
    public function orderDelivery($params=null){
        #查询系统配置信息
        $this->getShopNotifyInfo(0);
        $order_goods_ids=$params["order_goods_ids"];
        $order_goods_str=explode(",", $order_goods_ids);
        $result="";
        $user_name="";
        $order_model=new NsOrderModel();
        $user_model = new UserModel();
        if(count($order_goods_str)>0){
            $order_goods_id=$order_goods_str[0];
            $order_goods_model=new NsOrderGoodsModel();
            $order_goods_obj=$order_goods_model->get($order_goods_id);
            $shop_id=$order_goods_obj["shop_id"];
            $order_id=$order_goods_obj["order_id"];
            $order_obj=$order_model->get($order_id);
            $buyer_id=$order_obj["buyer_id"];
            $user_obj = $user_model->get($buyer_id);
            $user_name=$user_obj["nick_name"];
            $goods_name=$order_goods_obj["goods_name"];
            $goods_name = mb_substr($goods_name,0,19,'utf-8');
            $goods_sku=$order_goods_obj["sku_name"];
            $order_no=$order_obj["out_trade_no"];
            $order_money=$order_obj["order_money"];
            $goods_money=$order_goods_obj["goods_money"];
            $mobile=$order_obj["receiver_mobile"];
            $goods_express_model=new NsOrderGoodsExpressModel();
            $express_obj=$goods_express_model->getInfo(["order_id"=>$order_id, "order_goods_id_array"=>$order_goods_ids], "*");
            $express_obj["express_name"] = $express_obj["express_name"] != null ? $express_obj["express_name"] : '';
            $express_obj["express_no"] = $express_obj["express_no"] != null ? $express_obj["express_no"] : '';
            $sms_params=array(
                "shop_name"=>$this->shop_name,
                "user_name"=>$user_name,
                "goods_name"=>$goods_name,
                "goods_sku"=>$goods_sku,
                "order_no"=>$order_no,
                "order_money"=>$order_money,
                "goods_money"=>$goods_money,
                "express_company"=>$express_obj["express_name"],
                "express_no"=>$express_obj["express_no"],
                "shopname"=>$this->shop_name,
                "username"=>$user_name,
                "goodsname"=>$goods_name,
                "goodssku"=>$goods_sku,
                "orderno"=>$order_no,
                "ordermoney"=>$order_money,
                "goodsmoney"=>$goods_money,
                "expresscompany"=>$express_obj["express_name"],
                "expressno"=>$express_obj["express_no"]
            );
            #短信发送
            if(!empty($mobile) && $this->mobile_is_open==1){
                $template_obj=$this->getTemplateDetail($shop_id, "order_deliver", "sms");
                if(!empty($template_obj) && $template_obj["is_enable"]==1){
                    $this->createNoticeSmsRecords($template_obj, $shop_id, $buyer_id, $mobile, $sms_params, "订单发货短信发送", 5);
                }
            }
            // 邮件发送
            if (!empty($user_obj)) {
                $email = $user_obj["user_email"];
                if (! empty($email) && $this->email_is_open == 1) {
                    $template_obj = $this->getTemplateDetail($shop_id, "order_deliver", "email");
                    if (! empty($template_obj) && $template_obj["is_enable"] == 1) {
                        $content = $template_obj["template_content"];
                        $content = str_replace("{商场名称}", $this->shop_name, $content);
                        $content = str_replace("{用户名称}", $user_name, $content);
                        $content = str_replace("{商品名称}", $goods_name, $content);
                        $content = str_replace("{商品规格}", $goods_sku, $content);
                        $content = str_replace("{主订单号}", $order_no, $content);
                        $content = str_replace("{订单金额}", $order_money, $content);
                        $content = str_replace("{商品金额}", $goods_money, $content);
                        $content = str_replace("{物流公司}", $express_obj["express_name"], $content);
                        $content = str_replace("{快递编号}", $express_obj["express_no"], $content);
                        
                        $send_title=$template_obj["template_title"];
                        $send_title = str_replace("{商场名称}", $this->shop_name, $send_title);
                        $send_title = str_replace("{用户名称}", $user_name, $send_title);
                        $send_title = str_replace("{商品名称}", $goods_name, $send_title);
                        $send_title = str_replace("{商品规格}", $goods_sku, $send_title);
                        $send_title = str_replace("{主订单号}", $order_no, $send_title);
                        $send_title = str_replace("{订单金额}", $order_money, $send_title);
                        $send_title = str_replace("{商品金额}", $goods_money, $send_title);
                        $send_title = str_replace("{物流公司}", $express_obj["express_name"], $send_title);
                        $send_title = str_replace("{快递编号}", $express_obj["express_no"], $send_title);
                        $this->createNoticeEmailRecords($shop_id, $buyer_id, $email, $send_title, $content, 5);
                    }
                }
            }
        }
    }
    /**
     * 订单确认
     * @param string $params
     */
    public function orderComplete($params=null){
        #查询系统配置信息
        $this->getShopNotifyInfo(0);
        $order_id=$params["order_id"];
        $order_model=new NsOrderModel();
        $order_obj=$order_model->get($order_id);
        $shop_id=$order_obj["shop_id"];
        $buyer_id=$order_obj["buyer_id"];
        $user_name=$order_obj["receiver_name"];
        $order_no=$order_obj["out_trade_no"];
        $order_money=$order_obj["order_money"];
        $mobile=$order_obj["receiver_mobile"];
        $sms_params=array(
            "shop_name"=>$this->shop_name,
            "user_name"=>$user_name,
            "order_no"=>$order_no,
            "order_money"=>$order_money,
            "shopname"=>$this->shop_name,
            "username"=>$user_name,
            "orderno"=>$order_no,
            "ordermoney"=>$order_money
        );
            // 短信发送
        if (! empty($mobile) && $this->mobile_is_open == 1) {
            $template_obj = $this->getTemplateDetail($shop_id, "confirm_order", "sms");
            if (! empty($template_obj) && $template_obj["is_enable"] == 1) {
                $this->createNoticeSmsRecords($template_obj, $shop_id, $buyer_id, $mobile, $sms_params, "订单确认短信发送", 2);
            }
        }
        // 邮件发送
        $user_model = new UserModel();
        $user_obj = $user_model->get($buyer_id);
        if (! empty($user_obj)) {
        	
            $email = $user_obj["user_email"];
            if (! empty($email) && $this->email_is_open == 1) {
                $template_obj = $this->getTemplateDetail($shop_id, "confirm_order", "email");
                if (! empty($template_obj) && $template_obj["is_enable"] == 1) {
                    $content = $template_obj["template_content"];
                    $content = str_replace("{商场名称}", $this->shop_name, $content);
                    $content=str_replace("{用户名称}", $user_name, $content);
                    $content=str_replace("{主订单号}", $order_no, $content);
                    $content=str_replace("{订单金额}", $order_money, $content);
                    
                    $send_title=$template_obj["template_title"];
                    $send_title= str_replace("{商场名称}", $this->shop_name, $send_title);
                    $send_title=str_replace("{用户名称}", $user_name, $send_title);
                    $send_title=str_replace("{主订单号}", $order_no, $send_title);
                    $send_title=str_replace("{订单金额}", $order_money, $send_title);
                    $this->createNoticeEmailRecords($shop_id, $buyer_id, $email, $send_title, $content, 2);
                }
            }
        }
    }
    /**
     * 订单付款成功
     * @param string $params
     */
    public function orderPay($params=null){
        #查询系统配置信息
        $this->getShopNotifyInfo(0);
        
        $order_id=$params["order_id"];
        $order_model=new NsOrderModel();
        $order_obj=$order_model->get($order_id);
        $shop_id=$order_obj["shop_id"];
        $buyer_id=$order_obj["buyer_id"];
        $user_name=$order_obj["receiver_name"];
        $order_no=$order_obj["out_trade_no"];
        $order_money=$order_obj["order_money"];
        $mobile=$order_obj["receiver_mobile"];
        $goods_money=$order_obj["goods_money"];
        $sms_params=array(
            "shop_name"=>$this->shop_name,
            "user_name"=>$user_name,
            "order_no"=>$order_no,
            "order_money"=>$order_money,
            "goods_money"=>$goods_money,
            "shopname"=>$this->shop_name,
            "username"=>$user_name,
            "orderno"=>$order_no,
            "ordermoney"=>$order_money,
            "goodsmoney"=>$goods_money
        );
        #短信发送
        if(!empty($mobile) && $this->mobile_is_open==1){
            $template_obj=$this->getTemplateDetail($shop_id, "pay_success", "sms");
            if(!empty($template_obj) && $template_obj["is_enable"]==1){
                $this->createNoticeSmsRecords($template_obj, $shop_id, $buyer_id, $mobile, $sms_params, "订单付款成功通知", 3);
            }
        }
        #邮件发送
        $user_model=new UserModel();
        $user_obj=$user_model->get($buyer_id);
        if(!empty($user_obj)){
            $email=$user_obj["user_email"];
            if(!empty($email) && $this->email_is_open==1){
                $template_obj=$this->getTemplateDetail($shop_id, "pay_success", "email");
                if(!empty($template_obj) && $template_obj["is_enable"]==1){
                    $content=$template_obj["template_content"];
                    $content=str_replace("{商场名称}", $this->shop_name, $content);
                    $content=str_replace("{用户名称}", $user_name, $content);
                    $content=str_replace("{主订单号}", $order_no, $content);
                    $content=str_replace("{订单金额}", $order_money, $content);
                    $content=str_replace("{商品金额}", $goods_money, $content);
                    $send_title=$template_obj["template_title"];
                    $send_title=str_replace("{商场名称}", $this->shop_name, $send_title);
                    $send_title=str_replace("{用户名称}", $user_name, $send_title);
                    $send_title=str_replace("{主订单号}", $order_no, $send_title);
                    $send_title=str_replace("{订单金额}", $order_money, $send_title);
                    $send_title=str_replace("{商品金额}", $goods_money, $send_title);
                    $this->createNoticeEmailRecords($shop_id, $buyer_id, $email, $send_title, $content, 3);
                }
            }
        }
    }
    /**
     * 订单创建成功
     * @param string $params
     */
    public function orderCreate($params=null){
        #查询系统配置信息
        $this->getShopNotifyInfo(0);
        $order_id=$params["order_id"];
        $order_model=new NsOrderModel();
        $order_obj=$order_model->get($order_id);
        $shop_id=$order_obj["shop_id"];
        $buyer_id=$order_obj["buyer_id"];
        $user_name=$order_obj["receiver_name"];
        $order_no=$order_obj["out_trade_no"];
        $order_money=$order_obj["order_money"];
        $mobile=$order_obj["receiver_mobile"];
        $goods_money=$order_obj["goods_money"];
        $sms_params=array(
            "shop_name"=>$this->shop_name,
            "user_name"=>$user_name,
            "order_no"=>$order_no,
            "order_money"=>$order_money,
            "goods_money"=>$goods_money,
            "shopname"=>$this->shop_name,
            "username"=>$user_name,
            "orderno"=>$order_no,
            "ordermoney"=>$order_money,
            "goodsmoney"=>$goods_money
        );
        #短信发送
        if(!empty($mobile) && $this->mobile_is_open==1){
            $template_obj=$this->getTemplateDetail($shop_id, "create_order", "sms");
            if(!empty($template_obj) && $template_obj["is_enable"]==1){
                $this->createNoticeSmsRecords($template_obj, $shop_id, $buyer_id, $mobile, $sms_params, "订单创建成功通知", 4);
            }
        }
            // 邮件发送
        $user_model = new UserModel();
        $user_obj = $user_model->get($buyer_id);
        if (! empty($user_obj)) {
            $email = $user_obj["user_email"];
            if (! empty($email) && $this->email_is_open == 1) {
                $template_obj = $this->getTemplateDetail($shop_id, "create_order", "email");
                if (! empty($template_obj) && $template_obj["is_enable"] == 1) {
                    $content = $template_obj["template_content"];
                    $content = str_replace("{商场名称}", $this->shop_name, $content);
                    $content = str_replace("{用户名称}", $user_name, $content);
                    $content = str_replace("{主订单号}", $order_no, $content);
                    $content = str_replace("{订单金额}", $order_money, $content);
                    $content = str_replace("{商品金额}", $goods_money, $content);
                    $send_title=$template_obj["template_title"];
                    $send_title = str_replace("{商场名称}", $this->shop_name, $send_title);
                    $send_title = str_replace("{用户名称}", $user_name, $send_title);
                    $send_title = str_replace("{主订单号}", $order_no, $send_title);
                    $send_title = str_replace("{订单金额}", $order_money, $send_title);
                    $send_title = str_replace("{商品金额}", $goods_money, $send_title);
                    $this->createNoticeEmailRecords($shop_id, $buyer_id, $email, $send_title, $content, 4);
                }
            }
        }
    }
    /**
     * 找回密码
     * @param string $params
     * @return multitype:number string
     */
    public function forgotPassword($params=null){
        $send_type=$params["send_type"];
        $send_param=$params["send_param"];
        $shop_id=$params["shop_id"];
        $this->getShopNotifyInfo($shop_id);
        $rand = rand(100000,999999);
        $template_obj=$this->getTemplateDetail($shop_id, "forgot_password", $send_type);
        if($send_type=="email"){
            #邮箱验证
            if($this->email_is_open==1){
                if(!empty($template_obj) && $template_obj["is_enable"]==1){
                    #发送
                    $content=$template_obj["template_content"];
                    $content=str_replace("{验证码}", $rand, $content);
                    $result=emailSend($this->email_host, $this->email_id, $this->email_pass, $this->email_port, $this->email_is_security, $this->email_addr, $send_param, $template_obj["template_title"], $content, $this->shop_name);
                    $this->result["param"]=$rand;
                    if($result){
                        $this->result["code"]=0;
                        $this->result["message"]="发送成功!";
                    }else{
                        $this->result["code"]=-1;
                        $this->result["message"]="发送失败!";
                    }
                }else{
                    $this->result["code"]=-1;
                    $this->result["message"]="商家没有设置找回密码的模板!";
                }
            }else{
                $this->result["code"]=-1;
                $this->result["message"]="商家没开启邮箱验证!";
            }
            $send_result = $this->result["code"] < 0 ? -1 : 1;
            
            $this->createVerificationCodeRecords($template_obj, $shop_id, 0, 2, $send_param, 11, "找回密码邮箱验证码", "验证码", $this->result["message"], $send_result);
			
        }else{
            #短信验证
            if($this->mobile_is_open==1){
                if(!empty($template_obj) && $template_obj["is_enable"]==1){
                    #发送
                    $sms_params=array(
                    "number"=>$rand.""
                        );
                        $result=aliSmsSend($this->appKey, $this->secretKey,
                            $template_obj["sign_name"], json_encode($sms_params), $send_param, $template_obj["template_title"], $this->ali_use_type);
                        $result=$this->dealAliSmsResult($result);
                        $this->result["code"]=$result["code"];
                        $this->result["message"]=$result["message"];
                        $this->result["param"]=$rand;
                        
                }else{
                    $this->result["code"]=-1;
                    $this->result["message"]="商家没有设置找回密码的短信模板!";
                }
            }else{
                $this->result["code"]=-1;
                $this->result["message"]="商家没开启短信验证!";
            }
            $send_result = $this->result["code"] < 0 ? -1 : 1;
            $this->createVerificationCodeRecords($template_obj, $shop_id, 0, 1, $send_param, 12, "找回密码短信验证码", "验证码", $this->result["message"], $send_result);
        }       
        return $this->result;       
    }
    /**
     * 用户绑定手机号
     * @param string $params
     */
    public function bindMobile($params=null){
        $rand = rand(100000,999999);
        $mobile=$params["mobile"];
        $shop_id=$params["shop_id"];
        $user_id=$params["user_id"];
        #查询系统配置信息
        $this->getShopNotifyInfo($shop_id);
        if(!empty($mobile) && $this->mobile_is_open==1){
            $template_obj=$this->getTemplateDetail($shop_id, "bind_mobile", "sms");
            if(!empty($template_obj) && $template_obj["is_enable"]==1){
                $sms_params=array(
                    "number"=>$rand."",
                );
                $this->result["param"]=$rand;
                if(!empty($this->appKey) && !empty($this->secretKey) && !empty($template_obj["sign_name"]) && !empty($template_obj["template_title"])){
                    $result=aliSmsSend($this->appKey, $this->secretKey,
                                    $template_obj["sign_name"], json_encode($sms_params), $mobile, $template_obj["template_title"], $this->ali_use_type);
                    $result=$this->dealAliSmsResult($result);
                    $this->result["code"]=$result["code"];
                    $this->result["message"]=$result["message"];
                    $this->result["param"]=$rand;
                }else{
                    $this->result["code"]=-1;
                    $this->result["message"]="短信配置信息有误!";
                }
            }else{
                $this->result["code"]=-1;
                $this->result["message"]="短信通知模板有误!";
            }
        }else{
            $this->result["code"]=-1;
            $this->result["message"]="店家没有开启短信验证";
        }
        $send_result = $this->result["code"] < 0 ? -1 : 1;
        $this->createVerificationCodeRecords($template_obj, $shop_id, 0, 1, $mobile, 13, "绑定手机号短信验证码", "验证码", $this->result["message"], $send_result);
        return $this->result;
    }
    /**
     * 用户绑定邮箱
     * @param string $params
     */
    public function bindEmail($params=null){
        $rand = rand(100000,999999);
        $email=$params["email"];
        $shop_id=$params["shop_id"];
        $user_id=$params["user_id"];
        #查询系统配置信息
        $this->getShopNotifyInfo($shop_id);
        if(!empty($email) && $this->email_is_open==1){
            $template_obj=$this->getTemplateDetail($shop_id, "bind_email", "email");
            if(!empty($template_obj) && $template_obj["is_enable"]==1){
                $content=$template_obj["template_content"];
                $content=str_replace("{验证码}", $rand, $content);
                $this->result["param"]=$rand;
              
                if(!empty($this->email_host) && !empty($this->email_id) && !empty($this->email_pass) && !empty($this->email_addr)){
                	//$result=emailSend($this->email_host, $this->email_id, $this->email_pass, $this->email_port, $this->email_is_security, $this->email_addr, $email, $template_obj["template_title"], $content, $this->shop_name);
                	$result=emailSend($this->email_host, $this->email_id, $this->email_pass, $this->email_port, $this->email_is_security, $this->email_addr, $email, $template_obj["template_title"], $content, $this->shop_name);
                    if($result){
                        $this->result["code"]=0;
                        $this->result["message"]="发送成功!";
                    }else{
                        $this->result["code"]=-1;
                        $this->result["message"]="发送失败!";
                    }
                }else{
                    $this->result["code"]=-1;
                    $this->result["message"]="邮箱配置信息有误!";
                }
            }else{
                $this->result["code"]=-1;
                $this->result["message"]="邮箱通知模板有误!";
            }
        }else{
            $this->result["code"]=-1;
            $this->result["message"]="店家没有开启邮箱验证";
        }
        $send_result = $this->result["code"] < 0 ? -1 : 1;
        $this->createVerificationCodeRecords($template_obj, $shop_id, 0, 2, $email, 14, "绑定邮箱邮箱验证码", "验证码", $this->result["message"], $send_result);
        return $this->result;
    }
    
    /**
     * 订单提醒
     * @param string $params
     */
    public function orderRemindBusiness($params=null){
        #查询系统配置信息
        $this->getShopNotifyInfo(0);
        $out_trade_no = $params["out_trade_no"];//订单号
        $shop_id = $params['shop_id'];
        $result="";
        $user_name="";
        if(!empty($out_trade_no)){
            //获取订单详情
            $ns_order = new NsOrderModel();
            $order_detial = $ns_order->getInfo(["out_trade_no"=>$out_trade_no]);
            //邮箱提醒
            $template_email_obj = $this->getTemplateDetail($shop_id, "order_remind", "email", "business");
            $email_array = explode(',', $template_email_obj['notification_mode']);//获取要提醒的人
            if(!empty($email_array[0]) && $template_email_obj["is_enable"] == 1){
                $content = $template_email_obj["template_content"];
                $content = str_replace("{商场名称}", $this->shop_name, $content);
                $content = str_replace("{用户名称}", $order_detial['user_name'], $content);
                $content = str_replace("{主订单号}", $order_detial['order_no'], $content);
                $content = str_replace("{订单金额}", $order_detial['order_money'], $content);
                $send_title=$template_email_obj["template_title"];
                $send_title = str_replace("{商场名称}", $this->shop_name, $send_title);
                $send_title = str_replace("{用户名称}", $order_detial['user_name'], $send_title);
                $send_title = str_replace("{主订单号}", $order_detial['order_no'], $send_title);
                $send_title = str_replace("{订单金额}", $order_detial['order_money'], $send_title);
                foreach ($email_array as $v){
                    $this->createNoticeEmailRecords($shop_id, 0, $v, $send_title, $content, 7);
                }
            }
            //短信提醒
            $template_sms_obj = $this->getTemplateDetail($shop_id, "order_remind", "sms", "business");
            $mobile_array = explode(',', $template_sms_obj['notification_mode']);//获取要提醒的人
            if(!empty($mobile_array[0]) && $template_sms_obj["is_enable"] == 1){
                $sms_params=array(
                    "shop_name"=>$this->shop_name,
                    "user_name"=>$order_detial['user_name'],
                    "order_no"=>$order_detial['order_no'],
                    "order_money"=>$order_detial['order_money'],
                    "shopname"=>$this->shop_name,
                    "username"=>$order_detial['user_name'],
                    "orderno"=>$order_detial['order_no'],
                    "ordermoney"=>$order_detial['order_money']
                );
                foreach ($mobile_array as $v){
                    $this->createNoticeSmsRecords($template_sms_obj, $shop_id, 0, $v, $sms_params, "订单提醒-商家通知", 7);
                }
            }
        }
    }
    
    /**
     * 订单退款提醒
     * @param string $params
     */
    public function orderRefoundBusiness($params=null){
        #查询系统配置信息refund_order
        $this->getShopNotifyInfo(0);
        $order_id = $params["order_id"];//订单id
        $shop_id = $params['shop_id'];
        $result="";
        $user_name="";
        if(!empty($order_id)){
            //获取订单详情
            $ns_order = new NsOrderModel();
            $order_detial = $ns_order->getInfo(["order_id"=>$order_id]);
            //邮箱提醒
            $template_email_obj = $this->getTemplateDetail($shop_id, "refund_order", "email", "business");
            $email_array = explode(',', $template_email_obj['notification_mode']);//获取要提醒的人
            if(!empty($email_array[0]) && $template_email_obj["is_enable"] == 1){
                $content = $template_email_obj["template_content"];
                $content = str_replace("{商场名称}", $this->shop_name, $content);
                $content = str_replace("{用户名称}", $order_detial['user_name'], $content);
                $content = str_replace("{主订单号}", $order_detial['order_no'], $content);
                $content = str_replace("{订单金额}", $order_detial['order_money'], $content);
                $send_title=$template_email_obj["template_title"];
                $send_title = str_replace("{商场名称}", $this->shop_name, $send_title);
                $send_title = str_replace("{用户名称}", $order_detial['user_name'], $send_title);
                $send_title = str_replace("{主订单号}", $order_detial['order_no'], $send_title);
                $send_title = str_replace("{订单金额}", $order_detial['order_money'], $send_title);
                foreach ($email_array as $v){
                    $this->createNoticeEmailRecords($shop_id, 0, $v, $send_title, $content, 6);
                }
            }
            //短信提醒
            $template_sms_obj = $this->getTemplateDetail($shop_id, "refund_order", "sms", "business");
            $mobile_array = explode(',', $template_sms_obj['notification_mode']);//获取要提醒的人
            if(!empty($mobile_array[0]) && $template_sms_obj["is_enable"] == 1){
                $sms_params=array(
                    "shop_name"=>$this->shop_name,
                    "user_name"=>$order_detial['user_name'],
                    "order_no"=>$order_detial['order_no'],
                    "order_money"=>$order_detial['order_money'],
                    "shopname"=>$this->shop_name,
                    "username"=>$order_detial['user_name'],
                    "orderno"=>$order_detial['order_no'],
                    "ordermoney"=>$order_detial['order_money']
                );
                foreach ($mobile_array as $v){
                    $this->createNoticeSmsRecords($template_sms_obj, $shop_id, 0, $v, $sms_params, "订单退货提醒-商家通知", 6);
                }
            }
        }
    }
    
    /**
     * 用户充值余额商家提醒
     */
    public function rechargeSuccessBusiness($params=null){
        #查询系统配置信息
        $this->getShopNotifyInfo(0);

        $out_trade_no = $params["out_trade_no"];//订单号
        $shop_id = $params['shop_id'];
        $user = new UserModel();
        $user_name = $user->getInfo(["uid"=>$params['uid']],"nick_name")["nick_name"];
        $result="";
        if(!empty($out_trade_no)){
            //获取支付详情
            $pay = new NsOrderPaymentModel();
            $order_payment = $pay->getInfo(["out_trade_no"=>$out_trade_no]);
            //邮箱提醒
            $template_email_obj = $this->getTemplateDetail($shop_id, "recharge_success", "email", "business");
            $email_array = explode(',', $template_email_obj['notification_mode']);//获取要提醒的人
            if(!empty($email_array[0]) && $template_email_obj["is_enable"] == 1){
                $content = $template_email_obj["template_content"];
                $content = str_replace("{商场名称}", $this->shop_name, $content);
                $content = str_replace("{用户名称}", $user_name, $content);
                $content = str_replace("{充值金额}", $order_payment['pay_money'], $content);
                $send_title=$template_email_obj["template_title"];
                $send_title = str_replace("{商场名称}", $this->shop_name, $send_title);
                $send_title = str_replace("{用户名称}", $user_name, $send_title);
                $send_title = str_replace("{充值金额}", $order_payment['pay_money'], $send_title);
                foreach ($email_array as $v){
                    $this->createNoticeEmailRecords($shop_id, 0, $v, $send_title, $content, 8);
                }
            }
            //短信提醒
            $template_sms_obj = $this->getTemplateDetail($shop_id, "recharge_success", "sms", "business");
            $mobile_array = explode(',', $template_sms_obj['notification_mode']);//获取要提醒的人
            if(!empty($mobile_array[0]) && $template_sms_obj["is_enable"] == 1){
                $sms_params=array(
                    "shop_name"=>$this->shop_name,
                    "user_name"=>$user_name,
                    "recharge_money"=>$order_payment['pay_money'],
                    "shopname"=>$this->shop_name,
                    "username"=>$user_name,
                    "rechargemoney"=>$order_payment['pay_money']
                );
                foreach ($mobile_array as $v){
                    $this->createNoticeSmsRecords($template_sms_obj, $shop_id, 0, $v, $sms_params, "余额充值-商家通知", 8);
                }
            }
        }
    }
    
    /**
     * 用户充值余额用户提醒
     */
    public function rechargeSuccessUser($params=null){
        #查询系统配置信息
        $this->getShopNotifyInfo(0);
        $out_trade_no = $params["out_trade_no"];//订单号
        $shop_id = $params['shop_id'];
        $user = new UserModel();
        $user_info = $user->getInfo(["uid"=>$params['uid']],"*");
        $user_name = $user_info["nick_name"];
        $user_tel = $user_info["user_tel"];
        $user_email = $user_info["user_email"];
        $result="";
        if(!empty($out_trade_no)){
            //获取支付详情
            $pay = new NsOrderPaymentModel();
            $order_payment = $pay->getInfo(["out_trade_no"=>$out_trade_no]);
            //邮箱提醒
            $template_email_obj = $this->getTemplateDetail($shop_id, "recharge_success", "email", "user");
            $email_array = explode(',', $template_email_obj['notification_mode']);//获取要提醒的人
            if(!empty($email_array) && $template_email_obj["is_enable"] == 1){
                $content = $template_email_obj["template_content"];
                $content = str_replace("{商场名称}", $this->shop_name, $content);
                $content = str_replace("{用户名称}", $user_name, $content);
                $content = str_replace("{充值金额}", $order_payment['pay_money'], $content);
                $send_title=$template_email_obj["template_title"];
                $send_title = str_replace("{商场名称}", $this->shop_name, $send_title);
                $send_title = str_replace("{用户名称}", $user_name, $send_title);
                $send_title = str_replace("{充值金额}", $order_payment['pay_money'], $send_title);
                $this->createNoticeEmailRecords($shop_id, $user_info["uid"], $user_email, $send_title, $content, 1);
            }
            //短信提醒
            $template_sms_obj = $this->getTemplateDetail($shop_id, "recharge_success", "sms", "user");
            $mobile_array = explode(',', $template_sms_obj['notification_mode']);//获取要提醒的人
            if(!empty($mobile_array) && $template_sms_obj["is_enable"] == 1){
                $sms_params=array(
                    "shop_name"=>$this->shop_name,
                    "user_name"=>$user_name,
                    "recharge_money"=>$order_payment['pay_money'],
                    "shopname"=>$this->shop_name,
                    "username"=>$user_name,
                    "rechargemoney"=>$order_payment['pay_money']
                );
                $this->createNoticeSmsRecords($template_sms_obj, $shop_id, $user_info["uid"], $user_tel, $sms_params, "用户余额充值", 1);
            }
        }
    }
    
    /**
     * 开团通知
     * @param array $params
     */
    public function openGroupNoticeUser($params = []){
        // 获取系统配置
        $this->getShopNotifyInfo(0);
        $shop_id = 0;
        $pintuan_info = $this->getPintuanInfo($params['pintuan_group_id']);
        if(!empty($pintuan_info)){
            $user_info = $this->getUserInfo($pintuan_info['group_uid']);
        }
        if(!empty($pintuan_info) && !empty($user_info)){
            $pintuan_info['surplus_num'] -= 1;
            // 邮件提醒
            $template_email_obj = $this->getTemplateDetail($shop_id, "open_the_group", "email", "user");
            if(!empty($template_email_obj) && $template_email_obj["is_enable"] == 1 && !empty($user_info['user_email'])){
                // 内容
                $content = $template_email_obj["template_content"];
                $content = str_replace("{商场名称}", $this->shop_name, $content);
                $content = str_replace("{用户名称}", $pintuan_info['group_name'], $content);
                $content = str_replace("{商品名称}", $pintuan_info['goods_name'], $content);
                $content = str_replace("{主订单号}", $params['order_no'], $content);
                $content = str_replace("{拼团价}", $pintuan_info['tuangou_money'], $content);
                $content = str_replace("{剩余人数}", $pintuan_info['surplus_num'], $content);
                $content = str_replace("{团购人数}", $pintuan_info['tuangou_num'], $content);
                $content = str_replace("{发起时间}", date('Y-m-d H:i:s', $pintuan_info['create_time']), $content);
                $content = str_replace("{剩余时间}", $pintuan_info['surplus_time'], $content);
                $content = str_replace("{拼团类型}", $pintuan_info['tuangou_type_name'], $content);
                // 标题
                $send_title=$template_email_obj["template_title"];
                $send_title = str_replace("{商场名称}", $this->shop_name, $send_title);
                $send_title = str_replace("{用户名称}", $pintuan_info['group_name'], $send_title);
                $send_title = str_replace("{商品名称}", $pintuan_info['goods_name'], $send_title);
                $send_title = str_replace("{主订单号}", $params['order_no'], $send_title);
                $send_title = str_replace("{拼团价}", $pintuan_info['tuangou_money'], $send_title);
                $send_title = str_replace("{剩余人数}", $pintuan_info['surplus_num'], $send_title);
                $send_title = str_replace("{团购人数}", $pintuan_info['tuangou_num'], $send_title);
                $send_title = str_replace("{发起时间}", date('Y-m-d H:i:s', $pintuan_info['create_time']), $send_title);
                $send_title = str_replace("{剩余时间}", $pintuan_info['surplus_time'], $send_title);
                $send_title = str_replace("{拼团类型}", $pintuan_info['tuangou_type_name'], $send_title);
                // 添加到发送记录表
                $this->createNoticeEmailRecords($shop_id, $pintuan_info['group_uid'], $user_info['user_email'], $send_title, $content, 0);
            }
            
            // 短信提醒
            $template_sms_obj = $this->getTemplateDetail($shop_id, "open_the_group", "sms", "user");
            if(!empty($template_sms_obj) && $template_sms_obj["is_enable"] == 1 && !empty($user_info['user_tel'])){
                $sms_params=array(
                    "shop_name" => $this->shop_name,
                    "username" => $pintuan_info['group_name'],
                    "shopname" => $this->shop_name,
                    "goodsname" => $pintuan_info['goods_name'],
                    "orderno" => $params['order_no'],
                    "pintuanmoney" => $pintuan_info['tuangou_money'],
                    "surplusnumber" => $pintuan_info['surplus_num'],
                    "totalnumber" => $pintuan_info['tuangou_num'],
                    "launchtime" => date('Y-m-d H:i:s', $pintuan_info['create_time']),
                    "surplustime" => $pintuan_info['surplus_time'],
                    "groupbookingtype" => $pintuan_info['tuangou_type_name']
                );
                $this->createNoticeSmsRecords($template_sms_obj, $shop_id, $pintuan_info['group_uid'], $user_info['user_tel'], $sms_params, "用户拼团发起通知", 0);
            }
        }
    }
    
    /**
     * 用户参团通知
     * @param array $params
     */
    public function addGroupNoticeUser($params = []){
        // 获取系统配置
        $this->getShopNotifyInfo(0);
        $shop_id = 0;
        $pintuan_info = $this->getPintuanInfo($params['pintuan_group_id']);
        if(!empty($pintuan_info)){
            $user_info = $this->getUserInfo($params['uid']);
        }
        if(!empty($pintuan_info) && !empty($user_info)){
            $pintuan_info['surplus_num'] -= 1;
            // 邮件提醒
            $template_email_obj = $this->getTemplateDetail($shop_id, "add_the_group", "email", "user");
            if(!empty($template_email_obj) && $template_email_obj["is_enable"] == 1 && !empty($user_info['user_email'])){
                // 内容
                $content = $template_email_obj["template_content"];
                $content = str_replace("{商场名称}", $this->shop_name, $content);
                $content = str_replace("{用户名称}", $pintuan_info['group_name'], $content);
                $content = str_replace("{商品名称}", $pintuan_info['goods_name'], $content);
                $content = str_replace("{主订单号}", $params['order_no'], $content);
                $content = str_replace("{拼团价}", $pintuan_info['tuangou_money'], $content);
                $content = str_replace("{剩余人数}", $pintuan_info['surplus_num'], $content);
                $content = str_replace("{团购人数}", $pintuan_info['tuangou_num'], $content);
                $content = str_replace("{发起时间}", date('Y-m-d H:i:s', $pintuan_info['create_time']), $content);
                $content = str_replace("{剩余时间}", $pintuan_info['surplus_time'], $content);
                $content = str_replace("{拼团类型}", $pintuan_info['tuangou_type_name'], $content);
                // 标题
                $send_title=$template_email_obj["template_title"];
                $send_title = str_replace("{商场名称}", $this->shop_name, $send_title);
                $send_title = str_replace("{用户名称}", $pintuan_info['group_name'], $send_title);
                $send_title = str_replace("{商品名称}", $pintuan_info['goods_name'], $send_title);
                $send_title = str_replace("{主订单号}", $params['order_no'], $send_title);
                $send_title = str_replace("{拼团价}", $pintuan_info['tuangou_money'], $send_title);
                $send_title = str_replace("{剩余人数}", $pintuan_info['surplus_num'], $send_title);
                $send_title = str_replace("{团购人数}", $pintuan_info['tuangou_num'], $send_title);
                $send_title = str_replace("{发起时间}", date('Y-m-d H:i:s', $pintuan_info['create_time']), $send_title);
                $send_title = str_replace("{剩余时间}", $pintuan_info['surplus_time'], $send_title);
                $send_title = str_replace("{拼团类型}", $pintuan_info['tuangou_type_name'], $send_title);
                // 添加到发送记录表
                $this->createNoticeEmailRecords($shop_id, $pintuan_info['group_uid'], $user_info['user_email'], $send_title, $content, 0);
            }
        
            // 短信提醒
            $template_sms_obj = $this->getTemplateDetail($shop_id, "add_the_group", "sms", "user");
            if(!empty($template_sms_obj) && $template_sms_obj["is_enable"] == 1 && !empty($user_info['user_tel'])){
                $sms_params=array(
                    "shop_name" => $this->shop_name,
                    "username" => $pintuan_info['group_name'],
                    "shopname" => $this->shop_name,
                    "goodsname" => $pintuan_info['goods_name'],
                    "orderno" => $params['order_no'],
                    "pintuanmoney" => $pintuan_info['tuangou_money'],
                    "surplusnumber" => $pintuan_info['surplus_num'],
                    "totalnumber" => $pintuan_info['tuangou_num'],
                    "launchtime" => date('Y-m-d H:i:s', $pintuan_info['create_time']),
                    "surplustime" => $pintuan_info['surplus_time'],
                    "groupbookingtype" => $pintuan_info['tuangou_type_name']
                );
                $this->createNoticeSmsRecords($template_sms_obj, $shop_id, $pintuan_info['group_uid'], $user_info['user_tel'], $sms_params, "用户参与拼团通知", 0);
            }
        }
    }  
    
    /**
     * 拼团成功或失败通知（用户）
     * @param array $params
     */
    public function groupBookingSuccessOrFailUser($params = []){
        // 获取系统配置
        $this->getShopNotifyInfo(0);
        $shop_id = 0;
        $pintuan_info = $this->getPintuanInfo($params['pintuan_group_id']);
        $user_list = $this->getPintuanUserList($params['pintuan_group_id']);
        if(!empty($pintuan_info) && !empty($user_list)){
            // 邮件提醒
            if($params['type'] == "success"){
                $template_email_obj = $this->getTemplateDetail($shop_id, "group_booking_success", "email", "user");                
            }elseif($params['type'] == "fail"){
                $template_email_obj = $this->getTemplateDetail($shop_id, "group_booking_fail", "email", "user");
            }
            
            if(!empty($template_email_obj) && $template_email_obj["is_enable"] == 1){
                // 内容
                $content = $template_email_obj["template_content"];
                $content = str_replace("{商场名称}", $this->shop_name, $content);
                $content = str_replace("{商品名称}", $pintuan_info['goods_name'], $content);
                $content = str_replace("{拼团价}", $pintuan_info['tuangou_money'], $content);
                $content = str_replace("{团购人数}", $pintuan_info['tuangou_num'], $content);
                $content = str_replace("{发起时间}", date('Y-m-d H:i:s', $pintuan_info['create_time']), $content);
                $content = str_replace("{拼团类型}", $pintuan_info['tuangou_type_name'], $content);
                // 标题
                $send_title=$template_email_obj["template_title"];
                $send_title = str_replace("{商场名称}", $this->shop_name, $send_title);
                $send_title = str_replace("{商品名称}", $pintuan_info['goods_name'], $send_title);
                $send_title = str_replace("{拼团价}", $pintuan_info['tuangou_money'], $send_title);
                $send_title = str_replace("{团购人数}", $pintuan_info['tuangou_num'], $send_title);
                $send_title = str_replace("{发起时间}", date('Y-m-d H:i:s', $pintuan_info['create_time']), $send_title);
                $send_title = str_replace("{拼团类型}", $pintuan_info['tuangou_type_name'], $send_title);
                
                foreach ($user_list as $item){
                    $content = str_replace("{用户名称}", $item['nick_name'], $content);
                    $content = str_replace("{主订单号}", $item['order_no'], $content);
                    $send_title = str_replace("{用户名称}", $item['nick_name'], $send_title);
                    $send_title = str_replace("{主订单号}", $item['order_no'], $send_title);
                    // 添加到发送记录表
                    $this->createNoticeEmailRecords($shop_id, $item['buyer_id'], $item['user_email'], $send_title, $content, 0);
                }
            }
            
            // 短信提醒
            if($params['type'] == "success"){
                $template_sms_obj = $this->getTemplateDetail($shop_id, "group_booking_success", "sms", "user");
            }elseif($params['type'] == "fail"){
                $template_sms_obj = $this->getTemplateDetail($shop_id, "group_booking_fail", "sms", "user");
            }
            if(!empty($template_sms_obj) && $template_sms_obj["is_enable"] == 1){
                $sms_params=array(
                    "shop_name" => $this->shop_name,
                    "shopname" => $this->shop_name,
                    "goodsname" => $pintuan_info['goods_name'],
                    "pintuanmoney" => $pintuan_info['tuangou_money'],
                    "totalnumber" => $pintuan_info['tuangou_num'],
                    "launchtime" => date('Y-m-d H:i:s', $pintuan_info['create_time']),
                    "groupbookingtype" => $pintuan_info['tuangou_type_name']
                );
                foreach ($user_list as $item){
                    if($params['type'] == "success"){
                        $sms_params['username'] = $item['nick_name'];
                        $sms_params['orderno'] = $item['order_no'];
                        $this->createNoticeSmsRecords($template_sms_obj, $shop_id, $item['buyer_id'], $item['user_tel'], $sms_params, "用户拼团成功通知", 0);
                    }elseif($params['type'] == "fail"){
                        $this->createNoticeSmsRecords($template_sms_obj, $shop_id, $item['buyer_id'], $item['user_tel'], $sms_params, "用户拼团失败通知", 0);
                    }
                }
            }
        }
    }
    
    /**
     * 拼团发起通知（商家）
     * @param array $params
     */
    public function openGroupNoticeBusiness($params = []){
        // 获取系统配置
        $this->getShopNotifyInfo(0);
        $shop_id = 0;
        $pintuan_info = $this->getPintuanInfo($params['pintuan_group_id']);
        if(!empty($pintuan_info)){
            $user_info = $this->getUserInfo($pintuan_info['group_uid']);
        }
        if(!empty($pintuan_info) && !empty($user_info)){
            $pintuan_info['surplus_num'] -= 1;
            //邮箱提醒
            $template_email_obj = $this->getTemplateDetail($shop_id, "open_the_group_business", "email", "business");
            $email_array = explode(',', $template_email_obj['notification_mode']);//获取要提醒的人
            if(!empty($template_email_obj) && $template_email_obj["is_enable"] == 1 && !empty($email_array[0])){
                // 内容
                $content = $template_email_obj["template_content"];
                $content = str_replace("{商场名称}", $this->shop_name, $content);
                $content = str_replace("{用户名称}", $pintuan_info['group_name'], $content);
                $content = str_replace("{商品名称}", $pintuan_info['goods_name'], $content);
                $content = str_replace("{主订单号}", $params['order_no'], $content);
                $content = str_replace("{拼团价}", $pintuan_info['tuangou_money'], $content);
                $content = str_replace("{剩余人数}", $pintuan_info['surplus_num'], $content);
                $content = str_replace("{团购人数}", $pintuan_info['tuangou_num'], $content);
                $content = str_replace("{发起时间}", date('Y-m-d H:i:s', $pintuan_info['create_time']), $content);
                $content = str_replace("{剩余时间}", $pintuan_info['surplus_time'], $content);
                $content = str_replace("{拼团类型}", $pintuan_info['tuangou_type_name'], $content);
                // 标题
                $send_title=$template_email_obj["template_title"];
                $send_title = str_replace("{商场名称}", $this->shop_name, $send_title);
                $send_title = str_replace("{用户名称}", $pintuan_info['group_name'], $send_title);
                $send_title = str_replace("{商品名称}", $pintuan_info['goods_name'], $send_title);
                $send_title = str_replace("{主订单号}", $params['order_no'], $send_title);
                $send_title = str_replace("{拼团价}", $pintuan_info['tuangou_money'], $send_title);
                $send_title = str_replace("{剩余人数}", $pintuan_info['surplus_num'], $send_title);
                $send_title = str_replace("{团购人数}", $pintuan_info['tuangou_num'], $send_title);
                $send_title = str_replace("{发起时间}", date('Y-m-d H:i:s', $pintuan_info['create_time']), $send_title);
                $send_title = str_replace("{剩余时间}", $pintuan_info['surplus_time'], $send_title);
                $send_title = str_replace("{拼团类型}", $pintuan_info['tuangou_type_name'], $send_title);
                // 添加到发送记录表
                foreach ($email_array as $v){
                    $this->createNoticeEmailRecords($shop_id, $pintuan_info['group_uid'], $v, $send_title, $content, 0);
                }
            }
            
            // 短信提醒
            $template_sms_obj = $this->getTemplateDetail($shop_id, "open_the_group_business", "sms", "business");
            $mobile_array = explode(',', $template_sms_obj['notification_mode']);//获取要提醒的人
            if(!empty($template_sms_obj) && $template_sms_obj["is_enable"] == 1 && !empty($mobile_array[0])){
                $sms_params=array(
                    "shop_name" => $this->shop_name,
                    "username" => $pintuan_info['group_name'],
                    "shopname" => $this->shop_name,
                    "goodsname" => $pintuan_info['goods_name'],
                    "orderno" => $params['order_no'],
                    "pintuanmoney" => $pintuan_info['tuangou_money'],
                    "surplusnumber" => $pintuan_info['surplus_num'],
                    "totalnumber" => $pintuan_info['tuangou_num'],
                    "launchtime" => date('Y-m-d H:i:s', $pintuan_info['create_time']),
                    "surplustime" => $pintuan_info['surplus_time'],
                    "groupbookingtype" => $pintuan_info['tuangou_type_name']
                );
                
                foreach ($mobile_array as $v){
                    $this->createNoticeSmsRecords($template_sms_obj, $shop_id, $pintuan_info['group_uid'], $v, $sms_params, "用户发起拼团商家通知", 0);
                }
            }
        }
    }
    
    /**
     * 拼团成功通知(商家)
     * @param array $params
     */
    public function groupBookingSuccessBusiness($params = []){
        // 获取系统配置
        $this->getShopNotifyInfo(0);
        $shop_id = 0;
        $pintuan_info = $this->getPintuanInfo($params['pintuan_group_id']);
        if(!empty($pintuan_info)){
            //邮箱提醒
            $template_email_obj = $this->getTemplateDetail($shop_id, "group_booking_success_business", "email", "business");
            $email_array = explode(',', $template_email_obj['notification_mode']);//获取要提醒的人
            if(!empty($template_email_obj) && $template_email_obj["is_enable"] == 1 && !empty($email_array[0])){
                // 内容
                $content = $template_email_obj["template_content"];
                $content = str_replace("{商场名称}", $this->shop_name, $content);
                $content = str_replace("{商品名称}", $pintuan_info['goods_name'], $content);
                $content = str_replace("{拼团价}", $pintuan_info['tuangou_money'], $content);
                $content = str_replace("{团购人数}", $pintuan_info['tuangou_num'], $content);
                $content = str_replace("{发起时间}", date('Y-m-d H:i:s', $pintuan_info['create_time']), $content);
                $content = str_replace("{拼团类型}", $pintuan_info['tuangou_type_name'], $content);
                $content = str_replace("{团长名称}", $pintuan_info['group_name'], $content);
                // 标题
                $send_title=$template_email_obj["template_title"];
                $send_title = str_replace("{商场名称}", $this->shop_name, $send_title);
                $send_title = str_replace("{商品名称}", $pintuan_info['goods_name'], $send_title);
                $send_title = str_replace("{拼团价}", $pintuan_info['tuangou_money'], $send_title);
                $send_title = str_replace("{团购人数}", $pintuan_info['tuangou_num'], $send_title);
                $send_title = str_replace("{发起时间}", date('Y-m-d H:i:s', $pintuan_info['create_time']), $send_title);
                $send_title = str_replace("{拼团类型}", $pintuan_info['tuangou_type_name'], $send_title);
                $send_title = str_replace("{团长名称}", $pintuan_info['group_name'], $send_title);
                // 添加到发送记录表
                foreach ($email_array as $v){
                    $this->createNoticeEmailRecords($shop_id, $pintuan_info['group_uid'], $v, $send_title, $content, 0);
                }
            }
            
            // 短信提醒
            $template_sms_obj = $this->getTemplateDetail($shop_id, "group_booking_success_business", "sms", "business");
            $mobile_array = explode(',', $template_sms_obj['notification_mode']);//获取要提醒的人
            if(!empty($template_sms_obj) && $template_sms_obj["is_enable"] == 1 && !empty($mobile_array[0])){
                $sms_params=array(
                    "shop_name" => $this->shop_name,
                    "shopname" => $this->shop_name,
                    "goodsname" => $pintuan_info['goods_name'],
                    "pintuanmoney" => $pintuan_info['tuangou_money'],
                    "surplusnumber" => $pintuan_info['surplus_num'],
                    "totalnumber" => $pintuan_info['tuangou_num'],
                    "launchtime" => date('Y-m-d H:i:s', $pintuan_info['create_time']),
                    "surplustime" => $pintuan_info['surplus_time'],
                    "groupbookingtype" => $pintuan_info['tuangou_type_name'],
                    "headgroup" => $pintuan_info['group_name']
                );
                foreach ($mobile_array as $v){
                    $this->createNoticeSmsRecords($template_sms_obj, $shop_id, $pintuan_info['group_uid'], $v, $sms_params, "拼团成功商家通知", 0);
                }
            }
        }
    }
    
    /**
     * 获取拼团通知所需信息
     * @param unknown $pintuan_group_id
     */
    private function getPintuanInfo($pintuan_group_id){
        $tuangou_group = new NsTuangouGroupModel();
        $tuangou_group_info = $tuangou_group -> getInfo(['group_id' => $pintuan_group_id], 'group_uid,group_name,goods_name,tuangou_money,tuangou_type_name,tuangou_num,real_num,create_time,end_time');
        if(!empty($tuangou_group_info)){
            $tuangou_group_info['surplus_num'] = $tuangou_group_info['tuangou_num'] - $tuangou_group_info['real_num'];
            $day = floor(($tuangou_group_info['end_time'] - time()) / 86400);
            $hours = floor(($tuangou_group_info['end_time'] - time() - $day * 86400) / 3600);
            $tuangou_group_info['surplus_time'] = $day > 0 ? $day.'天' : '';
            $tuangou_group_info['surplus_time'] .= $hours > 0 ? $hours.'小时' : '';
        }
        return $tuangou_group_info;       
    }   
    
    /**
     * 获取用户的手机与邮箱
     * @param unknown $uid
     */
    private function getUserInfo($uid){
        $user = new UserModel();
        $user_info = $user -> getInfo(['uid' => $uid], "user_email,user_tel,nick_name");
        return $user_info;
    }
    
    /**
     * 获取参与拼团的用户列表
     * @param unknown $pintuan_group_id
     */
    private function getPintuanUserList($pintuan_group_id){
        $ns_order = new NsOrderModel();
        $buyer_list = $ns_order -> getQuery([
            'tuangou_group_id' => $pintuan_group_id,
            'order_status' => 1
        ], 'buyer_id,order_no', '');
        if(!empty($buyer_list)){
            $user_model = new UserModel();
            foreach ($buyer_list as $key => $item){
                $user_info = $user_model -> getInfo(['uid' => $item['buyer_id']], "user_email,user_tel,nick_name");
                $buyer_list[$key]['user_email'] = $user_info['user_email'];
                $buyer_list[$key]['user_tel'] = $user_info['user_tel'];
                $buyer_list[$key]['nick_name'] = $user_info['nick_name'];
            }
        }
        return $buyer_list;
    }
    
    /**
     * 砍价发起表（用户）
     * @param array $params
     */
    public function bargainLaunchUser($params = []){
        // 获取系统配置
        $this->getShopNotifyInfo(0);
        $shop_id = 0;
        $bargain_info = $this->getBargainInfo($params['launch_id']);
        if(!empty($bargain_info)){
            $user_info = $this-> getUserInfo($bargain_info['uid']);
        }
        
        if(!empty($bargain_info) && !empty($user_info)){
            // 邮件提醒
            $template_email_obj = $this->getTemplateDetail($shop_id, "bargain_launch", "email", "user");
            if(!empty($template_email_obj) && $template_email_obj["is_enable"] == 1 && !empty($user_info['user_email'])){
                // 内容
                $content = $template_email_obj["template_content"];
                $content = str_replace("{商场名称}", $this->shop_name, $content);
                $content = str_replace("{用户名称}", $user_info['nick_name'], $content);
                $content = str_replace("{商品名称}", $bargain_info['goods_name'], $content);
                $content = str_replace("{剩余时间}", $bargain_info['surplus_time'], $content);
                $content = str_replace("{砍价金额}", $bargain_info['bargain_min_money'], $content);
                // 标题
                $send_title=$template_email_obj["template_title"];
                $send_title = str_replace("{商场名称}", $this->shop_name, $send_title);
                $send_title = str_replace("{用户名称}", $user_info['nick_name'], $send_title);
                $send_title = str_replace("{商品名称}", $bargain_info['goods_name'], $send_title);
                $send_title = str_replace("{剩余时间}", $bargain_info['surplus_time'], $send_title);
                $send_title = str_replace("{砍价金额}", $bargain_info['bargain_min_money'], $send_title);
                // 添加到发送记录表
                $this->createNoticeEmailRecords($shop_id, $bargain_info['uid'], $user_info['user_email'], $send_title, $content, 0);
            }
            
            // 短信提醒
            $template_sms_obj = $this->getTemplateDetail($shop_id, "bargain_launch", "sms", "user");
            if(!empty($template_sms_obj) && $template_sms_obj["is_enable"] == 1 && !empty($user_info['user_tel'])){
                $sms_params=array(
                    "shop_name" => $this->shop_name,
                    "shopname" => $this->shop_name,
                    "username" => $user_info['nick_name'],
                    "goodsname" => $bargain_info['goods_name'],
                    "surplustime" => $bargain_info['surplus_time'],
                    "bargainminmoney" => $bargain_info['bargain_min_money']
                );
                $this->createNoticeSmsRecords($template_sms_obj, $shop_id, $bargain_info['uid'], $user_info['user_tel'], $sms_params, "用户砍价发起通知", 0);
            }
        }
    }

    /**
     * 砍价成功或失败通知（用户）
     * @param array $params
     */
    public function bargainSuccessOrFailUser($params = []){
        $this->getShopNotifyInfo(0);
        $shop_id = 0;
        $bargain_info = $this->getBargainInfo($params['launch_id']);
        if(!empty($bargain_info)){
            $user_info = $this-> getUserInfo($bargain_info['uid']);
        }
        
        if(!empty($bargain_info) && !empty($user_info)){
            // 邮件提醒
            if($params['type'] == 'success'){
                $template_email_obj = $this->getTemplateDetail($shop_id, "bargain_success", "email", "user");
            }elseif($params['type'] == 'fail'){
                $template_email_obj = $this->getTemplateDetail($shop_id, "bargain_fail", "email", "user");
            }
            if(!empty($template_email_obj) && $template_email_obj["is_enable"] == 1 && !empty($user_info['user_email'])){
                // 内容
                $content = $template_email_obj["template_content"];
                $content = str_replace("{商场名称}", $this->shop_name, $content);
                $content = str_replace("{用户名称}", $user_info['nick_name'], $content);
                $content = str_replace("{商品名称}", $bargain_info['goods_name'], $content);
                $content = str_replace("{砍价金额}", $bargain_info['bargain_min_money'], $content);
                // 标题
                $send_title=$template_email_obj["template_title"];
                $send_title = str_replace("{商场名称}", $this->shop_name, $send_title);
                $send_title = str_replace("{用户名称}", $user_info['nick_name'], $send_title);
                $send_title = str_replace("{商品名称}", $bargain_info['goods_name'], $send_title);
                $send_title = str_replace("{砍价金额}", $bargain_info['bargain_min_money'], $send_title);
                
                if($params['type'] == 'success'){
                    $content = str_replace("{主订单号}", $params['order_no'], $content);
                    $send_title = str_replace("{主订单号}", $params['order_no'], $send_title);
                }
                // 添加到发送记录表
                $this->createNoticeEmailRecords($shop_id, $bargain_info['uid'], $user_info['user_email'], $send_title, $content, 0);
            }
        
            // 短信提醒
            if($params['type'] == 'success'){
                $template_sms_obj = $this->getTemplateDetail($shop_id, "bargain_success", "sms", "user");
            }elseif($params['type'] == 'fail'){
                $template_sms_obj = $this->getTemplateDetail($shop_id, "bargain_fail", "sms", "user");
            }
            if(!empty($template_sms_obj) && $template_sms_obj["is_enable"] == 1 && !empty($user_info['user_tel'])){
                $sms_params=array(
                    "shop_name" => $this->shop_name,
                    "shopname" => $this->shop_name,
                    "username" => $user_info['nick_name'],
                    "goodsname" => $bargain_info['goods_name'],
                    "bargainminmoney" => $bargain_info['bargain_min_money']
                );
                if($params['type'] == 'success'){
                    $sms_params['orderno'] = $params['order_no'];
                    $this->createNoticeSmsRecords($template_sms_obj, $shop_id, $bargain_info['uid'], $user_info['user_tel'], $sms_params, "用户砍价成功通知", 0);
                }elseif($params['type'] == 'fail'){
                    $this->createNoticeSmsRecords($template_sms_obj, $shop_id, $bargain_info['uid'], $user_info['user_tel'], $sms_params, "用户砍价失败通知", 0);
                }
            }
        }
    }
    
    /**
     * 砍价发起通知（商家）
     * @param array $params
     */
    public function bargainLaunchBusiness($params = []){
        // 获取系统配置
        $this->getShopNotifyInfo(0);
        $shop_id = 0;
        $bargain_info = $this->getBargainInfo($params['launch_id']);
        if(!empty($bargain_info)){
            $user_info = $this-> getUserInfo($bargain_info['uid']);
        }
        if(!empty($bargain_info) && !empty($user_info)){
            // 邮件提醒
            $template_email_obj = $this->getTemplateDetail($shop_id, "bargain_launch_business", "email", "business");
            $email_array = explode(',', $template_email_obj['notification_mode']);//获取要提醒的人
            if(!empty($template_email_obj) && $template_email_obj["is_enable"] == 1 && !empty($email_array[0])){
                // 内容
                $content = $template_email_obj["template_content"];
                $content = str_replace("{商场名称}", $this->shop_name, $content);
                $content = str_replace("{用户名称}", $user_info['nick_name'], $content);
                $content = str_replace("{商品名称}", $bargain_info['goods_name'], $content);
                $content = str_replace("{剩余时间}", $bargain_info['surplus_time'], $content);
                $content = str_replace("{砍价金额}", $bargain_info['bargain_min_money'], $content);
                // 标题
                $send_title=$template_email_obj["template_title"];
                $send_title = str_replace("{商场名称}", $this->shop_name, $send_title);
                $send_title = str_replace("{用户名称}", $user_info['nick_name'], $send_title);
                $send_title = str_replace("{商品名称}", $bargain_info['goods_name'], $send_title);
                $send_title = str_replace("{剩余时间}", $bargain_info['surplus_time'], $send_title);
                $send_title = str_replace("{砍价金额}", $bargain_info['bargain_min_money'], $send_title);
                // 添加到发送记录表
                foreach ($email_array as $email){
                    $this->createNoticeEmailRecords($shop_id, $bargain_info['uid'], $email, $send_title, $content, 0);                    
                }
            }
    
            // 短信提醒
            $template_sms_obj = $this->getTemplateDetail($shop_id, "bargain_launch_business", "sms", "business");
            $mobile_array = explode(',', $template_sms_obj['notification_mode']);//获取要提醒的人
            if(!empty($template_sms_obj) && $template_sms_obj["is_enable"] == 1 && !empty($mobile_array[0])){
                $sms_params=array(
                    "shop_name" => $this->shop_name,
                    "shopname" => $this->shop_name,
                    "username" => $user_info['nick_name'],
                    "goodsname" => $bargain_info['goods_name'],
                    "surplustime" => $bargain_info['surplus_time'],
                    "bargainminmoney" => $bargain_info['bargain_min_money']
                );
                foreach ($mobile_array as $mobile){
                    $this->createNoticeSmsRecords($template_sms_obj, $shop_id, $bargain_info['uid'], $mobile, $sms_params, "用户砍价发起商家通知", 0);                    
                }
            }
        }
    }
    
    /**
     * 砍价成功通知（商家）
     * @param array $params
     */
    public function bargainSuccessBusiness($params = []){
        // 获取系统配置
        $this->getShopNotifyInfo(0);
        $shop_id = 0;
        $bargain_info = $this->getBargainInfo($params['launch_id']);
        if(!empty($bargain_info)){
            $user_info = $this-> getUserInfo($bargain_info['uid']);
        }
        if(!empty($bargain_info) && !empty($user_info)){
            // 邮件提醒
            $template_email_obj = $this->getTemplateDetail($shop_id, "bargain_success_business", "email", "business");
            $email_array = explode(',', $template_email_obj['notification_mode']);//获取要提醒的人
            if(!empty($template_email_obj) && $template_email_obj["is_enable"] == 1 && !empty($email_array[0])){
                // 内容
                $content = $template_email_obj["template_content"];
                $content = str_replace("{商场名称}", $this->shop_name, $content);
                $content = str_replace("{用户名称}", $user_info['nick_name'], $content);
                $content = str_replace("{商品名称}", $bargain_info['goods_name'], $content);
                $content = str_replace("{砍价金额}", $bargain_info['bargain_min_money'], $content);
                $content = str_replace("{主订单号}", $params['order_no'], $content);
                // 标题
                $send_title=$template_email_obj["template_title"];
                $send_title = str_replace("{商场名称}", $this->shop_name, $send_title);
                $send_title = str_replace("{用户名称}", $user_info['nick_name'], $send_title);
                $send_title = str_replace("{商品名称}", $bargain_info['goods_name'], $send_title);
                $send_title = str_replace("{砍价金额}", $bargain_info['bargain_min_money'], $send_title);
                $send_title = str_replace("{主订单号}", $params['order_no'], $send_title);
                // 添加到发送记录表
                foreach ($email_array as $email){
                    $this->createNoticeEmailRecords($shop_id, $bargain_info['uid'], $email, $send_title, $content, 0);
                }
            }
        
            // 短信提醒
            $template_sms_obj = $this->getTemplateDetail($shop_id, "bargain_success_business", "sms", "business");
            $mobile_array = explode(',', $template_sms_obj['notification_mode']);//获取要提醒的人
            if(!empty($template_sms_obj) && $template_sms_obj["is_enable"] == 1 && !empty($mobile_array[0])){
                $sms_params=array(
                    "shop_name" => $this->shop_name,
                    "shopname" => $this->shop_name,
                    "username" => $user_info['nick_name'],
                    "goodsname" => $bargain_info['goods_name'],
                    "bargainminmoney" => $bargain_info['bargain_min_money'],
                    "orderno" => $params['order_no']
                );
                foreach ($mobile_array as $mobile){
                    $this->createNoticeSmsRecords($template_sms_obj, $shop_id, $bargain_info['uid'], $mobile, $sms_params, "用户砍价成功商家通知", 0);
                }
            }
        }
    }
    
    /**
     * 获取砍价通知所需信息
     * @param unknown $bargain_id
     */
    private function getBargainInfo($launch_id){
        $bargain_model = new NsPromotionBargainLaunchModel();
        $bargain_info = $bargain_model -> getInfo(['launch_id'=>$launch_id], 'bargain_min_money,goods_id,uid,end_time');
        if(!empty($bargain_info)){
            $day = floor(($bargain_info['end_time'] - time()) / 86400);
            $hours = floor(($bargain_info['end_time'] - time() - $day * 86400) / 3600);
            $bargain_info['surplus_time'] = $day > 0 ? $day.'天' : '';
            $bargain_info['surplus_time'] .= $hours > 0 ? $hours.'小时' : '';
            // 商品信息
            $ns_goods = new NsGoodsModel();   
            $goods_info = $ns_goods -> getInfo(['goods_id' => $bargain_info['goods_id']], 'goods_name');
            if(!empty($goods_info)){
                $bargain_info['goods_name'] = $goods_info['goods_name'];
            }else{
                $bargain_info = null;
            }            
        }
        return $bargain_info;
    }
    
    /**
     * 添加短信记录
     * @param unknown $template_obj
     * @param unknown $shop_id
     * @param unknown $buyer_id
     * @param unknown $mobile
     * @param unknown $sms_params
     * @param unknown $title
     * @param unknown $records_type
     */
    private function createNoticeSmsRecords($template_obj, $shop_id, $buyer_id, $mobile, $sms_params, $title, $records_type, $is_send = 0){
        $notice_service=new Notice();
        /* if(mb_strlen($sms_params['goodsname'],'utf8') > 15){
        	$sms_params['goodsname'] = mb_substr($sms_params['goodsname'],0,14,"UTF-8");
        }
        if(mb_strlen($sms_params['good_sname'],'utf8') > 15){
         $sms_params['good_sname'] = mb_substr($sms_params['good_sname'],0,14,"UTF-8");
        } */
        $send_config=array(
            "appkey"=>$this->appKey,
            "secret"=>$this->secretKey,
            "signName"=>$template_obj["sign_name"],
            "template_code"=>$template_obj["template_title"],
            "sms_type"=>$this->ali_use_type
        );
        $notice_service->createNoticeRecords($shop_id, $buyer_id, 1, $mobile, $title, json_encode($sms_params), $records_type, json_encode($send_config), $is_send);
    }
    
    /**
     * 添加邮箱记录
     * @param unknown $shop_id
     * @param unknown $buyer_id
     * @param unknown $mobile
     * @param unknown $send_title
     * @param unknown $content
     * @param unknown $records_type
     */
    private function createNoticeEmailRecords($shop_id, $buyer_id, $mobile, $send_title, $content, $records_type, $is_send = 0){
        $notice_service=new Notice();
        $send_config=array(
            "email_host"=>$this->email_host,
            "email_id"=>$this->email_id,
            "email_pass"=>$this->email_pass,
            "email_port"=>$this->email_port,
            "email_is_security"=>$this->email_is_security,
            "email_addr"=>$this->email_addr,
            "shopName"=>$this->shop_name,
        );
        $notice_service->createNoticeRecords($shop_id, $buyer_id, 2, $mobile, $send_title, $content, $records_type, json_encode($send_config), $is_send);
    }
    
    /**
     * 创建验证码发送记录
     * @param unknown $template_obj
     * @param unknown $shop_id
     * @param unknown $uid
     * @param unknown $send_type
     * @param unknown $send_account
     * @param unknown $records_type
     * @param unknown $notice_title
     * @param unknown $notice_context
     * @param unknown $send_message
     * @param unknown $is_send
     */        
    private function createVerificationCodeRecords($template_obj, $shop_id, $uid, $send_type, $send_account, $records_type, $notice_title, $notice_context, $send_message, $is_send){
        $notice_service=new Notice();
        if($send_type == 1){
            // 短信
            $send_config=array(
                "appkey"=>$this->appKey,
                "secret"=>$this->secretKey,
                "signName"=>$template_obj["sign_name"],
                "template_code"=>$template_obj["template_title"],
                "sms_type"=>$this->ali_use_type
            );
        }elseif($send_type == 2){
            // 邮箱
            $send_config=array(
                "email_host"=>$this->email_host,
                "email_id"=>$this->email_id,
                "email_pass"=>$this->email_pass,
                "email_port"=>$this->email_port,
                "email_is_security"=>$this->email_is_security,
                "email_addr"=>$this->email_addr,
                "shopName"=>$this->shop_name,
            );
        }
        $notice_service -> createVerificationCodeRecords($shop_id, $uid, $send_type, $send_account, json_encode($send_config), $records_type, $notice_title, $notice_context, $send_message, $is_send);
    }
    
} 

?>