<?php
/**
 * User.php
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
 * @date : 2015.4.24
 * @version : v1.0.0.0
 */
namespace data\service;



class WechatApplet extends User
{

    function __construct()
    {
        parent::__construct();
        
    }
    /**
     * 微信unionid登录(non-PHPdoc)
     * @see \ata\api\IUser::wchatUnionLogin()
     */
    public function wchatAppUnionLogin($unionid)
    {
        $this->Logout();
        $condition = array(
            'wx_unionid' => $unionid
        );
        $user_info = $this->user->getInfo($condition, $field = 'uid,user_status,user_name,is_system,instance_id,is_member, current_login_ip, current_login_time, current_login_type');
        if (! empty($user_info)) {
            if ($user_info['user_status'] == 0) {
                return USER_LOCK;
            } else {
                $this->initLoginInfo($user_info);
                return 1;
            }
        } else
            return USER_NBUND;
    }
    /*
     * 微信第三方登录(non-PHPdoc)
     * @see \data\api\IMember::wchatLogin()
     */
    public function wchatAppLogin($openid)
    {
        $this->Logout();
        $condition = array(
            'wx_openid' => $openid
        );
        $user_info = $this->user->getInfo($condition, $field = 'uid,user_status,user_name,is_system,instance_id,is_member, current_login_ip, current_login_time, current_login_type');
        if (! empty($user_info)) {
            if ($user_info['user_status'] == 0) {
                return USER_LOCK;
            } else {
                
                return $user_info;
            }
        } else
            return USER_NBUND;
        // TODO Auto-generated method stub
    }
}

