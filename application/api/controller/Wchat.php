<?php
/**
 * Index.php
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
use data\extend\WchatOauth;

class Wchat extends BaseController
{

    public function getFansInfo()
    {
        $title = "获取微信粉丝信息，注意只能在微信浏览器";
        $url = request()->request("url", "");
        if (! empty($url)) {
            $_SESSION['request_url'] = $url;
        } else {
            return $this->out_message($title, "", "-50", "未获取到要返回的url");
        }
        // 微信浏览器自动登录
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $config = new Config();
            $wchat_config = $config->getInstanceWchatConfig(0);
            if (empty($wchat_config['value']['appid'])) {
                $out_message = $this->outMessage($title, "", "-50", "当前系统未设置微信公众号！");
            }
            $wchat_oauth = new WchatOauth();
            $domain_name = \think\Request::instance()->domain();
            if (! empty($_COOKIE[$domain_name . "member_access_token"])) {
                $token = json_decode($_COOKIE[$domain_name . "member_access_token"], true);
            } else {
                $token = $wchat_oauth->get_member_access_token();
                if (! empty($token['access_token'])) {
                    setcookie($domain_name . "member_access_token", json_encode($token));
                    $info = $wchat_oauth->get_oauth_member_info($token);
                    $out_message = $this->outMessage($title, $info);
                }
            }
        } else {
            $out_message = $this->outMessage($title, "", "-50", "请在微信浏览器登录");
        }
        if (! empty($out_message)) {
            $this->redirect($_SESSION['request_url'] . '?message=' . json_encode($out_message));
        }
    }

    /**
     * 获取分享相关票据
     */
    public function getShareTicket()
    {
        $title = "获取微信票据";
        $url = request()->request("url", "");
        $request_url = request()->request("request_url", "");
        if (! empty($url)) {
            $_SESSION['request_url'] = $request_url;
        } else {
            return $this->outMessage($title, "", "-50", "未获取到要返回的url");
        }
        
        $config = new Config();
        $auth_info = $config->getInstanceWchatConfig(0);
        // 获取票据
        if (! empty($auth_info['value']['appid'])) {
            // 针对单店版获取微信票据
            $wexin_auth = new WchatOauth();
            $signPackage['appId'] = $auth_info['value']['appid'];
            $signPackage['jsTimesTamp'] = time();
            $signPackage['jsNonceStr'] = $wexin_auth->get_nonce_str();
            $jsapi_ticket = $wexin_auth->jsapi_ticket();
            $signPackage['ticket'] = $jsapi_ticket;
            $Parameters = "jsapi_ticket=" . $signPackage['ticket'] . "&noncestr=" . $signPackage['jsNonceStr'] . "&timestamp=" . $signPackage['jsTimesTamp'] . "&url=" . $url;
            $signPackage['jsSignature'] = sha1($Parameters);
            $out_message = $this->outMessage($title, $signPackage);
        } else {
            $signPackage = array(
                'appId' => '',
                'jsTimesTamp' => '',
                'jsNonceStr' => '',
                'ticket' => '',
                'jsSignature' => ''
            );
            $out_message = $this->outMessage($title, $signPackage, '-9001', "当前微信没有配置!");
        }
        $this->redirect($_SESSION['request_url'] . '?message=' . json_encode($out_message));
    }

    /**
     * 获取ACCESS_TOKEN
     */
    public function getAccessToken($scene, $newurl, $page, $data)
    {
        $config = new Config();
        $applet_config = $config->getInstanceAppletConfig($this->instance_id);
        $appid = '';
        $appsecret = '';
        if (! empty($applet_config["value"])) {
            $appid = $applet_config["value"]['appid'];
            $appsecret = $applet_config["value"]['appsecret'];
        } else {
            return - 50;
        }
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $appid . '&secret=' . $appsecret;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $a = curl_exec($ch);
        $strjson = json_decode($a);
        if ($strjson == false || empty($strjson)) {
            return '';
        } else {
            $token = $strjson->access_token;
            $url = $newurl . $token;
            return $this->get_url_return($url, $data);
        }
    }

    /**
     * 微信数据获取
     *
     * @param unknown $url            
     * @param unknown $data            
     * @param string $needToken            
     * @return string|unknown
     */
    private function get_url_return($url, $data = '')
    {
        $curl = curl_init(); // 创建一个新url资源
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (! empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $AjaxReturn = curl_exec($curl);
        curl_close($curl);
        $strjson = json_decode($AjaxReturn);
        if (! empty($strjson->errcode)) {
            switch ($strjson->errcode) {
                case 40001:
                    return $this->get_url_return($url, $data); // 获取access_token时AppSecret错误，或者access_token无效
                    break;
                case 40014:
                    return $this->get_url_return($url, $data); // 不合法的access_token
                    break;
                case 42001:
                    return $this->get_url_return($url, $data); // access_token超时
                    break;
                case 45009:
                    return json_encode(array(
                        "code" => - 10,
                        "message" => "接口调用超过限制：" . $strjson->errmsg
                    ));
                    break;
                case 41001:
                    return json_encode(array(
                        "code" => - 10,
                        "message" => "缺少access_token参数：" . $strjson->errmsg
                    ));
                    break;
                default:
                    return json_encode(array(
                        "code" => - 10,
                        "message" => $strjson->errmsg
                    )); // 其他错误，抛出
                    break;
            }
        } else {
            return $AjaxReturn;
        }
    }
}
