<?php
/**
 * BaseController.php
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

\think\Loader::addNamespace('data', 'data/');
use think\Controller;
use think\Request;
use data\service\Address;
use think\Session;
use data\service\Config;
use data\service\WebSite;
use data\service\User;
use data\service\Member;
use app\wap\controller\Task;

class BaseController extends Controller
{

    public $api_result;

    public $user;

    protected $shop_name;

    protected $uid;

    protected $instance_id;

    protected $share_icon;

    public $web_site;

    protected $auth_key = 'addexdfsdfewfscvsrdf!@#';

    /**
     * 当前版本的路径
     *
     * @var string
     */
    public function __construct()
    {
        parent::__construct();
        $this->instance_id = 0;
        $this->api_result = new ApiResult();
        $this->web_site = new WebSite();
        $this->user = new Member();
        $web_info = $this->web_site->getWebSiteInfo();
        $this->share_icon = $web_info['web_wechat_share_logo'];
        $this->uid = $this->user->getSessionUid();
        $this->instance_id = $this->user->getSessionInstanceId();
        $this->shop_id = 0;
        $this->shop_name = $this->user->getInstanceName();
        $this->logo = $web_info['logo'];
        $this->checkToken();
        $task = new Task();
        $task->load_task();
    }

    /**
     * 检测token
     */
    public function checkToken()
    {
        $token = request()->post("token", "");
        if (! empty($token)) {
            $data = $this->niuDecrypt($token);
            $data = json_decode($data, true);
            if (! empty($data['uid'])) {
                $this->uid = $data["uid"];
                $model = $this->getRequestModel();
                $user = new User();
                $user_info = $user->getUserInfoByUid($this->uid);
                Session::set($model . 'uid', $user_info['uid']);
                Session::set($model . $user_info['uid'] . 'from', 'WECHATAPPLET');
                Session::set($model . 'is_system', $user_info['is_system']);
                Session::set($model . 'is_member', $user_info['is_member']);
                Session::set($model . 'instance_id', $user_info['instance_id']);
            } else {
                return $this->outMessage("token", "", - 50, "token已失效!");
                exit();
            }
        }
        // else{
        // echo $this->outMessage("token", "", -1, "token验证失败!");
        // exit;
        // }
    }

    /**
     * 返回信息
     *
     * @param unknown $res            
     * @return \think\response\Json
     */
    public function outMessage($title, $data, $code = 0, $message = "success")
    {
        $this->api_result->code = $code;
        $this->api_result->data = $data;
        $this->api_result->message = $message;
        $this->api_result->title = $title;
        
        if ($this->api_result) {
            return json_encode($this->api_result);
        } else {
            abort(404);
        }
    }

    /**
     * 系统解密方法
     *
     * @param string $data
     *            要解密的字符串 （必须是think_encrypt方法加密的字符串）
     * @param string $key
     *            加密密钥
     * @return string
     * @author 麦当苗儿 <zuojiazi@vip.qq.com>
     */
    public function niuDecrypt($data, $key = '')
    {
        $key = md5(empty($key) ? $this->auth_key : $key);
        $data = str_replace(array(
            '-',
            '_'
        ), array(
            '+',
            '/'
        ), $data);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        $data = base64_decode($data);
        $expire = substr($data, 0, 10);
        $data = substr($data, 10);
        
        if ($expire > 0 && $expire < time()) {
            return '';
        }
        $x = 0;
        $len = strlen($data);
        $l = strlen($key);
        $char = $str = '';
        
        for ($i = 0; $i < $len; $i ++) {
            if ($x == $l)
                $x = 0;
            $char .= substr($key, $x, 1);
            $x ++;
        }
        
        for ($i = 0; $i < $len; $i ++) {
            if (ord(substr($data, $i, 1)) < ord(substr($char, $i, 1))) {
                $str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
            } else {
                $str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
            }
        }
        return base64_decode($str);
    }

    /**
     * 获取省列表
     */
    public function getProvince()
    {
        $address = new Address();
        $title = '省列表';
        $province_list = $address->getProvinceList();
        return $this->outMessage($title, $province_list);
    }

    /**
     * 获取城市列表
     *
     * @return Ambigous <multitype:\think\static , \think\false, \think\Collection, \think\db\false, PDOStatement, string, \PDOStatement, \think\db\mixed, boolean, unknown, \think\mixed, multitype:, array>
     */
    public function getCity()
    {
        $address = new Address();
        $title = '城市列表';
        $province_id = request()->post('province_id', 0);
        $city_list = $address->getCityList($province_id);
        return $this->outMessage($title, $city_list);
    }

    /**
     * 获取区域地址
     */
    public function getDistrict()
    {
        $address = new Address();
        $title = '区县列表';
        $city_id = request()->post('city_id', 0);
        $district_list = $address->getDistrictList($city_id);
        return $this->outMessage($title, $district_list);
    }

    /**
     * 获取model
     *
     * @return Ambigous <string, \think\Request>
     */
    public function getRequestModel()
    {
        $model = Request::instance()->module();
        if ($model == 'shop' || $model == 'wap') {
            $model = 'app';
        }
        return $model;
    }

    /**
     * 是否开启虚拟商品功能，0：禁用，1：开启
     */
    public function getIsOpenVirtualGoodsConfig()
    {
        $config = new Config();
        $res = $config->getIsOpenVirtualGoodsConfig($this->instance_id);
        return $res;
    }

    /**
     * 网站配置信息
     */
    public function getWebSiteInfo()
    {
        $title = '网站配置信息';
        $this->web_site = new WebSite();
        $config = new Config();
        $web_info = $this->web_site->getWebSiteInfo();
        $web_info['custom_template_is_enable'] = 0;
        $web_info['is_support_pintuan'] = IS_SUPPORT_PINTUAN;
        $web_info['is_support_bargain'] = IS_SUPPORT_BARGAIN;
        return $this->outMessage($title, $web_info);
    }

    public function getAppUpgradeInfo()
    {
        $title = "获取最新版App信息";
        $app_type = request()->post("app_type", "");
        if (empty($app_type)) {
            return $this->outMessage($title, null, - 1, "缺少字段app_type");
        }
        $config = new Config();
        $res = $config->getLatestAppVersionInfo($app_type);
        if (! empty($res)) {
            if (! empty($res['download_address'])) {
                if (strpos($res['download_address'], "http://") === false && strpos($res['download_address'], "https://") === false) {
                    $res['download_address'] = getBaseUrl() . "/" . $res['download_address'];
                }
            }
            return $this->outMessage($title, $res);
        } else {
            return $this->outMessage($title, null, - 1, "暂无更新");
        }
    }

    public function getAppWelcomePageConfig()
    {
        $title = "获取App欢迎页配置";
        $config = new Config();
        $res = $config->getAppWelcomePageConfig(0);
        if (! empty($res['value']['welcome_page_picture'])) {
            if (strpos($res['value']['welcome_page_picture'], "http://") === false && strpos($res['value']['welcome_page_picture'], "https://") === false) {
                $res['value']['welcome_page_picture'] = getBaseUrl() . "/" . $res['value']['welcome_page_picture'];
            }
        }
        return $this->outMessage($title, $res['value']);
    }
}

class ApiResult
{

    public $code;

    public $message;

    public $data;

    public $title;

    public function __construct()
    {
        $this->code = 0;
        $this->title = '';
        $this->message = "success";
        $this->data = null;
    }
}