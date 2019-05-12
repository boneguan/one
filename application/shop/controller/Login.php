<?php
/**
 * Login.php
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

use data\extend\ThinkOauth as ThinkOauth;
use data\service\Config as Config;
use data\service\Member as Member;
use data\service\WebSite as WebSite;
use data\service\promotion\PromoteRewardRule;
use think\Controller;
use think\Session;
use think\Cookie;
use data\service\Platform;
\think\Loader::addNamespace('data', 'data/');

/**
 * 登录控制器
 * 创建人：李吉
 * 创建时间：2017-02-06 10:59:23
 */
class Login extends Controller
{
    // 验证码配置
    public $login_verify_code;
    // 通知配置
    public $notice;
    public $instance_id;

    public function __construct()
    {
        $default_client = request()->cookie("default_client", "");
        if ($default_client == "shop") {} elseif (request()->isMobile()) {
            $redirect = __URL(__URL__ . "/wap");
            $this->redirect($redirect);
            exit();
        }
        
        // 当切换到PC端时，隐藏右下角返回手机端按钮
        if (! request()->isMobile() && $default_client == "shop") {
            $default_client = "";
        }
        parent::__construct();
        $this->init();
    }

    public function _empty($name)
    {}

    public function init()
    {
        $this->user = new Member();
        $this->web_site = new WebSite();
        $config = new Config();
        $web_info = $this->web_site->getWebSiteInfo();
        
        if ($web_info['web_status'] == 3 && $web_info['wap_status'] == 1) {
            Cookie::delete("default_client");
            $this->redirect(__URL(\think\Config::get('view_replace_str.APP_MAIN')));
        } elseif ($web_info['web_status'] == 2) {
            Cookie::delete("default_client");
            webClose($web_info['close_reason']);
        } elseif (($web_info['web_status'] == 3 && $web_info['wap_status'] == 3) || ($web_info['web_status'] == 3 && $web_info['wap_status'] == 2)) {
            Cookie::delete("default_client");
            webClose($web_info['close_reason']);
        }
        
        $this->assign("platform_shopname", $this->user->getInstanceName());
        $this->assign("title", $web_info['title']);
        
        // 获取当前使用的PC端模板
        $this->instance_id = 0;
        $use_pc_template = $config->getUsePCTemplate($this->instance_id);
        
        if (empty($use_pc_template)) {
            $use_pc_template['value'] = 'blue';
        }
        if (! checkTemplateIsExists("shop", $use_pc_template['value'])) {
            $this->error("模板配置有误，请联系商城管理员");
        }
//         $use_pc_template['value'] = 'default';
        $this->style = "shop/" . $use_pc_template['value'] . "/";
        $this->assign("style", "shop/" . $use_pc_template['value']);
        
        // 是否开启qq跟微信
        $instance_id = 0;
        $qq_info = $config->getQQConfig($instance_id);
        $Wchat_info = $config->getWchatConfig($instance_id);
        $this->assign("qq_info", $qq_info);
        $this->assign("Wchat_info", $Wchat_info);
        // 是否开启验证码
        $this->login_verify_code = $config->getLoginVerifyCodeConfig($instance_id);
        if(!isset($this->login_verify_code['value']['error_num'])){
            $this->login_verify_code['value']['error_num'] = 0;
        }
        $this->assign("login_verify_code", $this->login_verify_code["value"]);
        // 是否开启通知
        $noticeMobile = $config->getNoticeMobileConfig($instance_id);
        $noticeEmail = $config->getNoticeEmailConfig($instance_id);
        $this->notice['noticeEmail'] = $noticeEmail[0]['is_use'];
        $this->notice['noticeMobile'] = $noticeMobile[0]['is_use'];
        $this->assign("notice", $this->notice);
        
        // 配置头部
        $seoconfig = $config->getSeoConfig($instance_id);
        $this->assign("seoconfig", $seoconfig);
    }

    public function index()
    {
        if (request()->isPost()) {
            $username = request()->post('username', '');
            $password = request()->post('password', '');
            if ($this->isNeedVerification()) {
                $vertification = request()->post('vertification', '');
                if (! captcha_check($vertification)) {
                    $retval = [
                        'code' => 0,
                        'message' => "验证码错误"
                    ];
                    return $retval;
                }
            }
            if (trim($username) == "") {
                $retval = [
                    'code' => 0,
                    'message' => "账号不能为空"
                ];
                return $retval;
            }
            if (trim($password) == "") {
                $retval = [
                    'code' => 0,
                    'message' => "密码不能为空"
                ];
                return $retval;
            }
            $user_name = trim($username);
            $password = trim($password);
            if (! empty($user_name) && ! empty($password)) {
                $res = $this->user->login($username, $password);
            }
            if ($res == 1) {
                if (! empty($_SESSION['login_pre_url'])) {
                    $retval = [
                        'code' => 1,
                        'url' => $_SESSION['login_pre_url']
                    ];
                } else {
                    $retval = [
                        'code' => 2,
                        'url' => 'index/index'
                    ];
                }
                $err_num = 0;
                $this->clearLoginErrorNum();
            } else {
                $retval = AjaxReturn($res);
                $err_num = $this->getLoginErrorNum();
                $err_num += 1;
                $this->setLoginErrorNum($err_num);
            }
            $retval['error_num'] = $err_num;
            return $retval;
        }
        
        // 点击商品详情没有登录首先要获取上一页
        $pre_url = '';
        $bund_pre_url = $_SESSION['login_pre_url'];
        if (empty($bund_pre_url)) {
            if (! empty($_SERVER['HTTP_REFERER'])) {
                $pre_url = $_SERVER['HTTP_REFERER'];
                if (strpos($pre_url, 'login')) {
                    $pre_url = '';
                }
                $_SESSION['login_pre_url'] = $pre_url;
            }
        }
        
        $isNeedVerification = $this->isNeedVerification();
        $this->assign("is_need_verification", $isNeedVerification);
        
        // 获取商场logo
        $website = new WebSite();
        $web_info = $website->getWebSiteInfo();
        
        $tencent = new Config();
        $instance_id = 0;
        $qq_info = $tencent->getQQConfig($instance_id);
        $Wchat_info = $tencent->getWchatConfig($instance_id);
        $this->assign("qq_info", $qq_info);
        $this->assign("Wchat_info", $Wchat_info);
        $this->assign("web_info", $web_info);
        $this->assign("title_before", "用户登录");
        return view($this->style . 'Login/login');
    }
    
    /*
     * 吴奇
     * 首页登录
     * 2017/3/7 9:55
     */
    public function login()
    {
        $tencent = new Config();
        $instance_id = 0;
        $qq_info = $tencent->getQQConfig($instance_id);
        $Wchat_info = $tencent->getWchatConfig($instance_id);
        $this->assign("qq_info", $qq_info);
        $this->assign("Wchat_info", $Wchat_info);
        
        $username = request()->post('userName', '');
        $password = request()->post('password', '');
        if ($this->login_verify_code["value"]["pc"] == 1) {
            $vertification = request()->post('vertification', '');
            if (! captcha_check($vertification)) {
                
                $retval = [
                    'code' => 0,
                    'message' => "验证码错误"
                ];
                return $retval;
            }
        }
        $res = $this->user->login($username, $password);
        if ($res == 1) {
            if (! empty($_SESSION['login_pre_url'])) {
                $retval = [
                    'code' => 1,
                    'url' => $_SESSION['login_pre_url']
                ];
            } else {
                $retval = [
                    'code' => 2,
                    'url' => 'index/index'
                ];
            }
        } else {
            $retval = AjaxReturn($res);
        }
        return $retval;
    }
    
    /*
     * 吴奇
     * 2017/2/8 14:30
     * 以下两个分别为注册页面
     */
    public function mobile()
    {
        $member = new Member();
        if (request()->isAjax()) {
            // 获取数据库中的用户列表
            $user_mobile = request()->get('mobile');
            $exist = $member->memberIsMobile($user_mobile);
            return $exist;
        }
        if (request()->isPost()) {
            $password = request()->post('password', '');
            $email = '';
            $mobile = request()->post('mobile', '');
            $user_name = '';
            if ($this->notice['noticeMobile'] == 1) {
                $mobile_code = request()->post('mobile_code', '');
                $times_get = Session::get('mobileVerificationCode_times');
                $verification_code = Session::get('mobileVerificationCode');
                if(time() - $times_get >= 300){
                	$this->error("你的动态码已失效");
                }else{
                	if ($mobile_code == $verification_code && ! empty($verification_code)) {
                		$retval_id = $member->registerMember($user_name, $password, $email, $mobile, '', '', '', '', '');
                		$result = true;
                	} else {
                		$retval_id = 0;
                		$result = false;
                	}
                }
                
            } else {
                $retval_id = $member->registerMember($user_name, $password, $email, $mobile, '', '', '', '', '');
                $result = true;
            }
            
            if ($retval_id > 0 && $result) {
            	Session::pull('mobileVerificationCode');
            	Session::pull('mobileVerificationCode_times');
                if (! empty($_SESSION['login_pre_url'])) {
                    $retval = [
                        'code' => 1,
                        'url' => $_SESSION['login_pre_url']
                    ];
                } else {
                    $retval = [
                        'code' => 2,
                        'url' => 'index/index'
                    ];
                }
                // 注册成功送优惠券
                $Config = new Config();
                $integralConfig = $Config->getIntegralConfig($this->instance_id);
                if ($integralConfig['register_coupon'] == 1) {
                    $rewardRule = new PromoteRewardRule();
                    $res = $rewardRule->getRewardRuleDetail($this->instance_id);
                    if ($res['reg_coupon'] != 0) {
                        $member = new Member();
                        $retval = $member->memberGetCoupon($retval_id, $res['reg_coupon'], 2);
                    }
                }
                $this->success("注册成功", __URL(__URL__ . "/index"));
            } else {
                $error_array = AjaxReturn($retval);
                $message = $error_array["message"];
                $this->error($message, __URL(__URL__ . "/login/mobile"));
            }
        }
        // 获取商场logo
        $website = new WebSite();
        $web_info = $website->getWebSiteInfo();
        
        $config = new Config();
        $instanceid = 0;
        // 验证注册配置
        $this->verifyRegConfig("mobile");
        $phone_info = $config->getMobileMessage($instanceid);
        $this->assign("phone_info", $phone_info);
        $notice_templa_info = $config->getNoticeTemplateOneDetail($instanceid, 'sms', 'register_validate');
        if (! empty($notice_templa_info)) {
            $is_enable = $notice_templa_info['is_enable'];
        } else {
            $is_enable = 0;
        }
        $email_info = $config->getEmailMessage($instanceid);
        $this->assign("email_info", $email_info);
        $this->assign("web_info", $web_info);
        $this->assign("is_enable", $is_enable);
        $this->assign("title_before", "手机注册");
        
        //pc端注册广告位
        $platform = new Platform();
        $register_adv = $platform -> getPlatformAdvPositionDetailByApKeyword("pcRegisterAdv");
        $this->assign("register_adv", $register_adv['adv_list'][0]);
        
        return view($this->style . "Login/mobile");
    }

    /**
     * 发送注册短信验证码
     *
     * @return boolean
     */
    public function sendSmsRegisterCode()
    {
        $params['mobile'] = request()->post('mobile', '');
        $vertification = request()->post('vertification', '');
        if ($this->login_verify_code["value"]["pc"] == 1) {
            if (! captcha_check($vertification)) {
                return $result = [
                    'code' => - 1,
                    'message' => "验证码错误"
                ];
            } else {
                $params['shop_id'] = 0;
                $result = runhook('Notify', 'registSmsValidation', $params);
                Session::set('mobileVerificationCode', $result['param']);
                Session::set('mobileVerificationCode_times', time());
            }
        } else {
            $params['shop_id'] = 0;
            $result = runhook('Notify', 'registSmsValidation', $params);
            Session::set('mobileVerificationCode', $result['param']);
            Session::set('mobileVerificationCode_times', time());
        }
        if (empty($result)) {
            return $result = [
                'code' => - 1,
                'message' => "发送失败"
            ];
        } elseif ($result['code'] == 0) {
            return $result = [
                'code' => 0,
                'message' => "发送成功"
            ];
        } else {
            return $result = [
                'code' => $result['code'],
                'message' => $result['message']
            ];
        }
    }

    /**
     * 发送邮箱验证码
     */
    public function sendEmailCode()
    {
        $params['email'] = request()->post('email', '');
        $vertification = request()->post('vertification', '');
        if ($this->login_verify_code["value"]["pc"] == 1) {
            if (! captcha_check($vertification)) {
                return $result = [
                    'code' => - 1,
                    'message' => "验证码错误"
                ];
            } else {
                $params['shop_id'] = 0;
                $result = runhook('Notify', 'registEmailValidation', $params);
                Session::set('emailVerificationCode', $result['param']);
            }
        } else {
            $params['shop_id'] = 0;
            $result = runhook('Notify', 'registEmailValidation', $params);
            Session::set('emailVerificationCode', $result['param']);
        }
        if (empty($result)) {
            return $result = [
                'code' => - 1,
                'message' => "发送失败"
            ];
        } elseif ($result['code'] == 0) {
            return $result = [
                'code' => 0,
                'message' => "发送成功"
            ];
        } else {
            return $result = [
                'code' => $result['code'],
                'message' => $result['message']
            ];
        }
    }

    /**
     * 注册手机号验证码验证
     * 任鹏强
     * 2017年6月17日16:26:46
     *
     * @return multitype:number string
     */
    public function register_check_code()
    {
        $send_param = request()->post('send_param', '');
        $param = session::get('mobileVerificationCode');
        
        if ($send_param == $param && $send_param != '') {
            $retval = [
                'code' => 0,
                'message' => "验证码一致"
            ];
        } else {
            $retval = [
                'code' => 1,
                'message' => "验证码不一致"
            ];
        }
        return $retval;
    }

    public function email()
    {
        $member = new Member();
        if (request()->isAjax()) {
            // 获取数据库中的用户列表
            $user_email = request()->get('email', '');
            $exist = $member->memberIsEmail($user_email);
            return $exist;
        }
        if (request()->isPost()) {
            // $min = 1;
            // $max = 1000000000;
            $password = request()->post('password', '');
            $email = request()->post('email', '');
            $mobile = '';
            $user_name = '';
            $uid = $this->user->getSessionUid();
            $email_code = request()->post('email_code', '');
            $verification_code = Session::get('emailVerificationCode');
            // 判断邮箱是否开启
            if ($this->notice['noticeEmail'] == 1) {
                // 开启的话进行验证
                if ($email_code == $verification_code && ! empty($verification_code)) {
                    $retval_id = $member->registerMember($user_name, $password, $email, $mobile, '', '', '', '', '');
                    $result = true;
                } else {
                    $retval = "";
                    $result = false;
                }
            } else {
                // 未开启直接进行注册
                $retval_id = $member->registerMember($user_name, $password, $email, $mobile, '', '', '', '', '');
                $result = true;
            }
            if ($retval_id > 0) {
                // 注册成功送优惠券
                $Config = new Config();
                $integralConfig = $Config->getIntegralConfig($this->instance_id);
                if ($integralConfig['register_coupon'] == 1) {
                    $rewardRule = new PromoteRewardRule();
                    $res = $rewardRule->getRewardRuleDetail($this->instance_id);
                    if ($res['reg_coupon'] != 0) {
                        $member = new Member();
                        $retval = $member->memberGetCoupon($retval_id, $res['reg_coupon'], 2);
                    }
                }
                $this->success("注册成功", __URL(__URL__ . "/index"));
            } else {
                $error_array = AjaxReturn($retval);
                $message = $error_array["message"];
                $this->error($message, __URL(__URL__ . "/login/email"));
            }
        }
        // 获取商场logo
        $website = new WebSite();
        $web_info = $website->getWebSiteInfo();
        $instanceid = 0;
        $config = new Config();
        // 验证注册配置
        $this->verifyRegConfig("email");
        
        $phone_info = $config->getMobileMessage($instanceid);
        $this->assign("phone_info", $phone_info);
        $email_info = $config->getEmailMessage($instanceid);
        $this->assign("email_info", $email_info);
        
        $this->assign("web_info", $web_info);
        $this->assign("title_before", "邮箱注册");
        
        //pc端注册广告位
        $platform = new Platform();
        $register_adv = $platform -> getPlatformAdvPositionDetailByApKeyword("pcRegisterAdv");
        $this->assign("register_adv", $register_adv['adv_list'][0]);
        
        return view($this->style . "Login/email");
    }

    public function register()
    {
        if (request()->isAjax()) {
            // 获取数据库中的用户列表
            $username = request()->get('username', '');
            $exist = $this->user->judgeUserNameIsExistence($username);
            return $exist;
        }
        if (request()->isPost()) {
            // $min = 10000000000;
            // $max = 19999999999;
            $member = new Member();
            $password = request()->post('password', '');
            // $email = rand($min, $max) . '@qq.com';
            // $mobile = rand($min, $max);
            $user_name = request()->post("username", '');
            
            $retval_id = $member->registerMember($user_name, $password, '', '', '', '', '', '', '');
            if ($retval_id > 0) {
                // $this->success("注册成功", __URL__."/index");
                // 注册成功送优惠券
                $Config = new Config();
                $integralConfig = $Config->getIntegralConfig($this->instance_id);
                if ($integralConfig['register_coupon'] == 1) {
                    $rewardRule = new PromoteRewardRule();
                    $res = $rewardRule->getRewardRuleDetail($this->instance_id);
                    if ($res['reg_coupon'] != 0) {
                        $retval = $member->memberGetCoupon($retval_id, $res['reg_coupon'], 2);
                    }
                }
                $redirect = __URL(__URL__ . '/index');
                $this->redirect($redirect);
            } else {
                $error_array = AjaxReturn($retval);
                $message = $error_array["message"];
                $redirect = __URL(__URL__ . '/login/register');
                $this->error($message, $redirect);
                // return AjaxReturn($retval);
            }
        }
        $instanceid = 0;
        $config = new Config();
        // 验证注册配置
        $this->verifyRegConfig("plain");
        $website = new WebSite();
        $web_info = $website->getWebSiteInfo();
        $phone_info = $config->getMobileMessage($instanceid);
        $this->assign("phone_info", $phone_info);
        
        $email_info = $config->getEmailMessage($instanceid);
        $this->assign("email_info", $email_info);
        $this->assign("web_info", $web_info);
        $this->assign("title_before", "普通注册");
        
        //pc端注册广告位
        $platform = new Platform();
        $register_adv = $platform -> getPlatformAdvPositionDetailByApKeyword("pcRegisterAdv");
  
        $this->assign("register_adv", $register_adv['adv_list'][0]);
        
        return view($this->style . 'Login/register');
    }

    /**
     * 验证码
     *
     * @return multitype:number string
     */
    public function vertify()
    {
        $vertification = request()->post('vertification', '');
        if (! captcha_check($vertification)) {
            $retval = [
                'code' => 0,
                'message' => "验证码错误"
            ];
        } else {
            $retval = [
                'code' => 1,
                'message' => "验证码正确"
            ];
        }
        return $retval;
    }
    
    /*
     * 以下为找回密码页面
     */
    public function findPasswd()
    {
        if (request()->isAjax()) {
            // 获取数据库中的用户列表
            $username = request()->get('username', '');
            $exist = false;
            $user_list = $this->user->getMemberList();
            foreach ($user_list["data"] as $user_list2) {
                if ($user_list2["user_tel"] == $username) {
                    $exist = true;
                } elseif ($user_list2["user_email"] == $username) {
                    $exist = true;
                }
            }
            return $exist;
        }
        
        // 获取商城logo
        $website = new WebSite();
        $web_info = $website->getWebSiteInfo();
        $this->assign("web_info", $web_info);
        $this->assign("title_before", "密码找回");
        return view($this->style . "Login/findPasswd");
    }

    /**
     * 邮箱短信验证
     *
     * @return Ambigous <string, \think\mixed>
     */
    public function forgotValidation()
    {
        $send_type = request()->post("type", "");
        $send_param = request()->post("send_param", "");
        $vertification = request()->post("vertification", "");
        $shop_id = 0;
     
        if ($this->login_verify_code["value"]["pc"] == 1) {
            if (! captcha_check($vertification)) {
            	
                $result = [
                    'code' => - 1,
                    'message' => "验证码错误"
                ];
                return $result;
            } else {
                $member = new Member();
                if ($send_type == 'sms') {
                    if (! $member->memberIsMobile($send_param)) {
                        $result = [
                            'code' => - 1,
                            'message' => "该手机号未注册"
                        ];
                        return $result;
                    } else {
                        Session::set("codeMobile", $send_param);
                    }
                  
                } elseif ($send_type == 'email') {
                    $member->memberIsEmail($send_param);
                    if (! $member->memberIsEmail($send_param)) {
                        $result = [
                            'code' => - 1,
                            'message' => "该邮箱未注册"
                        ];
                        return $result;
                    } else {
                        Session::set("codeEmail", $send_param);
                    }
                }
                
                $params = array(
                    "send_type" => $send_type,
                    "send_param" => $send_param,
                    "shop_id" => $shop_id
                );
                
                $result = runhook("Notify", "forgotPassword", $params);             
                Session::set('forgotPasswordVerificationCode', $result['param']);
              
                if (empty($result)) {
                	
                    return $result = [
                        'code' => - 1,
                        'message' => "发送失败"
                    ];
                } elseif ($result['code'] == 0) {
                    return $result = [
                        'code' => 0,
                        'message' => "发送成功"
                    ];
                } else {
                	
                    return $result = [
                        'code' => $result['code'],
                        'message' => $result['message']
                    ];
                }
            }
        } else {
            // 手机注册验证
            $member = new Member();
            if ($send_type == 'sms') {
                if (! $member->memberIsMobile($send_param)) {
                    $result = [
                        'code' => - 1,
                        'message' => "该手机号未注册"
                    ];
                    return $result;
                } else {
                    Session::set("codeMobile", $send_param);
                }
            } elseif ($send_type == 'email') {
                $member->memberIsEmail($send_param);
                if (! $member->memberIsEmail($send_param)) {
                    $result = [
                        'code' => - 1,
                        'message' => "该邮箱未注册"
                    ];
                    return $result;
                } else {
                    Session::set("codeEmail", $send_param);
                }
            }
            $params = array(
                "send_type" => $send_type,
                "send_param" => $send_param,
                "shop_id" => $shop_id
            );
            $result = runhook("Notify", "forgotPassword", $params);
            Session::set('forgotPasswordVerificationCode', $result['param']);
            
            if (empty($result)) {
                return $result = [
                    'code' => - 1,
                    'message' => "发送失败"
                ];
            } elseif ($result['code'] == 0) {
                return $result = [
                    'code' => 0,
                    'message' => "发送成功"
                ];
            } else {
                return $result = [
                    'code' => $result['code'],
                    'message' => $result['message']
                ];
            }
        }
    }

    /**
     * 找回密码密码重置
     *
     * @return unknown[]
     */
    public function setNewPasswordByEmailOrmobile()
    {
        $userInfo = request()->post('userInfo', '');
        $password = request()->post('password', '');
        $type = request()->post('type', '');
        if ($type == "email") {
            $codeEmail = Session::get("codeEmail");
            if ($userInfo != $codeEmail) {
                return $retval = array(
                    "code" => - 1,
                    "message" => "该邮箱与验证邮箱不符"
                );
            } else {
                $res = $this->user->updateUserPasswordByEmail($userInfo, $password);
                Session::delete("codeEmail");
            }
        } elseif ($type == "mobile") {
            $codeMobile = Session::get("codeMobile");
            if ($userInfo != $codeMobile) {
                return $retval = array(
                    "code" => - 1,
                    "message" => "该手机号与验证手机不符"
                );
            } else {
                $res = $this->user->updateUserPasswordByMobile($userInfo, $password);
                Session::delete("codeMobile");
            }
        }
        return AjaxReturn($res);
    }

    /**
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function check_code()
    {
        $send_param = request()->post('send_param', '');
        $param = Session::get('emailVerificationCode');
        if ($send_param == $param && $send_param != '') {
            $retval = [
                'code' => 0,
                'message' => "验证码一致"
            ];
        } else {
            $retval = [
                'code' => 1,
                'message' => "验证码不一致"
            ];
        }
        return $retval;
    }

    public function ckeck_find_password_code()
    {
        $send_param = request()->post('send_param', '');
        $param = Session::get('forgotPasswordVerificationCode');
        if ($send_param == $param && $send_param != '') {
            $retval = [
                'code' => 0,
                'message' => "验证码一致"
            ];
        } else {
            $retval = [
                'code' => 1,
                'message' => "验证码不一致"
            ];
        }
        return $retval;
    }

    /**
     * 第三方登录登录
     */
    public function oauthLogin()
    {
        $type = request()->get('type', '');
        $test = ThinkOauth::getInstance($type);
        $this->redirect($test->getRequestCodeURL());
    }

    /**
     * qq登录返回
     */
    public function callback()
    {
        $code = request()->get('code', '');
        if (empty($code))
            die();
        $qq = ThinkOauth::getInstance('QQLOGIN');
        $token = $qq->getAccessToken($code);
        if (! empty($token['openid'])) {
            if (! empty($_SESSION['bund_pre_url'])) {
                // 1.检测当前qqopenid是否已经绑定，如果已经绑定直接返回绑定失败
                $bund_pre_url = $_SESSION['bund_pre_url'];
                $_SESSION['bund_pre_url'] = '';
                $is_bund = $this->user->checkUserQQopenid($token['openid']);
                if ($is_bund == 0) {
                    // 2.绑定操作
                    $qq = ThinkOauth::getInstance('QQLOGIN', $token);
                    $data = $qq->call('user/get_user_info');
                    $_SESSION['qq_info'] = json_encode($data);
                    // 执行用户信息更新user服务层添加更新绑定qq函数（绑定，解绑）
                    $res = $this->user->bindQQ($token['openid'], json_encode($data));
                    // 如果执行成功执行跳转
                    
                    if ($res) {
                        $this->success('绑定成功', $bund_pre_url);
                    } else {
                        $this->error('绑定失败', $bund_pre_url);
                    }
                } else {
                    $this->error('该qq已经绑定', $bund_pre_url);
                }
            } else {
                $retval = $this->user->qqLogin($token['openid']);
                // 已经绑定
                if ($retval == 1) {
                    if (! empty($_SESSION['login_pre_url'])) {
                        $this->redirect($_SESSION['login_pre_url']);
                    } else
                        $this->redirect(__URL__);
                    // $this->success("登录成功", "Index/index");
                }
                if ($retval == USER_NBUND) {
                    $qq = ThinkOauth::getInstance('QQLOGIN', $token);
                    $data = $qq->call('user/get_user_info');
                    $_SESSION['qq_info'] = json_encode($data);
                    $this->assign("qq_info", json_encode($data));
                    $this->assign("qq_openid", $token['openid']);
                    $this->assign("data", $data);
                    return view($this->style . 'Login/qqcallback');
                }
            }
        }
    }
    // 验证注册配置
    public function verifyRegConfig($type = "plain")
    {
        $instanceid = 0;
        $config = new Config();
        $reg_config_info = $config->getRegisterAndVisit($instanceid);
        $reg_config = json_decode($reg_config_info["value"], true);
        if ($reg_config["is_register"] != 1) {
            $this->error("抱歉,商城暂未开放注册!");
        }
        if ($reg_config['register_info'] != "") {
            if (strpos($reg_config['register_info'], $type) === false) {
                if ($type == "mobile") {
                    $text = "抱歉,商城暂未开放手机注册!";
                } elseif ($type == "email") {
                    $text = "抱歉,商城暂未开放邮箱注册!";
                } else {
                    $text = "抱歉,商城暂未开放普通注册!";
                }
                $this->error($text);
            }
        } else {
            $this->error("抱歉,商城暂未开放注册!");
        }
        // 传递本页面上传方式
        $this->assign("login_type", $type);
        $this->assign("reg_config", $reg_config);
    }

    /**
     * 注册公共外层
     */
    public function registerBox()
    {
        $instanceid = 0;
        $config = new Config();
        $reg_config_info = $config->getRegisterAndVisit($instanceid);
        $reg_config = json_decode($reg_config_info["value"], true);
        if (trim($reg_config['register_info']) == "") {
            $this->error("抱歉,商城暂未开放注册!");
        } else {
            if (strpos($reg_config['register_info'], "plain") !== false) {
                $redirect = __URL(__URL__ . "/login/register");
                $this->redirect($redirect);
            } elseif (strpos($reg_config['register_info'], "mobile") !== false) {
                $redirect = __URL(__URL__ . "/login/mobile");
                $this->redirect($redirect);
            } elseif (strpos($reg_config['register_info'], "email") !== false) {
                $redirect = __URL(__URL__ . "/login/email");
                $this->redirect($redirect);
            } else {
                $this->error("抱歉,商城暂未开放注册!");
            }
        }
    }
    
    /**
     * 绑定账号
     * @return \think\response\View
     */
    public function bindAccount(){
        if(request()->isAjax()){
            $user_name = request()->post('username', '');
            $password = request()->post('password', '');
            $captcha = request()->post("captcha", "");
            if($this->login_verify_code["value"]["pc"]){
                if(!empty($captcha)){
                    if(!captcha_check($captcha)){
                        return array(
                            "code" => -1,
                            "message" => "验证码错误"
                        );
                    }
                }else{
                    return array(
                        "code" => -1,
                        "message" => "请输入验证码"
                    );
                }
            }
            $retval = $this->user->login($user_name, $password);
  
            if($retval > 0){
                // qq登录回调
                if(isset($_SESSION['login_type']) && $_SESSION['login_type'] == 'QQLOGIN'){
                    $qq_openid = $_SESSION['qq_openid'];
                    $qq_info = $_SESSION['qq_info'];
                   
                    if(!empty($qq_openid) && !empty($qq_info)){
                        $res = $this->user -> bindQQ($qq_openid, $qq_info);
                        if($res){
                            $uid = $this->user -> getCurrUserId();
                            $url = str_replace('api.php', 'index.php', __URL(__URL__.'wap/login/updateUserImg?uid='.$uid.'&type=qq'));
                            http($url, 1);
                            unset($_SESSION['qq_openid']);
                            unset($_SESSION['qq_info']);
                            unset($_SESSION['bund_pre_url']);
                            unset($_SESSION['login_type']);
                            return array(
                                "code" => 1,
                                "message" => "绑定成功"
                            );
                        }else{
                            return array(
                                "code" => - 1,
                                "message" => "账号绑定失败"
                            );
                        }
                    }else{
                        return array(
                            "code" => -1,
                            "message" => "未获取到绑定信息"
                        );
                    }
                }
                // 微信登录
                if(isset($_SESSION['login_type']) && $_SESSION['login_type'] == 'WCHAT'){
                    $unionid = $_SESSION['wx_unionid'];
                    $wx_info = $_SESSION['wx_info'];
                    if(!empty($unionid) && !empty($wx_info)){
                        $res = $this->user -> bindWchat($unionid, $wx_info, $user_name);
                        if($res){
                            // 拉取用户头像
                            $uid = $this->user -> getCurrUserId();
                            $url = str_replace('api.php', 'index.php', __URL(__URL__.'wap/login/updateUserImg?uid='.$uid.'&type=wchat'));
                            http($url, 1);
                            unset($_SESSION['wx_unionid']);
                            unset($_SESSION['wx_info']);
                            unset($_SESSION['bund_pre_url']);
                            unset($_SESSION['login_type']);
                            return array(
                                "code" => 1,
                                "message" => "绑定成功"
                            );
                        }else{
                            return array(
                                "code" => -1,
                                "message" => "账号绑定失败"
                            );
                        }
                    }else{
                        return array(
                            "code" => -1,
                            "message" => "未获取到绑定信息"
                        );
                    }
                }
            }else{
                return array(
                    "code" => -1,
                    "message" => "用户名或密码错误"
                );
            }
        }
        
        $config = new Config();
        // 验证注册配置
        $this->verifyRegConfig("plain");
        $website = new WebSite();
        $web_info = $website->getWebSiteInfo();
       
        $this->assign("web_info", $web_info);
        $this->assign("title_before", "绑定账号");
        //pc端注册广告位
        $platform = new Platform();
        $register_adv = $platform -> getPlatformAdvPositionDetailByApKeyword("pcRegisterAdv");
        $this->assign("register_adv", $register_adv['adv_list'][0]);
            
        return view($this -> style . "Login/bindAccount");
    }
    
    /**
     * 完善信息
     * @return \think\response\View
     */
    public function perfectInfo(){
        $config = new Config();
        // 验证注册配置
        $this->verifyRegConfig("plain");
        $website = new WebSite();
        $web_info = $website->getWebSiteInfo();
        $this->verifyRegConfig();
        
        if(request()->isAjax()){
            $user_name = request()->post('username', '');
            $password = request()->post('password', '');
            $captcha = request()->post("captcha", "");
            if($this->login_verify_code["value"]["pc"]){
                if(!empty($captcha)){
                    if(!captcha_check($captcha)){
                        return array(
                            "code" => -1,
                            "message" => "验证码错误"
                        );
                    }
                }else{
                    return array(
                        "code" => -1,
                        "message" => "请输入验证码"
                    );
                }
            }
            
            // qq
            if(isset($_SESSION['login_type']) && $_SESSION['login_type'] == 'QQLOGIN'){
                $qq_openid = $_SESSION['qq_openid'];
                $qq_info = $_SESSION['qq_info'];
                $result = $this->user->registerMember($user_name, $password, '', '', $qq_openid, $qq_info, '', '', '');
            }
            
            // 微信
            if(isset($_SESSION['login_type']) && $_SESSION['login_type'] == 'WCHAT'){
                $unionid = $_SESSION['wx_unionid'];
                $wx_info = $_SESSION['wx_info'];
                $result = $this->user->registerMember($user_name, $password, '', '', '', '', '', $wx_info, $unionid);
            }
            
            if($result > 0){
                // 注册成功送优惠券
                $Config = new Config();
                $shop_id = 0;
                $integralConfig = $Config->getIntegralConfig($shop_id);
                if ($integralConfig['register_coupon'] == 1) {
                    $rewardRule = new PromoteRewardRule();
                    $res = $rewardRule->getRewardRuleDetail($shop_id);
                    if ($res['reg_coupon'] != 0) {
                        $member = new Member();
                        $retval = $member->memberGetCoupon($result, $res['reg_coupon'], 2);
                    }
                } 
                // 注册成功之后
                if(isset($_SESSION['login_type']) && $_SESSION['login_type'] == 'QQLOGIN'){
                    unset($_SESSION['qq_openid']);
                    unset($_SESSION['qq_info']);
                }elseif(isset($_SESSION['login_type']) && $_SESSION['login_type'] == 'WCHAT'){
                    unset($_SESSION['wx_unionid']);
                    unset($_SESSION['wx_info']);
                }
                $url = empty($_SESSION['login_pre_url']) ? __URL(__URL__ . "/member/index") : $_SESSION['login_pre_url'];
                return array(
                    "code" => 1,
                    "message" => "注册成功",
                    "url" => $url
                );
            }else{
                return array(
                    "code" => -1,
                    "message" => "注册失败"
                );
            }
        }
         
        $this->assign("web_info", $web_info);
        $this->assign("title_before", "完善信息");
        //pc端注册广告位
        $platform = new Platform();
        $register_adv = $platform -> getPlatformAdvPositionDetailByApKeyword("pcRegisterAdv");
        $this->assign("register_adv", $register_adv['adv_list'][0]);
        
        return view($this -> style . "Login/perfectInfo");
    }
    
    /**
     * 判断是否需要验证
     * @return boolean
     */
    public function isNeedVerification(){
        if ($this->login_verify_code["value"]["pc"] == 1 && $this->login_verify_code["value"]["error_num"] == 0) {
            return true;
        }elseif($this->login_verify_code["value"]["pc"] == 1 && $this->login_verify_code["value"]["error_num"] > 0){
            $err_num = $this->getLoginErrorNum();
            if($err_num >= $this->login_verify_code["value"]["error_num"]){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
    
    /**
     * 获取当前ip登录错误次数
     * @return number|mixed|NULL|unknown
     */
    public function getLoginErrorNum(){
        $ip = request()->ip();
        $error_num = Session::get($ip.'error_num');
        if ($error_num == null){
            $error_num = 0;
            Session::set($ip.'shop_error_num', 0);
        }
        return $error_num;
    }
    
    /**
     * 设置当前ip登录错误次数
     * @param unknown $error_num
     */
    public function setLoginErrorNum($error_num){
        $ip = request()->ip();
        Session::set($ip.'shop_error_num', $error_num);
    }
    
    /**
     * 清除当前ip登录错误次数
     * @param unknown $error_num
     */
    public function clearLoginErrorNum(){
        $ip = request()->ip();
        Session::delete($ip.'shop_error_num');
    }
}