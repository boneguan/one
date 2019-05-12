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
/**
 * 后台登录控制器
 */
namespace app\admin\controller;

\think\Loader::addNamespace('data', 'data/');
use data\service\AdminUser as AdminUser;
use data\service\Config as WebConfig;
use data\service\WebSite as WebSite;
use think\Controller;
use think\Session;

class Login extends Controller
{

    public $user;

    /**
     * 当前版本的路径
     *
     * @var string
     */
    public $style;
    
    // 验证码配置
    public $login_verify_code;

    public function __construct()
    {
        parent::__construct();
        $this->init();
    }

    private function init()
    {
        $this->user = new AdminUser();
        $web_site = new WebSite();
        $web_info = $web_site->getWebSiteInfo();
        switch ($web_info['style_id_admin']) {
            case 3:
                $this->style = STYLE_DEFAULT_ADMIN . '/';
                $this->assign("style", STYLE_DEFAULT_ADMIN);
                break;
            case 4:
                $this->style = STYLE_BLUE_ADMIN . '/';
                $this->assign("style", STYLE_BLUE_ADMIN);
                break;
            default:
                $this->style = STYLE_BLUE_ADMIN . '/';
                $this->assign("style", STYLE_BLUE_ADMIN);
                break;
        }
        $this->assign("title_name", $web_info['title']);
        // 是否开启qq跟微信
        // 是否开启qq跟微信
        $web_config = new WebConfig();
        $instance_id = 0;
        $qq_info = $web_config->getQQConfig($instance_id);
        $Wchat_info = $web_config->getWchatConfig($instance_id);
        $this->assign("qq_info", $qq_info);
        $this->assign("Wchat_info", $Wchat_info);
        
        if (! empty($web_info['picture']['logo'])) {
            $this->assign("logo", $web_info['picture']['logo']);
        } else {
            $this->assign("logo", 'public/admin/images/shop_login_logo.png');
        }
        $this->login_verify_code = $web_config->getLoginVerifyCodeConfig($instance_id);
        if(!isset($this->login_verify_code['value']['error_num'])){
            $this->login_verify_code['value']['error_num'] = 0;
        }
        $this->assign("login_verify_code", $this->login_verify_code["value"]);
    }

    public function index()
    {
        $is_need_verification = $this->isNeedVerification();
        $this->assign('is_need_verification', $is_need_verification);
        return view($this->style . 'Login/login');
    }

    public function admin_login()
    {
        return view($this->style . 'login/login');
    }

    /**
     * 用户登录
     *
     * @return number
     */
    public function login()
    {
        $user_name = request()->post('userName','');
        $password = request()->post('password','');
        if ($this->isNeedVerification()) {
            $vertification = request()->post('vertification','');
            if (! captcha_check($vertification)) {
                $retval = [
                    'code' => 0,
                    'message' => "验证码错误"
                ];
                return $retval;
            }
        }
        $retval = $this->user->login($user_name, $password);
        if($retval < 0){
            $err_num = $this->getLoginErrorNum();
            $err_num += 1;
            $this->setLoginErrorNum($err_num);
        }else{
            $err_num = 0;
            $this->clearLoginErrorNum();
        }
        $result = AjaxReturn($retval);
        $result['error_num'] = $err_num;
        return $result;
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        $this->user->Logout();
        $redirect = __URL(__URL__ . '/' . ADMIN_MODULE . "/login");
        $this->redirect($redirect);
    }

    /**
     * 用户修改密码
     */
    public function ModifyPassword()
    {
        $uid = $this->user->getSessionUid();
        $old_pass = request()->post('old_pass','');
        $new_pass = request()->post('new_pass','');
        $retval = $this->user->ModifyUserPassword($uid, $old_pass, $new_pass);
        return AjaxReturn($retval);
    }
    
    /**
     * 判断是否需要验证
     * @return boolean
     */
    public function isNeedVerification(){
        if ($this->login_verify_code["value"]["admin"] == 1 && $this->login_verify_code["value"]["error_num"] == 0) {
            return true;
        }elseif($this->login_verify_code["value"]["admin"] == 1 && $this->login_verify_code["value"]["error_num"] > 0){
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
            Session::set($ip.'admin_error_num', 0);
        }
        return $error_num;
    }
    
    /**
     * 设置当前ip登录错误次数
     * @param unknown $error_num
     */
    public function setLoginErrorNum($error_num){
        $ip = request()->ip();
        Session::set($ip.'admin_error_num', $error_num);
    }
    
    /**
     * 清除当前ip登录错误次数
     * @param unknown $error_num
     */
    public function clearLoginErrorNum(){
        $ip = request()->ip();
        Session::delete($ip.'admin_error_num');
    }
}
