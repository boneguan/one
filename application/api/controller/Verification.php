<?php
/**
 * Verification.php
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

use data\service\Verification as VerificationService;
use data\service\VirtualGoods as VirtualGoodsService;
use data\service\User;

class Verification extends BaseController
{

    /**
     * 核销商品详情
     */
    public function VerificationOrderDetail()
    {
        $title = '核销商品详情';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $user = new User();
        $is_member = $user->getSessionUserIsMember();
        
        if (empty($is_member)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        
        $verificadition = new VerificationService();
        $vg_id = request()->post('vg_id', '');
        $uid = $this->uid;
        $condition = array(
            'virtual_goods_id' => $vg_id,
            "buyer_id" => $uid
        );
        
        $verificadition_detail = $verificadition->getVirtualGoodsDetail($condition);
        
        if (empty($verificadition_detail)) {
            return $this->outMessage($title, "", '-10', "未获取到该虚拟码信息");
        }
        return $this->outMessage($title, $verificadition_detail);
    }

    /**
     * 虚拟商品
     */
    public function virtualGoodsShare()
    {
        $title = '虚拟商品';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $verificadition = new VerificationService();
        $vg_id = request()->post('vg_id', '');
        $condition = array(
            'virtual_goods_id' => $vg_id
        );
        // 虚拟码详情
        $verificadition_detail = $verificadition->getVirtualGoodsDetail($condition);
        if (empty($verificadition_detail)) {
            return $this->outMessage($title, "", '-10', "未获取到该虚拟码信息");
        }
        
        // 核销记录
        $virtualGoodsVerificationList = $verificadition->getVirtualGoodsVerificationList(1, 0, [
            'virtual_goods_id' => $vg_id
        ], 'create_time desc');
        // 虚拟商品二维码
        $wchat = new Wchat();
        $url = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=';
        $scene = $vg_id;
        $page = 'pagesother/pages/verification/verificationgooodstoexamine/verificationgooodstoexamine';
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
                $path = '';
            }
        } else {
            $path = '';
        }
        $data = array(
            'info' => $verificadition_detail,
            'list' => $virtualGoodsVerificationList['data'],
            'path' => $path
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 核销商品审核
     */
    public function VerificationGooodsToExamine()
    {
        $title = '核销商品审核';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $verificadition = new VerificationService();
        $vg_id = request()->post('vg_id', '');
        // 判断用户是否是该店的核销员
        $is_verification_person = $verificadition->getShopVerificationInfo($this->uid, $this->instance_id);
        if ($is_verification_person == 0) {
            return $this->outMessage($title, "", '-10', "对不起，您没有权限核验该订单");
        }
        $condition = array(
            'virtual_goods_id' => $vg_id
        );
        // 虚拟码详情
        $verificadition_detail = $verificadition->getVirtualGoodsDetail($condition);
        
        if (empty($verificadition_detail)) {
            return $this->outMessage($title, "", '-10', "未获取到该虚拟码信息");
        }
        $time = time();
        if ($time < $verificadition_detail['start_time']) {
            return $this->outMessage($title, "", '-10', "该虚拟码未到有效期");
        }
        if ($verificadition_detail['end_time'] > 0) {
            if ($time > $verificadition_detail['end_time']) {
                return $this->outMessage($title, "", '-10', "该虚拟码已过期");
            }
        }
        if ($verificadition_detail['confine_use_number'] > 0 && ($verificadition_detail['confine_use_number'] - $verificadition_detail['use_number']) <= 0) {
            return $this->outMessage($title, "", '-10', "对不起，该虚拟码使用次数已用完");
        }
        
        $verificadition_person_info = $verificadition->getVerificationPersonnelList(1, 1, [
            'nvp.uid' => $this->uid
        ], "");
        
        $data = array(
            'info' => $verificadition_detail,
            'verificadition_person_info' => $verificadition_person_info
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 核销虚拟码
     */
    public function verificationVirtualGoods()
    {
        $title = '核销虚拟码';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $verificadition = new VerificationService();
        $virtual_goods_id = request()->post("virtual_goods_id", "");
        $res = $verificadition->verificationVirtualGoods($this->uid, $virtual_goods_id);
        return $this->outMessage($title, $res);
    }

    /**
     * 制作核销二维码
     */
    function getVirtualQecode($virtual_goods_id)
    {
        $title = '制作核销二维码';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $url = __URL(__URL__ . '/wap/Verification/VerificationGooodsToExamine?vg_id=' . $virtual_goods_id);
        
        // 查询并生成二维码
        
        $upload_path = "upload/qrcode/virtual_qrcode";
        if (! file_exists($upload_path)) {
            mkdir($upload_path, 0777, true);
        }
        $path = $upload_path . '/virtual_' . $virtual_goods_id . '.png';
        getQRcode($url, $upload_path, "virtual_" . $virtual_goods_id);
        return $path;
    }

    /**
     * 输入虚拟码进行核销
     * 
     * @return number|unknown
     */
    public function checkVirtualCode()
    {
        $title = '虚拟码核销';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $verificadition = new VerificationService();
        $virtual_code = request()->post("virtual_code", "");
        $condition = array(
            "virtual_code" => $virtual_code
        );
        $verificadition_detail = $verificadition->getVirtualGoodsDetail($condition);
        if (empty($verificadition_detail)) {
            return $this->outMessage($title, "", '-10', "未获取到该虚拟码信息");
        }
        $time = time();
        if ($time < $verificadition_detail['start_time']) {
            return $this->outMessage($title, "", '-10', "该虚拟码未到有效期");
        }
        if ($verificadition_detail['end_time'] > 0) {
            if ($time > $verificadition_detail['end_time']) {
                return $this->outMessage($title, "", '-10', "该虚拟码已过期");
            }
        }
        if ($verificadition_detail['confine_use_number'] > 0 && ($verificadition_detail['confine_use_number'] - $verificadition_detail['use_number']) <= 0) {
            return $this->outMessage($title, "", '-10', "对不起，该虚拟码使用次数已用完");
        }
        $res = $verificadition_detail['virtual_goods_id'];
        return $this->outMessage($title, $res);
    }

    /**
     * 我的虚拟码列表
     */
    public function myVirtualCode()
    {
        $title = '我的虚拟码列表';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        $virtualGoods = new VirtualGoodsService();
        $type = request()->post('type', 0);
        $condition['use_status'] = $type;
        $condition['nvg.buyer_id'] = $this->uid;
        $order = "";
        
        $virtual_list = $virtualGoods->getVirtualGoodsList(1, 0, $condition, $order);
        foreach ($virtual_list['data'] as $key => $item) {
            $virtual_list['data'][$key]['start_time'] = date("Y-m-d", $item['start_time']);
            if ($item['end_time'] > 0) {
                $virtual_list['data'][$key]['end_time'] = date("Y-m-d", $item['end_time']) . "之前";
            } else {
                $virtual_list['data'][$key]['end_time'] = "不限制有效期";
            }
        }
        
        return $this->outMessage($title, $virtual_list['data']);
    }

    /**
     * 核销台
     */
    public function verificationPlatform()
    {
        $title = '核销台';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息");
        }
        // 检测当前用户是否为核销员
        $verification_service = new VerificationService();
        $is_verification = $verification_service->getShopVerificationInfo($this->uid, $this->instance_id);
        if (! $is_verification > 0) {
            return $this->outMessage($title, "", '-50', "暂无核销资格");
        }
        $member = new User();
        $member_info = $member->getUserInfo();
        return $this->outMessage($title, $member_info);
    }
}