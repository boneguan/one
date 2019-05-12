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
namespace app\wap\controller;

use data\service\Verification as VerificationService;
use data\service\VirtualGoods as VirtualGoodsService;
use data\service\User;
class Verification extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 检测用户
     */
    private function checkLogin()
    {
        $uid = $this->uid;
        if (empty($uid)) {
            $redirect = __URL(__URL__ . "/wap/login");
            $this->redirect($redirect); // 用户未登录
        }
        $is_member = $this->user->getSessionUserIsMember();
        if (empty($is_member)) {
            $redirect = __URL(__URL__ . "/wap/login");
            $this->redirect($redirect); // 用户未登录
        }
    }
    /**
     * 核销商品详情
     */
    public function VerificationOrderDetail(){ 
        $this->checkLogin();
        $verificadition = new VerificationService();
        $vg_id = request()->get('vg_id', '');
        $uid = $this->uid;
        $condition = array('virtual_goods_id' => $vg_id, "buyer_id" => $uid);
        
        $verificadition_detail = $verificadition -> getVirtualGoodsDetail($condition);
        
        if(empty($verificadition_detail)){
            $this->error("未获取到该虚拟码信息");
        }
        $this->assign("info", $verificadition_detail);
        return view($this->style."Verification/VerificationOrderDetail");
    }
    
    /**
     * 虚拟商品
     */
    public function virtualGoodsShare(){
        $verificadition = new VerificationService();
        $vg_id = request()->get('vg_id', '');
        
        $condition = array('virtual_goods_id' => $vg_id);
        $verificadition_detail = $verificadition -> getVirtualGoodsDetail($condition);
        if(empty($verificadition_detail)){
            $this->error("未获取到该虚拟码信息");
        }
        //虚拟码详情
        $this->assign("info", $verificadition_detail);
        //核销记录
        $virtualGoodsVerificationList = $verificadition -> getVirtualGoodsVerificationList(1, 0, ['virtual_goods_id'=>$vg_id], 'create_time desc');
        $this->assign('list', $virtualGoodsVerificationList['data']);
        //虚拟商品二维码
        $path = $this -> getVirtualQecode($vg_id);
        $this->assign("path", $path);
        return view($this->style . 'Verification/virtualGoodsShare');
    }
    
    /**
     * 核销商品审核
     */
    public function VerificationGooodsToExamine(){
        $verificadition = new VerificationService();
        $vg_id = request()->get('vg_id', '');
        if(empty($this->uid)){
            $_SESSION['login_pre_url'] = __URL(\think\Config::get('view_replace_str.APP_MAIN') . "/Verification/VerificationGooodsToExamine?vg_id=".$vg_id);
           $this->redirect("Login/index");
        }
        //判断用户是否是该店的核销员
        $is_verification_person = $verificadition -> getShopVerificationInfo($this->uid, $this->instance_id);
        if($is_verification_person == 0){
            $this->error("对不起，您没有权限核验该订单","member/index");    
        }
        $condition = array('virtual_goods_id' => $vg_id);
        $verificadition_detail = $verificadition -> getVirtualGoodsDetail($condition);
        
        if(empty($verificadition_detail)){
            $this->error("未获取到该虚拟码信息","member/index");
        }
        $time = time();
        if($time < $verificadition_detail['start_time']){
            $this->error("该虚拟码未到有效期","member/index");
        }
        if($verificadition_detail['end_time'] > 0){
            if($time > $verificadition_detail['end_time']){
                $this->error("该虚拟码已过期","member/index");
            }
        }
        if($verificadition_detail['confine_use_number'] > 0 && ($verificadition_detail['confine_use_number'] - $verificadition_detail['use_number']) <= 0){
            $this->error("对不起，该虚拟码使用次数已用完","member/index");
        }
        
        $verificadition_person_info = $verificadition -> getVerificationPersonnelList(1, 1, ['nvp.uid'=>$this->uid], "");
        $this->assign('verificadition_person_info', $verificadition_person_info['data'][0]);
   
        //虚拟码详情
        $this->assign("info", $verificadition_detail);
        
        return view($this->style. "Verification/VerificationGooodsToExamine");
    }
    
    /**
     * 核销虚拟码
     */
    public function verificationVirtualGoods(){
        $verificadition = new VerificationService();
        $virtual_goods_id = request()->post("virtual_goods_id", "");
        $res = $verificadition -> verificationVirtualGoods($this->uid, $virtual_goods_id);
        return AjaxReturn($res);
    }
    
    /**
     * 制作用户分享优惠券二维码
     */
    function getVirtualQecode($virtual_goods_id)
    {
        $url = __URL(__URL__ . '/wap/Verification/VerificationGooodsToExamine?vg_id=' . $virtual_goods_id );
    
        // 查询并生成二维码
    
        $upload_path = "upload/qrcode/virtual_qrcode";
        if (! file_exists($upload_path)) {
            mkdir($upload_path, 0777, true);
        }
        $path = $upload_path . '/virtual_' . $virtual_goods_id . '.png';
//         if (! file_exists($path)) {
            getQRcode($url, $upload_path, "virtual_" . $virtual_goods_id );
//         }
        return $path;
    }
    
    /**
     * 输入虚拟码进行核销
     * @return number|unknown
     */
    public function check_virtual_code(){
        $verificadition = new VerificationService();
        $virtual_code = request()->post("virtual_code", "");
        $condition = array(
            "virtual_code" => $virtual_code
        );
        $res = $verificadition -> getVirtualGoodsInfo($condition);
        return $res;
    }
    
    /**
     * 我的虚拟码列表
     */
    public function myVirtualCode()
    {
        if (request()->isAjax()) {
            $virtualGoods = new VirtualGoodsService();
            $type = request()->post('type', 0);
            $condition['use_status'] = $type;
            $condition['nvg.buyer_id'] = $this->uid;
            $order = "";
    
            $virtual_list = $virtualGoods->getVirtualGoodsList(1, 0, $condition, $order);
            foreach ($virtual_list['data'] as $key => $item) {
                $virtual_list['data'][$key]['start_time'] = date("Y-m-d", $item['start_time']);
                if($item['end_time'] > 0){
                    $virtual_list['data'][$key]['end_time'] = date("Y-m-d", $item['end_time'])."之前";
                }else{
                    $virtual_list['data'][$key]['end_time'] = "不限制有效期";
                }
            }
    
            return $virtual_list['data'];
        }
    
        return view($this->style . "Verification/myVirtualCode");
    }
    
    /**
     * 核销台
     */
    public function verificationPlatform()
    {
        //检测当前用户是否为核销员
        $verification_service = new VerificationService();
        $is_verification = $verification_service->getShopVerificationInfo($this->uid, $this->instance_id);
        if (!$is_verification > 0) {
            $redirect = __URL(__URL__ . "/wap/Member");
            $this->error("", $redirect);
        }
        $member = new User();
        $member_info = $member -> getUserInfo();
        $this->assign('member_info', $member_info);
        return view($this->style . "Verification/verificationPlatform");
    }
}