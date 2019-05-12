<?php
/**
 * Member.php
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
use data\service\Member\MemberAccount as MemberAccount;
use data\service\Member as MemberService;
use data\service\Order as OrderService;
use data\service\Platform;
use data\service\promotion\PromoteRewardRule;
use think;
use data\service\UnifyPay;
use data\service\Verification;
use data\service\Goods;
use think\Cache;
use data\service\WebSite;
use data\service\Weixin;
use data\service\NfxPromoter;
use data\service\NfxShopConfig;
use data\service\Address;

/**
 * 会员
 *
 * @author Administrator
 *        
 */
class Member extends BaseController
{

    public $notice;

    public $login_verify_code;

    public function __construct()
    {
        parent::__construct();
        // 是否开启验证码
        $web_config = new Config();
        $this->login_verify_code = $web_config->getLoginVerifyCodeConfig($this->instance_id);
        // 是否开启通知
        $instance_id = 0;
        $noticeMobile = $web_config->getNoticeMobileConfig($instance_id);
        $noticeEmail = $web_config->getNoticeEmailConfig($instance_id);
        $this->notice['noticeEmail'] = $noticeEmail[0]['is_use'];
        $this->notice['noticeMobile'] = $noticeMobile[0]['is_use'];
    }

    /**
     * 查询是否开启验证码
     *
     * @return Ambigous <\think\response\Json, \think\Response, \think\response\View, \think\response\Xml, \think\response\Redirect, \think\response\Jsonp, unknown, \think\Response>
     */
    public function getLoginVerifyCodeConfig()
    {
        $title = "查询是否开启验证码";
        $web_config = new Config();
        $login_verify_code = $web_config->getLoginVerifyCodeConfig(0);
        return $this->outMessage($title, $login_verify_code);
    }

    /**
     * 查询是否开启通知
     *
     * @return Ambigous <\think\response\Json, \think\Response, \think\response\View, \think\response\Xml, \think\response\Redirect, \think\response\Jsonp, unknown, \think\Response>
     */
    public function getNoticeConfig()
    {
        $title = "查询通知是否开启";
        $web_config = new Config();
        $noticeMobile = $web_config->getNoticeMobileConfig(0);
        $noticeEmail = $web_config->getNoticeEmailConfig(0);
        $notice['noticeEmail'] = $noticeEmail[0]['is_use'];
        $notice['noticeMobile'] = $noticeMobile[0]['is_use'];
        return $this->outMessage($title, $notice);
    }

    /**
     * 获取会员详细信息
     */
    public function getMemberDetail()
    {
        $title = "获取会员详细信息";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $member = new MemberService();
        $member_info = $member->getMemberDetail($this->instance_id);
        return $this->outMessage($title, $member_info);
    }

    /**
     * 获取会员中心首页广告位
     */
    public function getMemberIndexAdv()
    {
        $title = "获取会员中心首页广告位";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $platform = new Platform();
        $index_adv = $platform->getPlatformAdvPositionDetail(1152);
        return $this->outMessage($title, $index_adv);
    }

    /**
     * 添加账户流水
     */
    public function addMemberAccountData()
    {
        $title = '添加账户流水';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $member_account = new MemberAccount();
        $account_type = request()->post('account_type', '');
        $sign = request()->post('sign', '');
        $number = request()->post('number', '');
        $from_type = request()->post('from_type', '');
        $data_id = request()->post('data_id', '');
        $text = request()->post('text', '');
        $res = $member_account->addMemberAccountData($this->instance_id, $account_type, $this->uid, $sign, $number, $from_type, $data_id, $text);
        return $this->outMessage($title, $res);
    }

    /**
     * 会员中心
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function index()
    {
        $title = '会员中心';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        switch (NS_VERSION) {
            case NS_VER_B2C:
                $retval = $this->memberIndex(); // 单店B2C版
                break;
            case NS_VER_B2C_FX:
                $retval = $this->memberIndexFx(); // 单店B2C分销版
                break;
        }
        return $this->outMessage($title, $retval);
    }
    
    /*
     * 单店B2C版
     */
    public function memberIndex()
    {
        $member = new MemberService();
        $platform = new Platform();
        
        // 查询用户是否是本店核销员
        $verification_service = new Verification();
        $is_verification = $verification_service->getShopVerificationInfo($this->uid, $this->instance_id);
        $data['is_verification'] = $is_verification;
        
        // 商城是否开启虚拟商品
        $is_open_virtual_goods = $this->getIsOpenVirtualGoodsConfig($this->instance_id);
        $data['is_open_virtual_goods'] = $is_open_virtual_goods;
        
        // 商城是否开启拼团
        $is_support_pintuan = IS_SUPPORT_PINTUAN;
        $data['is_support_pintuan'] = $is_support_pintuan;
        
        // 商城是否开启砍价
        $is_support_bargain = IS_SUPPORT_BARGAIN;
        $data['is_support_bargain'] = $is_support_bargain;
        
        // 判断是否开启了签到送积分
        $config = new Config();
        $integralconfig = $config->getIntegralConfig($this->instance_id);
        $data['integralConfig'] = $integralconfig;
        
        // 判断用户是否签到
        $dataMember = new MemberService();
        $isSign = $dataMember->getIsMemberSign($this->uid, $this->instance_id);
        $data['isSign'] = $isSign;
        
        // 待支付订单数量
        $order = new OrderService();
        $unpaidOrder = $order->getOrderNumByOrderStatu([
            'order_status' => 0,
            "buyer_id" => $this->uid,
            'order_type' => array(
                "in",
                "1,3"
            )
        ]);
        $data['unpaidOrder'] = $unpaidOrder;
        
        // 待发货订单数量
        $shipmentPendingOrder = $order->getOrderNumByOrderStatu([
            'order_status' => 1,
            "buyer_id" => $this->uid,
            'order_type' => array(
                "in",
                "1,3"
            )
        ]);
        $data['shipmentPendingOrder'] = $shipmentPendingOrder;
        
        // 待收货订单数量
        $goodsNotReceivedOrder = $order->getOrderNumByOrderStatu([
            'order_status' => 2,
            "buyer_id" => $this->uid,
            'order_type' => array(
                "in",
                "1,3"
            )
        ]);
        $data['goodsNotReceivedOrder'] = $goodsNotReceivedOrder;
        
        // 退款订单
        $refundOrder = $order->getOrderNumByOrderStatu([
            'order_status' => array(
                'in',
                [
                    - 1,
                    - 2
                ]
            ),
            "buyer_id" => $this->uid,
            'order_type' => array(
                "in",
                "1,3"
            )
        ]);
        $data['refundOrder'] = $refundOrder;
        
        return $data;
    }
    
    /*
     * 单店B2C版
     */
    public function memberIndexFx()
    {
        $member = new MemberService();
        $platform = new Platform();
        
        // 查询用户是否是本店核销员
        $verification_service = new Verification();
        $is_verification = $verification_service->getShopVerificationInfo($this->uid, $this->instance_id);
        $data['is_verification'] = $is_verification;
        
        // 商城是否开启虚拟商品
        $is_open_virtual_goods = $this->getIsOpenVirtualGoodsConfig($this->instance_id);
        $data['is_open_virtual_goods'] = $is_open_virtual_goods;
        
        // 商城是否开启拼团
        $is_support_pintuan = IS_SUPPORT_PINTUAN;
        $data['is_support_pintuan'] = $is_support_pintuan;
        
        // 商城是否开启砍价
        $is_support_bargain = IS_SUPPORT_BARGAIN;
        $data['is_support_bargain'] = $is_support_bargain;
        
        // 判断是否开启了签到送积分
        $config = new Config();
        $integralconfig = $config->getIntegralConfig($this->instance_id);
        $data['integralConfig'] = $integralconfig;
        
        // 判断用户是否签到
        $dataMember = new MemberService();
        $isSign = $dataMember->getIsMemberSign($this->uid, $this->instance_id);
        $data['isSign'] = $isSign;
        
        // 待支付订单数量
        $order = new OrderService();
        $unpaidOrder = $order->getOrderNumByOrderStatu([
            'order_status' => 0,
            "buyer_id" => $this->uid,
            'order_type' => array(
                "in",
                "1,3"
            )
        ]);
        $data['unpaidOrder'] = $unpaidOrder;
        
        // 待发货订单数量
        $shipmentPendingOrder = $order->getOrderNumByOrderStatu([
            'order_status' => 1,
            "buyer_id" => $this->uid,
            'order_type' => array(
                "in",
                "1,3"
            )
        ]);
        $data['shipmentPendingOrder'] = $shipmentPendingOrder;
        
        // 待收货订单数量
        $goodsNotReceivedOrder = $order->getOrderNumByOrderStatu([
            'order_status' => 2,
            "buyer_id" => $this->uid,
            'order_type' => array(
                "in",
                "1,3"
            )
        ]);
        $data['goodsNotReceivedOrder'] = $goodsNotReceivedOrder;
        
        // 退款订单
        $refundOrder = $order->getOrderNumByOrderStatu([
            'order_status' => array(
                'in',
                [
                    - 1,
                    - 2
                ]
            ),
            "buyer_id" => $this->uid,
            'order_type' => array(
                "in",
                "1,3"
            )
        ]);
        $data['refundOrder'] = $refundOrder;
        $nfx_promoter = new NfxPromoter();
        $nfx_shop_config = new NfxShopConfig();
        // 推广信息
        $apply_promoter_menu = '';
        $promoter_center = ""; // 推广中心
        
        $promoter_info = $nfx_promoter->getUserPromoter($this->uid, $this->instance_id);
        // 平台/店铺 会员中心
        $shop_config = $nfx_shop_config->getShopConfigDetail($this->instance_id);
        // 店铺详情类型
        $promoter_detail = null;
        if (empty($promoter_info)) {
            $apply_promoter_menu = 'distribution/applypromoter/applypromoter';
            $promoter_center = 'distribution/applypromoter/applypromoter';
        } else {
            if ((empty($promoter_info['is_audit']) || $promoter_info['is_audit'] == - 1) && $shop_config['is_distribution_enable'] == 1) {
                $apply_promoter_menu = 'distribution/applypromoter/applypromoter';
                $promoter_center = 'distribution/applypromoter/applypromoter';
            } elseif ($promoter_info['is_audit'] == 1) { // 通过显示推广中心
                $promoter_detail = $nfx_promoter->getPromoterDetail($promoter_info['promoter_id']);
                $promoter_center = 'distribution/distributioncenter/distributioncenter';
            }
        }
        $data['promoter_info'] = array(
            'apply_promoter_menu' => $apply_promoter_menu,
            'promoter_detail' => $promoter_detail,
            'promoter_center' => $promoter_center
        );
        return $data;
    }

    /**
     * 制作推广二维码
     */
    function showUserQrcode()
    {
        $title = '获取推广二维码';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $uid = $this->uid;
        $instance_id = $this->instance_id;
        // 读取生成图片的位置配置
        $config = new Config();
        $weixin = new Weixin();
        $data = $weixin->getWeixinQrcodeConfig($instance_id, $uid);
        $member_info = $this->user->getUserDetail($uid);
        // 获取所在店铺信息
        $web = new WebSite();
        $shop_info = $web->getWebDetail();
        $shop_logo = $shop_info["logo"];
        
        // 获取默认头像
        $defaultImages = $config->getDefaultImages($this->instance_id);
        
        $upload_path = "upload/qrcode/promote_qrcode/applet_user"; // 推广二维码手机端展示
        if (! file_exists($upload_path)) {
            $mode = intval('0777', 8);
            mkdir($upload_path, $mode, true);
        }
        $wchat = new Wchat();
        $url = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=';
        $scene = 'sourceid_' . $uid;
        $page = 'pages/index/index';
        $wchat_data = array(
            'scene' => $scene,
            'page' => $page,
            'auto_color' => true
        );
        $wchat_data = json_encode($wchat_data);
        $path = $wchat->getAccessToken($scene, $url, $page, $wchat_data);
        if ($path == - 50) {
            return $this->outMessage($title, '', - 50, '商家未配置小程序');
        }
        if (strlen($path) > 1000) {
            // 检测文件夹是否存在，不存在则创建文件夹
            $file_path = UPLOAD . '/qrcode/virtual_qrcode/';
            if (! file_exists($file_path)) {
                $mode = intval('0777', 8);
                mkdir($file_path, $mode, true);
            }
            $file_path = $file_path . md5(rand(0, 1000) . time() . rand(0, 1000)) . '_qrcode.png';
            if (file_put_contents($file_path, $path)) {
                $path = $file_path;
            } else {
                return $this->outMessage($title, null, - 10, '二维码生成失败');
            }
        } else {
            if (is_array(json_decode($path, true))) {
                $path = ': ' . json_decode($path, true)['message'];
            } else {
                $path = '';
            }
            return $this->outMessage($title, null, - 10, '二维码生成失败' . $path);
        }
        
        // 定义中继二维码地址
        $thumb_qrcode = $upload_path . '/thumb_' . 'qrcode_' . $uid . '_' . $instance_id . '.png';
        $image = \think\Image::open($path);
        // 生成一个固定大小为360*360的缩略图并保存为thumb_....jpg
        $image->thumb(288, 288, \think\Image::THUMB_CENTER)->save($thumb_qrcode);
        // 背景图片
        $dst = $data["background"];
        if (! strstr(dst, "http://") && ! strstr(dst, "https://")) {
            if (! file_exists($dst)) {
                $dst = "public/static/images/qrcode_bg/qrcode_user_bg.png";
            }
        }
        // 生成画布
        list ($max_width, $max_height) = getimagesize($dst);
        $dests = imagecreatetruecolor($max_width, $max_height);
        $dst_im = getImgCreateFrom($dst);
        imagecopy($dests, $dst_im, 0, 0, 0, 0, $max_width, $max_height);
        imagedestroy($dst_im);
        // 并入二维码
        $src_im = getImgCreateFrom($thumb_qrcode);
        $src_info = getimagesize($thumb_qrcode);
        imagecopy($dests, $src_im, $data["code_left"] * 2, $data["code_top"] * 2, 0, 0, $src_info[0], $src_info[1]);
        imagedestroy($src_im);
        
        // 并入用户头像
        $user_headimg = $member_info["user_headimg"];
        // $user_headimg = "upload/user/1493363991571.png";
        if (! strstr($user_headimg, "http://") && ! strstr($user_headimg, "https://")) {
            if (! file_exists($user_headimg)) {
                $user_headimg = $defaultImages["value"]["default_headimg"];
            }
        }
        $src_im_1 = getImgCreateFrom($user_headimg);
        
        if (empty($src_im_1)) {
            $user_headimg = $defaultImages["value"]["default_headimg"];
            $src_im_1 = getImgCreateFrom($user_headimg);
        }
        $src_info_1 = getimagesize($user_headimg);
        imagecopyresampled($dests, $src_im_1, $data['header_left'] * 2, $data['header_top'] * 2, 0, 0, 80, 80, $src_info_1[0], $src_info_1[1]);
        imagedestroy($src_im_1);
        
        // 并入网站logo
        if ($data['is_logo_show'] == '1') {
            if (! strstr($shop_logo, "http://") && ! strstr($shop_logo, "https://")) {
                if (! file_exists($shop_logo)) {
                    $shop_logo = "public/static/images/logo.png";
                }
            }
            $src_im_2 = getImgCreateFrom($shop_logo);
            $src_info_2 = getimagesize($shop_logo);
            imagecopy($dests, $src_im_2, $data['logo_left'] * 2, $data['logo_top'] * 2, 0, 0, $src_info_2[0], $src_info_2[1]);
            imagedestroy($src_im_2);
        }
        // 并入用户姓名
        $rgb = hColor2RGB($data['nick_font_color']);
        $bg = imagecolorallocate($dests, $rgb['r'], $rgb['g'], $rgb['b']);
        $name_top_size = $data['name_top'] * 2 + $data['nick_font_size'];
        @imagefttext($dests, $data['nick_font_size'], 0, $data['name_left'] * 2, $name_top_size, $bg, "public/static/font/Microsoft.ttf", $member_info["nick_name"]);
        @unlink($path);
        ob_clean();
        imagejpeg($dests, $thumb_qrcode);
        return $this->outMessage($title, $thumb_qrcode);
    }

    /**
     * 会员地址管理
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function getMemberAddressList()
    {
        $title = "获取会员地址";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $applet_member = new MemberService();
        $addresslist = $applet_member->getMemberExpressAddressList(1, 0, [
            'uid' => $this->uid
        ]);
        return $this->outMessage($title, $addresslist);
    }

    /**
     * 添加地址
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function addMemberAddress()
    {
        $title = "添加会员地址,注意传入省市区id";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $applet_member = new MemberService();
        $consigner = request()->post('consigner', '');
        $mobile = request()->post('mobile', '');
        if (empty($mobile)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数mobile");
        }
        $phone = request()->post('phone', '');
        $province = request()->post('province', '');
        if (empty($province)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数province");
        }
        $city = request()->post('city', '');
        if (empty($city)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数city");
        }
        $district = request()->post('district', '');
        $address = request()->post('address', '');
        if (empty($address)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数address");
        }
        $zip_code = request()->post('zip_code', '');
        $alias = request()->post('alias', '');
        $retval = $applet_member->addMemberExpressAddress($consigner, $mobile, $phone, $province, $city, $district, $address, $zip_code, $alias);
        return $this->outMessage($title, $retval);
    }

    /**
     * 修改会员地址
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >|Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function updateMemberAddress()
    {
        $title = "修改会员地址,注意传入省市区id";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $applet_member = new MemberService();
        $id = request()->post('id', '');
        if (empty($id)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数id");
        }
        $consigner = request()->post('consigner', '');
        $mobile = request()->post('mobile', '');
        if (empty($mobile)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数mobile");
        }
        $phone = request()->post('phone', '');
        $province = request()->post('province', '');
        if (empty($province)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数province");
        }
        $city = request()->post('city', '');
        if (empty($city)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数city");
        }
        $district = request()->post('district', '');
        $address = request()->post('address', '');
        if (empty($address)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数address");
        }
        $zip_code = request()->post('zip_code', '');
        $alias = request()->post('alias', '');
        $retval = $applet_member->updateMemberExpressAddress($id, $consigner, $mobile, $phone, $province, $city, $district, $address, $zip_code, $alias);
        return $this->outMessage($title, $retval);
    }

    /**
     * 获取用户地址详情
     *
     * @return Ambigous <\think\static, multitype:, \think\db\false, PDOStatement, string, \think\Model, \PDOStatement, \think\db\mixed, multitype:a r y s t i n g Q u e \ C l o , \think\db\Query, NULL>
     */
    public function getMemberAddressDetail()
    {
        $title = "获取用户地址详情";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $address_id = request()->post('id', 0);
        $applet_member = new MemberService();
        $info = $applet_member->getMemberExpressAddressDetail($address_id);
        return $this->outMessage($title, $info);
    }

    /**
     * 会员地址删除
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function memberAddressDelete()
    {
        $title = "删除会员地址";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $id = request()->post('id', '');
        $applet_member = new MemberService();
        $res = $applet_member->memberAddressDelete($id);
        return $this->outMessage($title, $res);
    }

    /**
     * 修改会员默认地址
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function updateAddressDefault()
    {
        $title = "修改默认会员地址";
        $id = request()->post('id', '');
        $applet_member = new MemberService();
        $res = $applet_member->updateAddressDefault($id);
        return $this->outMessage($title, $res);
    }

    /**
     * 获取会员积分余额账户情况
     */
    public function getMemberAccount()
    {
        // 获取店铺的积分列表
        $title = "获取会员账户,分为平台账户和店铺会员账户";
        $applet_member = new MemberService();
        $account_list = $applet_member->getShopAccountListByUser($this->uid, 1, 0);
        return $this->outMessage($title, $account_list);
    }

    /**
     * 会员账户流水
     */
    public function getMemberAccountRecordsList()
    {
        $title = "获取会员账户流水,分为平台账户和店铺会员账户,余额只有平台账户account_type:1积分2余额";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $shop_id = request()->post("shop_id", 0);
        $account_type = request()->post("account_type", 1);
        $condition['nmar.shop_id'] = $shop_id;
        $condition['nmar.uid'] = $this->uid;
        $condition['nmar.account_type'] = $account_type;
        // 查看用户在该商铺下的积分消费流水
        $member = new MemberService();
        $member_point_list = $member->getAccountList(1, 0, $condition);
        return $this->outMessage($title, $member_point_list);
    }

    /**
     * 余额提现记录
     */
    public function balanceWithdraw()
    {
        $title = "获取会员提现记录";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        // 该店铺下的余额提现记录
        $member = new MemberService();
        $uid = $this->uid;
        $shopid = 0;
        $condition['uid'] = $uid;
        $condition['shop_id'] = $shopid;
        $withdraw_list = $member->getMemberBalanceWithdraw(1, 0, $condition);
        foreach ($withdraw_list['data'] as $k => $v) {
            if ($v['status'] == 1) {
                $withdraw_list['data'][$k]['status'] = '已同意';
            } else 
                if ($v['status'] == 0) {
                    $withdraw_list['data'][$k]['status'] = '已申请';
                } else {
                    $withdraw_list['data'][$k]['status'] = '已拒绝';
                }
        }
        return $this->outMessage($title, $withdraw_list);
    }

    /**
     * 会员优惠券
     */
    public function memberCoupon()
    {
        $title = "会员优惠券列表";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $member = new MemberService();
        $type = request()->post('status', '');
        $shop_id = $this->instance_id;
        $counpon_list = $member->getMemberCounponList($type, $shop_id);
        foreach ($counpon_list as $key => $item) {
            $counpon_list[$key]['start_time'] = date("Y-m-d", $item['start_time']);
            $counpon_list[$key]['end_time'] = date("Y-m-d", $item['end_time']);
        }
        return $this->outMessage($title, $counpon_list);
    }

    /**
     * 修改密码
     */
    public function modifyPassword()
    {
        $title = "会员修改密码";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $member = new MemberService();
        $uid = $this->uid;
        $old_password = request()->post('old_password', '');
        $new_password = request()->post('new_password', '');
        $retval = $this->verifyValue($new_password);
        $flag = $retval[0];
        if ($flag == 0) {
            $retval = $member->ModifyUserPassword($uid, $old_password, $new_password);
            $retval = $retval == - 2005 ? array(
                - 2005,
                '原始密码错误'
            ) : $retval;
        }
        return $this->outMessage($title, $retval);
    }

    /**
     * 密码验证
     */
    public function verifyValue($password)
    {
        $config = new Config();
        $reg_config_info = $config->getRegisterAndVisit($this->instance_id);
        
        // 验证注册
        $reg_config = json_decode($reg_config_info["value"], true);
        // 密码最小长度
        $min_length = $reg_config['pwd_len'];
        $password_len = strlen(trim($password));
        if ($password_len == 0) {
            return array(
                REGISTER_PASSWORD_ERROR,
                '密码不可为空'
            );
        }
        if ($min_length > $password_len) {
            return array(
                REGISTER_PASSWORD_ERROR,
                '密码最小长度为' . $min_length
            );
        }
        if (preg_match("/^[\x{4e00}-\x{9fa5}]+$/u", $password)) {
            return array(
                REGISTER_PASSWORD_ERROR,
                '密码格式错误'
            );
        }
        // 验证密码内容
        if (trim($reg_config['pwd_complexity']) != "") {
            if (stristr($reg_config['pwd_complexity'], "number") !== false) {
                if (! preg_match("/[0-9]/", $password)) {
                    return array(
                        REGISTER_PASSWORD_ERROR,
                        '密码格式错误，密码中必须包含数字'
                    );
                }
            }
            if (stristr($reg_config['pwd_complexity'], "letter") !== false) {
                if (! preg_match("/[a-z]/", $password)) {
                    return array(
                        REGISTER_PASSWORD_ERROR,
                        '密码格式错误，密码中必须包含小写英文字母'
                    );
                }
            }
            if (stristr($reg_config['pwd_complexity'], "upper_case") !== false) {
                if (! preg_match("/[A-Z]/", $password)) {
                    return array(
                        REGISTER_PASSWORD_ERROR,
                        '密码格式错误，密码中必须包含大写英文字母'
                    );
                }
            }
            if (stristr($reg_config['pwd_complexity'], "symbol") !== false) {
                if (! preg_match("/[^A-Za-z0-9]/", $password)) {
                    return array(
                        REGISTER_PASSWORD_ERROR,
                        '密码格式错误，密码中必须包含符号'
                    );
                }
            }
        } else {
            return array(
                0,
                ''
            );
        }
    }

    /**
     * 修改邮箱
     */
    public function modifyEmail()
    {
        $title = "会员修改邮箱";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $member = new MemberService();
        $uid = $this->uid;
        $email = request()->post('email', '');
        $code = request()->post('code', '');
        $key = request()->post('key', '-504*504');
        if (empty($email)) {
            return $this->outMessage($title, "", '-50', "无法获取邮箱信息");
        }
        if ($this->login_verify_code["value"]["pc"] == 1) {
            $res = $this->check_code($code, $key);
            if ($res < 0) {
                return $this->outMessage($title, - 5);
            }
        }
        $retval = $member->modifyEmail($uid, $email);
        return $this->outMessage($title, $retval);
    }

    /**
     * 修改邮箱,App专用，APP的宗旨是操作方便，使用简单，不能太复杂
     * 创建时间：2018年6月13日19:30:18
     */
    public function modifyEmailForApp()
    {
        $title = "会员修改邮箱";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $member = new MemberService();
        $uid = $this->uid;
        $email = request()->post('email', '');
        $code = request()->post('code', '');
        if (empty($email)) {
            return $this->outMessage($title, "", '-50', "无法获取邮箱信息");
        }
        $retval = $member->modifyEmail($uid, $email);
        return $this->outMessage($title, $retval);
    }

    /**
     * 修改手机
     */
    public function modifyMobile()
    {
        $title = "会员修改手机";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $uid = $this->uid;
        $mobile = request()->post('mobile', '');
        $code = request()->post('code', '');
        $key = request()->post('key', '-504*504');
        if (empty($mobile)) {
            return $this->outMessage($title, "", '-50', "无法获取手机号");
        }
        if ($this->login_verify_code["value"]["pc"] == 1) {
            $res = $this->check_code($code, $key);
            if ($res < 0) {
                return $this->outMessage($title, - 5);
            }
        }
        $member = new MemberService();
        $retval = $member->modifyMobile($uid, $mobile);
        return $this->outMessage($title, $retval);
    }

    /**
     * 修改手机，App专用，APP的宗旨是操作方便，使用简单，不能太复杂
     * 创建时间：2018年6月13日19:30:27
     */
    public function modifyMobileForApp()
    {
        $title = "会员修改手机";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $uid = $this->uid;
        $mobile = request()->post('mobile', '');
        $code = request()->post('code', '');
        if (empty($mobile)) {
            return $this->outMessage($title, "", '-50', "无法获取手机号");
        }
        $member = new MemberService();
        $retval = $member->modifyMobile($uid, $mobile);
        return $this->outMessage($title, $retval);
    }

    /**
     * 修改昵称
     *
     * @return unknown[]
     */
    public function modifyNickName()
    {
        $title = "会员修改昵称";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $uid = $this->uid;
        $nickname = request()->post('nickname', '');
        if (empty($nickname)) {
            return $this->outMessage($title, "", '-50', "无法获取昵称信息");
        }
        $member = new MemberService();
        $retval = $member->modifyNickName($uid, $nickname);
        return $this->outMessage($title, $retval);
    }

    /**
     * 积分兑换余额
     *
     * @return \think\response\View
     */
    public function ajaxIntegralExchangeBalance()
    {
        $title = "积分兑换余额";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $point = request()->post('amount', 0);
        $point = (float) $point;
        $shop_id = request()->post('shop_id', 0);
        $result = $this->user->memberPointToBalance($this->uid, $shop_id, $point);
        return $this->outMessage($title, $result);
    }

    /**
     * 获取提现配置
     */
    public function getBalanceConfig()
    {
        $title = "获取提现配置";
        $config = new Config();
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        
        $balanceConfig = $config->getBalanceWithdrawConfig($this->instance_id);
        $balanceConfig = $balanceConfig['value']['withdraw_account'];
        return $this->outMessage($title, $balanceConfig);
    }

    /**
     * 账户详情
     */
    public function accountInfo()
    {
        $title = "会员银行账户详情";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $id = request()->post('id', 0);
        if (empty($id) || ! is_numeric($id)) {
            return $this->outMessage($title, "", '-50', "无法获取账户详情");
        }
        $member = new MemberService();
        $account_info = $member->getMemberBankAccountDetail($id);
        return $this->outMessage($title, $account_info);
    }

    /**
     * 账户列表
     * 任鹏强
     * 2017年3月13日10:52:59
     */
    public function accountList()
    {
        $title = "会员银行账户列表";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $member = new MemberService();
        $account_list = $member->getMemberBankAccount();
        return $this->outMessage($title, $account_list);
    }

    /**
     * 添加账户
     */
    public function addAccount()
    {
        $title = "添加会员银行账户";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        
        $member = new MemberService();
        $uid = $this->uid;
        $realname = request()->post('realname', '');
        $mobile = request()->post('mobile', '');
        $account_type = request()->post('account_type', '1');
        $account_type_name = request()->post('account_type_name', '');
        $account_number = request()->post('account_number', '');
        $branch_bank_name = request()->post('branch_bank_name', '');
        if (! empty($account_type)) {
            if ($account_type == 2 || $account_type == 3) {
                if (empty($realname) || empty($mobile) || empty($account_type) || empty($account_type_name)) {
                    return $this->outMessage($title, - 1);
                }
            } else {
                if (empty($realname) || empty($mobile) || empty($account_type) || empty($account_type_name) || empty($account_number) || empty($branch_bank_name)) {
                    return $this->outMessage($title, - 2);
                }
            }
        } else {
            return $this->outMessage($title, - 3);
        }
        $retval = $member->addMemberBankAccount($uid, $account_type, $account_type_name, $branch_bank_name, $realname, $account_number, $mobile);
        return $this->outMessage($title, $retval);
    }

    /**
     * 修改账户信息
     */
    public function updateAccount()
    {
        $title = "修改账户信息";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $member = new MemberService();
        $uid = $this->uid;
        $account_id = request()->post('id', '');
        $realname = request()->post('realname', '');
        $mobile = request()->post('mobile', '');
        $account_type = request()->post('account_type', '1');
        $account_type_name = request()->post('account_type_name', '');
        $account_number = request()->post('account_number', '');
        $branch_bank_name = request()->post('branch_bank_name', '');
        if (! empty($account_type)) {
            if ($account_type == 2 || $account_type == 3) {
                if (empty($realname) || empty($mobile) || empty($account_type) || empty($account_type_name)) {
                    return $this->outMessage($title, - 1);
                }
            } else {
                if (empty($realname) || empty($mobile) || empty($account_type) || empty($account_type_name) || empty($account_number) || empty($branch_bank_name)) {
                    return $this->outMessage($title, - 2);
                }
            }
        } else {
            return $this->outMessage($title, - 3);
        }
        $retval = $member->updateMemberBankAccount($account_id, $account_type, $account_type_name, $branch_bank_name, $realname, $account_number, $mobile);
        return $this->outMessage($title, $retval);
    }

    /**
     * 删除账户信息
     */
    public function delAccount()
    {
        $title = "删除账户信息";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $applet_member = new MemberService();
        $account_id = request()->post('id', '');
        if (empty($account_id)) {
            return $this->outMessage($title, "", '-50', "无法获取账户信息");
        }
        $retval = $applet_member->delMemberBankAccount($account_id);
        return $this->outMessage($title, $retval);
    }

    /**
     * 设置默认账户
     */
    public function checkAccount()
    {
        $title = "设置选中账户";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $member = new MemberService();
        $uid = $this->uid;
        $account_id = request()->post('id', '');
        $retval = $member->setMemberBankAccountDefault($uid, $account_id);
        return $this->outMessage($title, $retval);
    }

    /**
     * 用户签到
     */
    public function signIn()
    {
        $title = "用户签到";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $rewardRule = new PromoteRewardRule();
        $retval = $rewardRule->memberSign($this->uid, $this->instance_id);
        return $this->outMessage($title, $retval);
    }

    /**
     * 分享送积分
     */
    public function shareGivePoint()
    {
        $title = "分享送积分";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $rewardRule = new PromoteRewardRule();
        $res = $rewardRule->memberShareSendPoint($this->instance_id, $this->uid);
        if ($res) {
            $Config = new Config();
            $integralConfig = $Config->getIntegralConfig($this->instance_id);
            if ($integralConfig['share_coupon'] == 1) {
                $result = $rewardRule->getRewardRuleDetail($this->instance_id);
                if ($result['share_coupon'] != 0) {
                    $member = new MemberService();
                    $retval = $member->memberGetCoupon($this->uid, $result['share_coupon'], 2);
                }
            }
        }
        return $this->outMessage($title, $res);
    }

    /**
     * 用户充值余额
     */
    public function recharge()
    {
        $title = "用户充值余额";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $pay = new UnifyPay();
        $pay_no = $pay->createOutTradeNo();
        return $this->outMessage($title, $pay_no);
    }

    /**
     * 创建充值订单
     */
    public function createRechargeOrder()
    {
        $title = "创建充值订单";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $recharge_money = request()->post('recharge_money', 0);
        if ($recharge_money <= 0) {
            return $this->outMessage($title, "", '-50', "支付金额必须大于0");
        }
        $out_trade_no = request()->post('out_trade_no', '');
        if (empty($out_trade_no)) {
            return $this->outMessage($title, "", '-50', "支付流水号不能为空");
        }
        $member = new MemberService();
        $retval = $member->createMemberRecharge($recharge_money, $this->uid, $out_trade_no);
        return $this->outMessage($title, $retval);
    }

    /**
     * 申请提现页面数据
     */
    public function toWithdrawInfo()
    {
        $title = "申请提现页面数据";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $member = new MemberService();
        $account_list = $member->getMemberBankAccount(1);
        // 获取会员余额
        $uid = $this->uid;
        $members = new MemberAccount();
        $account = $members->getMemberBalance($uid);
        $shop_id = $this->instance_id;
        $config = new Config();
        $balanceConfig = $config->getBalanceWithdrawConfig($shop_id);
        if ($balanceConfig["is_use"] == 0 || $balanceConfig["value"]["withdraw_multiple"] <= 0) {
            return $this->outMessage($title, null, - 10, '当前店铺未开启提现，请联系管理员！');
        }
        $withdraw_cash_min = $balanceConfig['value']["withdraw_cash_min"];
        $poundage = $balanceConfig['value']["withdraw_multiple"];
        $withdraw_message = $balanceConfig['value']["withdraw_message"];
        
        $data = array(
            'withdraw_message' => $withdraw_message,
            'account_list' => $account_list,
            'poundage' => $poundage,
            'withdraw_cash_min' => $withdraw_cash_min,
            'account' => $account
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 申请提现
     */
    public function toWithdraw()
    {
        $title = "申请提现页面数据";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $bank_account_id = request()->post('bank_account_id', '');
        if (empty($bank_account_id)) {
            return $this->outMessage($title, "", '-50', "无法获取账户信息");
        }
        $withdraw_no = time() . rand(111, 999);
        $cash = request()->post('cash', '');
        $shop_id = $this->instance_id;
        $member = new MemberService();
        $retval = $member->addMemberBalanceWithdraw($shop_id, $withdraw_no, $this->uid, $bank_account_id, $cash);
        return $this->outMessage($title, $retval);
    }

    /**
     * 绑定时发送短信验证码或邮件验证码
     *
     * @return number[]|string[]|string|mixed
     */
    function sendBindCode()
    {
        $title = '发送验证码';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $params['email'] = request()->post('email', '');
        $params['mobile'] = request()->post('mobile', '');
        $params['user_id'] = $this->uid;
        $type = request()->post("type", '');
        $vertification = request()->post('vertification', '');
        $key = request()->post('key', '-504*504');
        $params['shop_id'] = 0;
        if ($this->login_verify_code["value"]["pc"] == 1) {
            $res = $this->check_code($vertification, $key);
            if ($res < 0) {
                $result = [
                    'code' => - 5,
                    'message' => '验证码错误'
                ];
                return $this->outMessage($title, $result);
            }
            $data = array(
                'vertification' => $vertification,
                'key' => $key
            );
        }
        
        if ($type == 'email') {
            $data['no'] = $params['email'];
            $hook = runhook('Notify', 'bindEmail', $params);
        } elseif ($type == 'mobile') {
            $data['no'] = $params['mobile'];
            $hook = runhook('Notify', 'bindMobile', $params);
        }
        if (! empty($hook) && ! empty($hook['param'])) {
            
            $result = [
                'code' => 0,
                'message' => '发送成功'
            ];
            $key = md5('@' . $key . '-');
            $data['code'] = $hook['param'];
            Cache::set($key, $data, 300);
        } else {
            
            $result = [
                'code' => - 1,
                'message' => '发送失败'
            ];
        }
        return $this->outMessage($title, $result);
    }

    /**
     * 绑定时发送短信验证码或邮件验证码，App专用，APP的宗旨是操作方便，使用简单，不能太复杂
     * 创建时间：2018年6月13日18:36:53
     *
     * @return number[]|string[]|string|mixed
     */
    function sendBindCodeForApp()
    {
        $title = '发送验证码';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $params['email'] = request()->post('email', '');
        $params['mobile'] = request()->post('mobile', '');
        $params['user_id'] = $this->uid;
        $type = request()->post("type", '');
        $params['shop_id'] = 0;
        
        if ($type == 'email') {
            $data['no'] = $params['email'];
            $hook = runhook('Notify', 'bindEmail', $params);
        } elseif ($type == 'mobile') {
            $data['no'] = $params['mobile'];
            $hook = runhook('Notify', 'bindMobile', $params);
        }
        if (! empty($hook) && ! empty($hook['param'])) {
            
            $result = [
                'code' => 0,
                'message' => '发送成功',
                'param' => $hook['param']
            ];
        } else {
            
            $result = [
                'code' => - 1,
                'message' => '发送失败',
                'param' => 0
            ];
        }
        return $this->outMessage($title, $result);
    }

    /**
     * 检侧动态验证码是否输入正确
     */
    public function checkDynamicCode()
    {
        $title = '检测动态验证码';
        if (request()->isPost()) {
            if (empty($this->uid)) {
                return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
            }
            $code = request()->post("vertification", '');
            $key = request()->post('key', '-504*504');
            $key = md5('@' . $key . '-');
            $data = Cache::get($key);
            $verificationCode = '';
            if (! empty($data['code'])) {
                $verificationCode = $data['code'];
            }
            if ($code != $verificationCode || $code == '') {
                $result = array(
                    "code" => - 1,
                    "message" => "动态验证码错误"
                );
            } else {
                $result = array(
                    "code" => 0,
                    "message" => "验证码正确"
                );
                Cache::set($key, '');
            }
        } else {
            $result = array(
                "code" => - 1,
                "message" => "动态验证码错误"
            );
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
     * 更改用户头像
     */
    public function modifyFace()
    {
        $title = '更换用户头像';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $member = new MemberService();
        
        $user_headimg = request()->post('user_headimg', '');
        if (empty($user_headimg)) {
            return $this->outMessage($title, "", '-50', "无法获取用户头像信息");
        }
        $res = $member->ModifyUserHeadimg($this->uid, $user_headimg);
        return $this->outMessage($title, $res);
    }

    /**
     * 我的收藏
     */
    public function myCollection()
    {
        $title = '我的收藏';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $member = new MemberService();
        $page = request()->post('page', '1');
        $type = request()->post('type', 0);
        $condition = array(
            "nmf.fav_type" => 'goods',
            "nmf.uid" => $this->uid
        );
        if ($type == 1) { // 获取本周内收藏的商品
            $start_time = mktime(0, 0, 0, date("m"), date("d") - date("w") + 1, date("Y"));
            $end_time = mktime(23, 59, 59, date("m"), date("d") - date("w") + 7, date("Y"));
            $condition["fav_time"] = array(
                "between",
                $start_time . "," . $end_time
            );
        } else 
            if ($type == 2) { // 获取本月内收藏的商品
                $start_time = mktime(0, 0, 0, date("m"), 1, date("Y"));
                $end_time = mktime(23, 59, 59, date("m"), date("t"), date("Y"));
                $condition["fav_time"] = array(
                    "between",
                    $start_time . "," . $end_time
                );
            } else 
                if ($type == 3) { // 获取本年内收藏的商品
                    $start_time = strtotime(date("Y", time()) . "-1" . "-1");
                    $end_time = strtotime(date("Y", time()) . "-12" . "-31");
                    $condition["fav_time"] = array(
                        "between",
                        $start_time . "," . $end_time
                    );
                }
        
        $goods_collection_list = $member->getMemberGoodsFavoritesList($page, PAGESIZE, $condition, "fav_time desc");
        foreach ($goods_collection_list['data'] as $k => $v) {
            $v['fav_time'] = date("Y-m-d H:i:s", $v['fav_time']);
        }
        return $this->outMessage($title, $goods_collection_list);
    }

    /**
     * 添加收藏
     */
    public function FavoritesGoodsorshop()
    {
        $title = '添加收藏';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $fav_id = request()->post('fav_id', '');
        $fav_type = request()->post('fav_type', '');
        $log_msg = request()->post('log_msg', '');
        $member = new MemberService();
        $result = $member->addMemberFavouites($fav_id, $fav_type, $log_msg);
        return $this->outMessage($title, $result);
    }

    /**
     * 取消收藏
     */
    public function cancelFavorites()
    {
        $title = '取消收藏';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $fav_id = request()->post('fav_id', '');
        $fav_type = request()->post('fav_type', '');
        $member = new MemberService();
        $result = $member->deleteMemberFavorites($fav_id, $fav_type);
        return $this->outMessage($title, $result);
    }

    /**
     * 我的足迹
     */
    public function newMyPath()
    {
        $title = '我的足迹';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $good = new Goods();
        $data = request()->post();
        $condition = [];
        $condition["uid"] = $this->uid;
        if (! empty($data['category_id']))
            $condition['category_id'] = $data['category_id'];
        
        $order = 'create_time desc';
        $list = $good->getGoodsBrowseList($data['page_index'], $data['page_size'], $condition, $order, $field = "*");
        foreach ($list['data'] as $key => $val) {
            $month = ltrim(date('m', $val['create_time']), '0');
            $day = ltrim(date('d', $val['create_time']), '0');
            $val['month'] = $month;
            $val['day'] = $day;
        }
        
        return $this->outMessage($title, $list);
    }

    /**
     * 删除我的足迹
     */
    public function delMyPath()
    {
        $title = '删除足迹';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $type = request()->post('type');
        $value = request()->post('value');
        
        if ($type == 'browse_id')
            $condition['browse_id'] = $value;
        
        if ($type != 'browse_id' || empty($value)) {
            return $this->outMessage($title, "", '-10', "删除失败，无法获取该足迹信息");
        }
        $good = new Goods();
        $res = $good->deleteGoodsBrowse($condition);
        
        return $this->outMessage($title, $res);
    }

    /**
     * 手机号验证码登录
     * 创建时间：2018年6月12日14:56:02
     *
     * @return Ambigous <\think\response\Json, string>
     */
    public function mobileVerificationCodeLogin()
    {
        $title = "手机号验证码登录";
        $mobile = request()->post("mobile", "");
        if (empty($mobile)) {
            return $this->outMessage($title, null, - 1, "缺少字段mobile");
        }
        $member = new MemberService();
        $ishas = $member->checkMobileIsHas($mobile);
        if ($ishas) {
            $params['mobile'] = $mobile;
            $params['shop_id'] = 0;
            $result = runhook('Notify', 'registSmsValidation', $params);
            
            if (empty($result)) {
                $res = [
                    'code' => - 1,
                    'message' => "发送失败",
                    'param' => 0
                ];
            } elseif ($result["code"] != 0) {
                $res = [
                    'code' => $result["code"],
                    'message' => $result["message"],
                    'param' => $result['param']
                ];
            } elseif ($result["code"] == 0) {
                $res = [
                    'code' => 0,
                    'message' => "发送成功",
                    'param' => $result['param']
                ];
            }
            return $this->outMessage($title, $res);
        } else {
            return $this->outMessage($title, null, - 1, "该手机号未注册");
        }
    }

    /**
     * 保存微信收货地址
     */
    public function saveWeixinAddress()
    {
        $title = '保存微信收货地址';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $member = new MemberService();
        $address_service = new Address();
        $consigner = request()->post('consigner', '');
        $mobile = request()->post('mobile', '');
        $phone = request()->post('phone', '');
        $province = request()->post('province', '');
        $city = request()->post('city', '');
        $district = request()->post('district', '');
        $address = request()->post('address', '');
        $zip_code = request()->post('zip_code', '');
        $alias = request()->post('alias', '');
        
        $province = ! empty($province) ? $address_service->getProvinceId($province)["province_id"] : "";
        $city = ! empty($city) ? $address_service->getCityId($city)["city_id"] : "";
        $district = ! empty($district) ? $address_service->getDistrictId($district)["district_id"] : "";
        
        $retval = $member->addMemberExpressAddress($consigner, $mobile, $phone, $province, $city, $district, $address, $zip_code, $alias);
        return $this->outMessage($title, $retval);
    }

    /**
     * 获取余额提现配置
     * 创建时间：2018年7月7日15:54:24
     * 
     * @return Ambigous <\think\response\Json, string>
     */
    public function getBalanceWithdrawConfig()
    {
        $title = "获取余额提现配置";
        $config = new Config();
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        
        $res = $config->getBalanceWithdrawConfig($this->instance_id);
        return $this->outMessage($title, $res);
    }
}