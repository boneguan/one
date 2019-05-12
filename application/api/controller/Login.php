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
namespace app\api\controller;

use think\Cache;
use think\Session;
use data\service\User;
use data\service\Member as MemberService;
use data\service\Applet\AppletWechat;
use data\service\promotion\PromoteRewardRule;
use data\service\WebSite;
use data\service\Config;
use data\service\Platform;

/**
 * 前台用户登录
 *
 * @author Administrator
 * 
 */
class Login extends BaseController
{

    public $auth_key = 'addexdfsdfewfscvsrdf!@#';

    public $user;

    public $web_site;

    public function __construct()
    {
        parent::__construct();
        $this->user = new MemberService();
        $this->web_site = new WebSite();
    }

    /**
     * 获取微信小程序的配置信息
     *
     * @return \think\response\Json
     */
    function getWechatInfo()
    {
        $config = new Config();
        $applet_config = $config->getInstanceAppletConfig($this->instance_id);
        $appid = '';
        $secret = '';
        if (! empty($applet_config["value"])) {
            $appid = $applet_config["value"]['appid'];
            $secret = $applet_config["value"]['appsecret'];
        } else {
            return $this->outMessage("获取微信信息", '', - 50, '后台未配置小程序');
        }
        $code = request()->post("code", "");
        $url = "https://api.weixin.qq.com/sns/jscode2session";
        $url = $url . "?appid=$appid";
        $url .= "&secret=$secret";
        $url .= "&js_code=$code&grant_type=authorization_code";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $result = curl_exec($ch);
        curl_close($ch);
        return $this->outMessage("获取微信信息", $result);
    }

    /**
     * 获取微信登录信息
     *
     * @return \think\response\Json
     */
    function getWechatInfos()
    {
        $title = '获取微信登录信息';
        $config = new Config();
        $applet_config = $config->getInstanceAppletConfig($this->instance_id);
        $appid = '';
        $sessionKey = request()->post('sessionKey', '');
        $encryptedData = request()->post('encryptedData', '');
        $iv = request()->post('iv', '');
        if (! empty($applet_config["value"])) {
            $appid = $applet_config["value"]['appid'];
        } else {
            return $this->outMessage($title, '', - 50, '商家未配置小程序');
        }
        $wchat_applet = new WchatApplet($appid, $sessionKey);
        $errCode = $wchat_applet->getWchatInfo($encryptedData, $iv, '');
        if ($errCode < 0) {
            $message = '登录失败';
            switch ($errCode) {
                case - 41001:
                    $message = 'encodingAesKey 非法';
                    break;
                case - 41002:
                    $message = 'aes 解密失败';
                    break;
                case - 41003:
                    $message = 'buffer 非法';
                    break;
                case - 41004:
                    $message = 'base64 解密失败';
                    break;
                default:
                    break;
            }
            return $this->outMessage($title, '', - 50, $message);
        } else {
            return $this->outMessage($title, $errCode);
        }
    }

    /**
     * 微信登录
     */
    public function wechatLogin()
    {
        $title = "会员登录";
        $openid = request()->post('openid', '');
        $info = request()->post('wx_info', '');
        $source_uid = request()->post('sourceid', '');
        
        if (empty($openid) || $openid == 'undefined') {
            return $this->outMessage($title, '', '-50', "无效的openid");
        }
        // 处理信息
        $applet_member = new MemberService();
        $applet_wechat = new AppletWechat();
        $res = array();
        $wx_info = json_decode($info, true);
        $unionid = $wx_info['unionid'] == 'undefined' || $wx_info['unionid'] == null || $wx_info['unionid'] == 'null' ? '' : $wx_info['unionid'];
        $res = $applet_wechat->wchatAppLogin($openid, $unionid);
        // 返回信息
        if ($res == 1) {
            $user_info = $applet_wechat->getUserDetailByOpentid($openid);
            $member_info = $applet_member->getMemberDetail($user_info['uid'], $user_info['instance_id']);
            $encode = $this->niuEncrypt(json_encode($user_info));
            return $this->outMessage($title, array(
                'member_info' => $member_info,
                'token' => $encode
            ));
        } else if ($res == 10) {
            $user_info = $applet_wechat->getUserDetailByUnionid($unionid);
            $member_info = $applet_member->getMemberDetail($user_info['uid'], $user_info['instance_id']);
            $encode = $this->niuEncrypt(json_encode($user_info));
            return $this->outMessage($title, array(
                'member_info' => $member_info,
                'token' => $encode
            ));
        } else {
            if ($res == USER_NBUND) {
                return $this->wchatRegister($openid, $info, $source_uid);
            } else {
                return $this->outMessage($title, '', '-50', '用户被锁定或者登录失败!');
            }
        }
    }

    public function wchatRegister($openid, $info, $source_uid)
    {
        $title = "会员注册";
        // 处理信息
        $member = new MemberService();
        $applet_user = new User();
        $weapp_user = new AppletWechat();
        $applet_member = new MemberService();
        
        $wx_unionid = '';
        $wx_info = json_decode($info, true);
        $wx_info['opneid'] = $openid;
        $wx_info['sex'] = $wx_info['gender'];
        $wx_info['headimgurl'] = $wx_info['avatarUrl'];
        $wx_info['nickname'] = $wx_info['nickName'];
        $wx_unionid = $wx_info['unionid'];
        $wx_info = json_encode($wx_info);
        
        $retval = $weapp_user->wchatAppLogin($openid, $wx_unionid);
        if ($retval == USER_NBUND) {
            
            if (! empty($source_uid)) {
                $_SESSION['source_uid'] = $source_uid;
            }
            
            // 检测是否开启微信自动注册
            $config = new Config();
            $register_and_visit = $config->getRegisterAndVisit(0);
            $register_config = json_decode($register_and_visit['value'], true);
            if (! empty($register_config) && $register_config["is_requiretel"] == 1) {
                return $this->outMessage($title, '', 20);
            }
            // 注册
            $openid = $wx_unionid == '' || $wx_unionid == 'undefined' || $wx_unionid == 'null' || $wx_unionid == null ? $openid : '';
            $result = $applet_member->registerMember('', '', '', '', '', '', $openid, $wx_info, $wx_unionid);
            if ($result > 0) {
                
                $user_info = $applet_user->getUserInfoByUid($result);
                $member_info = $applet_member->getMemberDetail($user_info['instance_id'], $user_info['uid']);
                
                // 注册成功送优惠券
                $Config = new Config();
                $integralConfig = $Config->getIntegralConfig($user_info['instance_id']);
                if ($integralConfig['register_coupon'] == 1) {
                    $rewardRule = new PromoteRewardRule();
                    $res = $rewardRule->getRewardRuleDetail($user_info['instance_id']);
                    if ($res['reg_coupon'] != 0) {
                        $member = new MemberService();
                        $retval = $member->memberGetCoupon($user_info['uid'], $res['reg_coupon'], 2);
                    }
                }
                
                $token = array(
                    'uid' => $user_info['uid'],
                    'request_time' => time()
                );
                $encode = $this->niuEncrypt(json_encode($token));
                return $this->outMessage($title, array(
                    'member_info' => $member_info,
                    'token' => $encode
                ));
            } else {
                return $this->outMessage($title, '', '-50', "注册失败");
            }
        } elseif ($retval == USER_LOCK) {
            return $this->outMessage($title, '', '-50', "用户被锁定");
        }
    }

    /**
     * 微信绑定用户
     */
    public function wchatBindMember($user_name, $password, $bind_message_info, $source_uid = '')
    {
        $bind_message_info = json_decode($bind_message_info, true);
        $bind_message_info['token'] = array(
            'openid' => $bind_message_info['openid']
        );
        unset($bind_message_info['openid']);
        $bind_message_info = json_encode($bind_message_info);
        $uid = $this->user->getUidWidthApplet($user_name, $password);
        
        if (!empty($uid)) {
            $user_info = $this->user->getUserInfoByUid($uid);
            if (empty($user_info['wx_openid']) && empty($user_info['wx_unionid'])) {
                Session::set("bind_message_info", $bind_message_info);
                Session::set('source_uid', $source_uid);
                $this->user->wchatBindMember($uid, $bind_message_info);
                return 1;
            } else {
                return -1;
            }
        }
    }

    /**
     * 注册配置信息
     */
    public function registerInfo()
    {
        $title = '注册配置信息';
        
        $config = new Config();
        $instanceid = 0;
        // 判断是否开启邮箱注册
        $reg_config_info = $config->getRegisterAndVisit($instanceid);
        $reg_config = json_decode($reg_config_info["value"], true);
        if ($reg_config["is_register"] != 1) {
            return $this->outMessage($title, '', - 50, '抱歉，商城暂未开放注册');
        }
        if (strpos($reg_config['register_info'], "plain") === false && strpos($reg_config['register_info'], "mobile") === false) {
            return $this->outMessage($title, '', - 50, '抱歉，商城暂未开放注册');
        }
        // 登录配置
        $web_config = new Config();
        $login_config = $web_config->getLoginConfig();
        
        $code_config = $web_config->getLoginVerifyCodeConfig($this->instance_id);
        
        $data = array(
            'reg_config' => $reg_config,
            'code_config' => $code_config,
            'login_config' => $login_config
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 登录/注册广告位
     */
    public function getAdv()
    {
        $title = '登录/注册广告位';
        $platform = new Platform();
        $adv_list = $platform->getPlatformAdvPositionDetailByApKeyword("wapLogAndRegAdv");
        $data = array(
            'adv_list' => $adv_list
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 注册账户 (APP)
     */
    public function register()
    {
        $title = '注册账户';
        $member = new MemberService();
        $user_name = request()->post('username', '');
        $password = request()->post('password', '');
        $email = request()->post('email', '');
        $mobile = request()->post('mobile', '');
        $retval_id = $member->registerMember($user_name, $password, $email, $mobile, '', '', '', '', '');
        if ($retval_id > 0) {
            // 注册成功送优惠券
            $Config = new Config();
            $integralConfig = $Config->getIntegralConfig($this->instance_id);
            if ($integralConfig['register_coupon'] == 1) {
                $rewardRule = new PromoteRewardRule();
                $res = $rewardRule->getRewardRuleDetail($this->instance_id);
                if ($res['reg_coupon'] != 0) {
                    $member = new MemberService();
                    $retval = $member->memberGetCoupon($retval_id, $res['reg_coupon'], 2);
                }
            }
            $applet_user = new User();
            $applet_member = new MemberService();
            $user_info = $applet_user->getUserInfoByUid($retval_id);
            $member_info = $applet_member->getMemberDetail($user_info['instance_id']);
            $token = array(
                'uid' => $user_info['uid'],
                'request_time' => time()
            );
            $encode = $this->niuEncrypt(json_encode($token));
            $data = array(
                'member_info' => $member_info,
                'token' => $encode
            );
            return $this->outMessage($title, $data);
        } else {
            $msg = "注册失败";
            $res_ajax = AjaxReturn($retval_id);
            if (! empty($res_ajax)) {
                if (! empty($res_ajax['message'])) {
                    $msg = $res_ajax['message'];
                }
            }
            return $this->outMessage($title, '', - 50, $msg);
        }
    }

    /**
     * 注册账户 (APPLET)
     */
    public function appletRegister()
    {
        $title = '注册账户';
        $member = new MemberService();
        $user_name = request()->post('username', '');
        $password = request()->post('password', '');
        $email = request()->post('email', '');
        $mobile = request()->post('mobile', '');
        $key = request()->post('key', '-504*505');
        $verify = request()->post('verify', '');
        $sms_captcha = request()->post('sms_captcha', '');
        $bind_message_info = request()->post('bind_message_info', '');
        $source_uid = request()->post('sourceid', '');
        
        $web_config = new Config();
        $code_config = $web_config->getLoginVerifyCodeConfig($this->instance_id);
        // 图文验证码验证
        if ($code_config["value"]['pc'] == 1 && $this->check_code($verify, $key) == - 1) {
            return $this->outMessage($title, '', - 1, "验证码错误");
        }
        // 是否存在手机号验证
        if (! empty($mobile)) {
            $count = $this->checkMobileIsHas($mobile);
            if ($count > 0) {
                return $this->outMessage($title, '', - 1, "该手机号已被注册");
            }
        }
        // 密码不符合规则提示信息
        $memberController = new Member();
        $retval = $memberController->verifyValue($password);
        $flag = $retval[0];
        if ($flag < 0) {
            return $this->outMessage($title, '', - 1, $retval[1]);
        }
        // 动态验证码验证
        if (! empty(mobile) && empty($user_name)) {
            $code_key = md5('@' . $key . '-');
            $data = Cache::get($code_key);
            $sms_captcha_code = '';
            if (! empty($data['code'])) {
                $sms_captcha_code = $data['code'];
            }
            $sendMobile = $data['mobile'];
            if ($sms_captcha == $sms_captcha_code && $sendMobile == $mobile && ! empty($sms_captcha_code)) {
                // if ($sms_captcha == $sms_captcha_code && ! empty($sms_captcha_code)) {
                Cache::set($key, '');
                $retval = $this->user->login($mobile, '');
            } else {
                return $this->outMessage($title, '', - 1, '动态验证码错误');
            }
        }
        if (! empty($source_uid)) {
            $_SESSION['source_uid'] = $source_uid;
        }
        $retval_id = $member->registerMember($user_name, $password, $email, $mobile, '', '', '', '', '');
        if ($retval_id > 0) {
            if (empty($user_name)) {
                $user_name = $mobile;
            }
            $this->wchatBindMember($user_name, $password, $bind_message_info, $source_uid);
            // 注册成功送优惠券
            $Config = new Config();
            $integralConfig = $Config->getIntegralConfig($this->instance_id);
            if ($integralConfig['register_coupon'] == 1) {
                $rewardRule = new PromoteRewardRule();
                $res = $rewardRule->getRewardRuleDetail($this->instance_id);
                if ($res['reg_coupon'] != 0) {
                    $member = new MemberService();
                    $retval = $member->memberGetCoupon($retval_id, $res['reg_coupon'], 2);
                }
            }
            $applet_user = new User();
            $applet_member = new MemberService();
            $user_info = $applet_user->getUserInfoByUid($retval_id);
            $member_info = $applet_member->getMemberDetail($this->instance_id);
            $token = array(
                'uid' => $user_info['uid'],
                'request_time' => time()
            );
            $encode = $this->niuEncrypt(json_encode($token));
            $data = array(
                'member_info' => $member_info,
                'token' => $encode
            );
            return $this->outMessage($title, $data);
        } else {
            $msg = "注册失败";
            $res_ajax = AjaxReturn($retval_id);
            if (! empty($res_ajax)) {
                if (! empty($res_ajax['message'])) {
                    $msg = $res_ajax['message'];
                }
            }
            return $this->outMessage($title, '', - 1, $msg);
        }
    }

    /**
     * 检测手机号是否已经注册
     *
     * @return Ambigous <number, \data\model\unknown>
     */
    public function checkMobileIsHas()
    {
        $title = '检测手机号是否注册';
        $mobile = request()->post('mobile', '');
        if (! empty($mobile)) {
            $count = $this->user->checkMobileIsHas($mobile);
        } else {
            $count = 0;
        }
        return $this->outMessage($title, $count);
    }

    /**
     * 登录 (APP)
     *
     * @return multitype:unknown
     */
    public function Login()
    {
        $title = '会员登录';
        $user_name = request()->post('username', '');
        $password = request()->post('password', '');
        $mobile = request()->post('mobile', '');
        if (! empty($user_name)) {
            $retval = $this->user->login($user_name, $password);
        } else {
            $retval = $this->user->login($mobile, $password);
        }
        if ($retval > 0) {
            $model = $this->getRequestModel();
            $uid = Session::get($model . 'uid');
            $applet_user = new User();
            $applet_member = new MemberService();
            $user_info = $applet_user->getUserInfoByUid($uid);
            $member_info = $applet_member->getMemberDetail($user_info['instance_id']);
            
            if (! empty($member_info['user_info']['user_headimg'])) {
                if (strpos($member_info['user_info']['user_headimg'], "http://") === false && strpos($member_info['user_info']['user_headimg'], "https://") === false) {
                    $member_info['user_info']['user_headimg'] = getBaseUrl() . "/" . $member_info['user_info']['user_headimg'];
                }
            }
            
            $token = array(
                'uid' => $user_info['uid'],
                'request_time' => time()
            );
            $encode = $this->niuEncrypt(json_encode($token));
            $data = array(
                'member_info' => $member_info,
                'token' => $encode
            );
            return $this->outMessage($title, $data);
        } else {
            return $this->outMessage($title, '', $retval);
        }
    }

    /**
     * 登录 (APPLET)
     */
    public function appletLogin()
    {
        $title = '会员登录';
        $user_name = request()->post('username', '');
        $password = request()->post('password', '');
        $mobile = request()->post('mobile', '');
        $key = request()->post('key', '-504*503');
        $sms_captcha = request()->post('sms_captcha', '');
        $verify_code = request()->post('verify_code', '');
        $bind_message_info = request()->post('bind_message_info', '');
        $source_uid = request()->post('sourceid', '');
        
        if ($key == '-504*503') {
            return $this->outMessage($title, '', - 1, '参数错误');
        }
        $code_key = md5('@' . $key . '*');
        
        if (! empty(mobile)) {
            $count = $this->user->checkMobileIsHas($mobile);
            if (! $count > 0) {
                return $this->outMessage($title, '', - 1, '该手机号未注册');
            }
        }
        
        $web_config = new Config();
        $code_config = $web_config->getLoginVerifyCodeConfig($this->instance_id);
        if ($code_config["value"]['pc'] == 1 && $this->check_code($verify_code, $key) == - 1) {
            return $this->outMessage($title, '', - 1, "验证码错误");
        }
        if (! empty($user_name)) {
            $retval = $this->user->login($user_name, $password);
        } else {
            $code_key = md5('@' . $key . '-');
            $data = Cache::get($code_key);
            $sms_captcha_code = '';
            if (! empty($data['code'])) {
                $sms_captcha_code = $data['code'];
            }
            $sendMobile = $data['mobile'];
            if ($sms_captcha == $sms_captcha_code && $sendMobile == $mobile && ! empty($sms_captcha_code)) {
                // if ($sms_captcha == $sms_captcha_code && ! empty($sms_captcha_code)) {
                Cache::set($key, '');
                $retval = $this->user->login($mobile, '');
            } else {
                return $this->outMessage($title, '', - 1, '动态验证码错误');
            }
        }
        if ($retval > 0) {
            if (empty($user_name)) {
                $user_name = $mobile;
                $password = "";
            }
            $res = $this->wchatBindMember($user_name, $password, $bind_message_info, $source_uid);
            if ($res == -1) {
                return $this->outMessage($title, '', - 1, '该账号已被绑定，请输入其他账号登录');
            }
            $model = $this->getRequestModel();
            $uid = Session::get($model . 'uid');
            $applet_user = new User();
            $applet_member = new MemberService();
            $user_info = $applet_user->getUserInfoByUid($uid);
            $member_info = $applet_member->getMemberDetail($user_info['instance_id']);
            $token = array(
                'uid' => $user_info['uid'],
                'request_time' => time()
            );
            $encode = $this->niuEncrypt(json_encode($token));
            $data = array(
                'member_info' => $member_info,
                'token' => $encode
            );
            return $this->outMessage($title, $data);
        } else {
            $message = AjaxReturn($retval)['message'];
            return $this->outMessage($title, '', $retval, $message);
        }
    }

    /**
     * 发送注册短信验证码
     *
     * @return boolean
     */
    public function sendSmsRegisterCode()
    {
        $title = '发送短信验证码';
        $params['mobile'] = request()->post('mobile', '');
        $vertification = request()->post('vertification', '');
        $key = request()->post('key', '-504*501');
        
        $web_config = new Config();
        $code_config = $web_config->getLoginVerifyCodeConfig($this->instance_id);
        
        if ($code_config["value"]['pc'] == 1 && $this->check_code($vertification, $key) == - 1) {
            $result = [
                'code' => - 5,
                'message' => "验证码错误"
            ];
        } else {
            $params['shop_id'] = 0;
            $result = runhook('Notify', 'registSmsValidation', $params);
            $data['code'] = $result['param'];
            $data['mobile'] = $params['mobile'];
            $key = md5('@' . $key . '-');
            Cache::set($key, $data, 300);
        }
        
        if (empty($result)) {
            $result = [
                'code' => - 1,
                'message' => "发送失败"
            ];
        } else if ($result["code"] != 0) {
            $result = [
                'code' => $result["code"],
                'message' => $result["message"]
            ];
        } else if ($result["code"] == 0) {
            $result = [
                'code' => 0,
                'message' => "发送成功"
            ];
        }
        return $this->outMessage($title, $result);
    }

    /**
     * 检测验证码是否正确
     */
    public function check_code($code, $key)
    {
        $key = md5('@' . $key . '*');
        $verificationCode = Cache::get($key);
        if ($code != $verificationCode || empty($code)) {
            Cache::set($key, '');
            return - 1;
        } else {
            return 1;
        }
    }

    /**
     * 找回密码密码重置
     *
     * @return unknown[]
     */
    public function setNewPasswordByEmailOrmobile()
    {
        $title = '找回密码重置';
        $userInfo = request()->post('userInfo', '');
        $password = request()->post('password', '');
        $type = request()->post('type', '');
        $key = request()->post('key', '-504*510');
        $vertification = request()->post('vertification', '');
        $info_code = request()->post('info_code', '');
        
        $web_config = new Config();
        $code_config = $web_config->getLoginVerifyCodeConfig($this->instance_id);
        if ($code_config["value"]['pc'] == 1 && $this->check_code($vertification, $key) == - 1) {
            $result = [
                'code' => - 5,
                'message' => "验证码错误"
            ];
            return $this->outMessage($title, $result);
        }
        $flag = $this->findPasswd($type, $userInfo);
        
        if (! $flag) {
            $result['code'] = - 5;
            $result['message'] = $type == "email" ? '该邮箱未注册' : '该手机号未注册';
            return $this->outMessage($title, $result);
        }
        // 密码不符合规则提示信息
        $memberController = new Member();
        $retval = $memberController->verifyValue($password);
        $flag = $retval[0];
        if ($flag < 0) {
            return $this->outMessage($title, '', - 1, $retval[1]);
        }
        $key = md5('@' . $key . '-');
        $data = Cache::get($key);
        $code = $data['code'];
        
        if (empty($data)) {
            $retval = array(
                "code" => - 1,
                "message" => "动态验证码已过期"
            );
            return $this->outMessage($title, $retval);
        }
        
        if ($type == "email") {
            $codeEmail = $data["codeEmail"];
            if ($userInfo != $codeEmail) {
                $retval = array(
                    "code" => - 1,
                    "message" => "该邮箱与验证邮箱不符"
                );
                return $this->outMessage($title, $retval);
            } else {
                if ($code == $info_code && ! empty($code)) {
                    $res = $this->user->updateUserPasswordByEmail($userInfo, $password);
                    Cache::set($key, '');
                } else {
                    $retval = array(
                        "code" => - 1,
                        "message" => "动态验证码错误"
                    );
                    return $this->outMessage($title, $retval);
                }
            }
        } elseif ($type == "mobile") {
            $data = Cache::get($key);
            $codeMobile = $data["codeMobile"];
            if ($userInfo != $codeMobile) {
                $retval = array(
                    "code" => - 1,
                    "message" => "该手机号与验证手机不符"
                );
                return $this->outMessage($title, $retval);
            } else {
                if ($code == $info_code && ! empty($code)) {
                    $res = $this->user->updateUserPasswordByMobile($userInfo, $password);
                    Cache::set($key, '');
                } else {
                    $retval = array(
                        "code" => - 1,
                        "message" => "动态验证码错误"
                    );
                    return $this->outMessage($title, $retval);
                }
            }
        }
        return $this->outMessage($title, AjaxReturn($res));
    }

    /**
     * 忘记密码邮箱短信验证
     *
     * @return Ambigous <string, \think\mixed>
     */
    public function forgotValidation()
    {
        $title = '忘记密码，获取动态码';
        $send_type = request()->post("type", "");
        $send_param = request()->post("send_param", "");
        $key = request()->post('key', '-504*502');
        $vertification = request()->post('vertification', '');
        
        $shop_id = 0;
        $web_config = new Config();
        $code_config = $web_config->getLoginVerifyCodeConfig($this->instance_id);
        if ($code_config["value"]['pc'] == 1 && $this->check_code($vertification, $key) == - 1) {
            $result = [
                'code' => - 5,
                'message' => "验证码错误"
            ];
            return $this->outMessage($title, $result);
        }
        
        // 手机注册验证
        $member = new MemberService();
        if ($send_type == 'sms') {
            if (! $member->memberIsMobile($send_param)) {
                $result = [
                    'code' => - 1,
                    'message' => "该手机号未注册"
                ];
                return $this->outMessage($title, $result);
            } else {
                $data['codeMobile'] = $send_param;
            }
        } elseif ($send_type == 'email') {
            $member->memberIsEmail($send_param);
            if (! $member->memberIsEmail($send_param)) {
                $result = [
                    'code' => - 1,
                    'message' => "该邮箱未注册"
                ];
                return $this->outMessage($title, $result);
            } else {
                $data['codeEmail'] = $send_param;
            }
        }
        $params = array(
            "send_type" => $send_type,
            "send_param" => $send_param,
            "shop_id" => $shop_id
        );
        $result = runhook("Notify", "forgotPassword", $params);
        $key = md5('@' . $key . '-');
        $data['code'] = $result['param'];
        Cache::set($key, $data, 300);
        
        if (empty($result)) {
            $result = [
                'code' => - 1,
                'message' => "发送失败"
            ];
        } elseif ($result['code'] == 0) {
            $result = [
                'code' => 0,
                'message' => "发送成功"
            ];
        } else {
            $result = [
                'code' => $result['code'],
                'message' => $result['message']
            ];
        }
        return $this->outMessage($title, $result);
    }

    /**
     * 忘记修改密码账号验证
     */
    public function findPasswd($type, $info)
    {
        $exist = false;
        $member = new MemberService();
        if ($type == "mobile") {
            $exist = $member->memberIsMobile($info);
        } else if ($type == "email") {
            $exist = $member->memberIsEmail($info);
        }
        return $exist;
    }

    /**
     * 系统加密方法
     *
     * @param string $data
     *            要加密的字符串
     * @param string $key
     *            加密密钥
     * @param int $expire
     *            过期时间 单位 秒
     * @return string
     * @author 麦当苗儿 <zuojiazi@vip.qq.com>
     */
    public function niuEncrypt($data, $key = '', $expire = 0)
    {
        $key = md5(empty($key) ? $this->auth_key : $key);
        $data = base64_encode($data);
        $x = 0;
        $len = strlen($data);
        $l = strlen($key);
        $char = '';
        
        for ($i = 0; $i < $len; $i ++) {
            if ($x == $l)
                $x = 0;
            $char .= substr($key, $x, 1);
            $x ++;
        }
        
        $str = sprintf('%010d', $expire ? $expire + time() : 0);
        
        for ($i = 0; $i < $len; $i ++) {
            $str .= chr(ord(substr($data, $i, 1)) + (ord(substr($char, $i, 1))) % 256);
        }
        return str_replace(array(
            '+',
            '/',
            '='
        ), array(
            '-',
            '_',
            ''
        ), base64_encode($str));
    }

    /**
     * 返回信息
     *
     * @param unknown $res            
     * @return \think\response\Json
     */
    public function outMessage($title, $data, $code = 0, $message = "success")
    {
        $api_result = array();
        $api_result["code"] = $code;
        if ($data === "") {
            $data = null;
        }
        $api_result['data'] = $data;
        $api_result['message'] = $message;
        $api_result['title'] = $title;
        
        if ($api_result) {
            return json_encode($api_result);
        } else {
            abort(404);
        }
    }

    /**
     * 判断手机号是否存在
     */
    public function mobile()
    {
        // 获取数据库中的用户列表
        $title = '判断手机号是否存在';
        $user_mobile = request()->post('mobile', '');
        $member = new MemberService();
        $exist = $member->memberIsMobile($user_mobile);
        return $this->outMessage($title, $exist);
    }

    /**
     * 判断邮箱是否存在
     */
    public function email()
    {
        // 获取数据库中的用户列表
        $title = '判断邮箱是否存在';
        $user_email = request()->post('email', '');
        $member = new MemberService();
        $exist = $member->memberIsEmail($user_email);
        return $this->outMessage($title, $exist);
    }
}