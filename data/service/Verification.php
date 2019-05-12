<?php
/**
 * Verification.php
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

use data\service\BaseService as BaseService;
use data\api\IVerification;
use data\model\NsVerificationPersonViewModel;
use data\model\NsVirtualGoodsModel;
use data\model\NsVirtualGoodsVerificationModel;
use data\model\UserModel;
use data\model\NsOrderGoodsModel;
use data\model\NsGoodsModel;
use data\model\AlbumPictureModel;
use data\model\NsVirtualGoodsVerificationViewModel;

class Verification extends BaseService implements IVerification
{

    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 获取核销人员列表
     * {@inheritDoc}
     * @see \data\api\IVerification::getVerificationPersonnelList()
     */
    public function getVerificationPersonnelList($page_index, $page_size, $condition, $order)
    {
        $verification_person = new NsVerificationPersonViewModel();
        $list = $verification_person -> getViewList($page_index, $page_size, $condition, $order);
        return $list;
    }
    
    /**
     * 添加核销人员
     * @param unknown $uid
     * @param unknown $shop_id
     */
    public function addVerificationPersonne($uid, $shop_id){
        
        $uid_arr = explode(',', $uid);
        if(count($uid_arr)){
            foreach ($uid_arr as $k=>$v){
                $verification_person = new NsVerificationPersonViewModel();
                $verification_person -> save([
                    'uid' => $v,
                    'shop_id' => $shop_id,
                    'create_time'=> time()
                ]);
            }
        }
        return $verification_person-> v_id;
    }
    
    /**
     * 删除核销员
     * @param unknown $vid
     */
    public function deleteVerificationPerson($vid){
        $verification_person = new NsVerificationPersonViewModel();
        $res = $verification_person -> destroy([
            'v_id' => array('in', $vid)
    
        ]);
        return $res;
    }
    
    /**
     * 核销使用虚拟商品
     */
    public function verificationVirtualGoods($uid, $virtual_goods_id, $is_system = true){
        $virtual_goods_model = new NsVirtualGoodsModel();
        $virtual_goods_model->startTrans();
        try {
            $virtual_goods_info = $virtual_goods_model -> getinfo(["virtual_goods_id" => $virtual_goods_id, "use_status" => 0 ], "use_number, start_time, end_time, confine_use_number, use_status, shop_id, buyer_id, goods_id");
            //判断此商品是否还能使用
            if(empty($virtual_goods_info)){
                return VIRIUAL_GOODS_ERROR;
            }
            $time = time();
            if($virtual_goods_info["end_time"] > 0){
                if($time > $virtual_goods_info["end_time"]){
                    return VIRIUAL_GOODS_TIME_ERROR;
                }
            }
            if($virtual_goods_info["confine_use_number"] != 0 && $virtual_goods_info["use_number"] >=  $virtual_goods_info["confine_use_number"]){
                return VIRIUAL_GOODS_TIME_ERROR;
            }
            if(!$is_system){
                $verification_person = new NsVerificationPersonViewModel();
                $verification_person_info = $verification_person -> getInfo(["uid" => $uid, "shop_id" => $virtual_goods_info["shop_id"]], "shop_id");
                if(empty($verification_person_info)){
                    //核销人员没有本店铺核销资格
                    return VIRIUAL_GOODS_MEMBER_ERROR;
                }
                
            }
            $useing_number = $virtual_goods_info["use_number"] + 1;
            $data = array(
                "use_number" => $useing_number
            );
            $status = $virtual_goods_info["use_status"];
            if($virtual_goods_info["confine_use_number"] != 0 && $useing_number == $virtual_goods_info["confine_use_number"]){
                $data["use_status"] = 1;
                $status = 1;
            }
            //加一次使用次数,如果数量等于最大使用次数,状态变为已使用
            $retval = $virtual_goods_model ->save($data, ["virtual_goods_id" => $virtual_goods_id]);
            //加一条虚拟商品核销记录
            $action = "虚拟商品核销";
            $this-> addVirtualGoodsVerificationModel($uid, $virtual_goods_id, $action, $status, $virtual_goods_info["goods_id"], $virtual_goods_info["buyer_id"]);
            $virtual_goods_model->commit();
            return 1;
        } catch (\Exception $e) {
            $virtual_goods_model->rollback();
            return 0;
        }
    
    
    }
    /**
     *添加商品核销记录
     * @param unknown $uid
     * @param unknown $virtual_goods_id
     * @param unknown $action
     * @param unknown $status
     * @return string
     */
    public function addVirtualGoodsVerificationModel($uid, $virtual_goods_id, $action, $status, $goods_id, $buyer_id){
        $user_model = new UserModel();
        //获取使用人信息
        $buyer_name = '';
        $buyer_info = $user_model->getInfo(["uid" => $buyer_id], "nick_name");
        if(!empty($buyer_info)){
            $buyer_name = $buyer_info["nick_name"];
        }
        //获取核销人信息
        $action_name = '';
        $action_info = $user_model -> getInfo(["uid" => $uid], "nick_name");
        if(!empty($action_info)){
            $action_name = $action_info["nick_name"];
        }
        $goods_name = '';
        //获取商品名称
        $goods_model = new NsGoodsModel();
        $goods_info = $goods_model -> getInfo(["goods_id" => $goods_id], "goods_name");
        if(!empty($goods_info)){
            $goods_name = $goods_info["goods_name"];
        }
        $virtual_goods_verification = new NsVirtualGoodsVerificationModel();
        $data = array(
            "uid" => $uid,
            "virtual_goods_id" => $virtual_goods_id,
            "action" => $action,
            "status" =>$status,
            "create_time" => time(),
            "num" => 1,
            "goods_id" => $goods_id,
            "verification_name" => $action_name,
            "user_name" =>  $buyer_name,
            "goods_name" => $goods_name,
            "buyer_id" => $buyer_id
        );
        $retval = $virtual_goods_verification -> save($data);
        return $retval;
    }
    /**
     * 商品核销记录
     */
    public function getVirtualGoodsVerificationList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*'){
        $virtual_goods_verification = new NsVirtualGoodsVerificationModel();
        $list = $virtual_goods_verification->pageQuery($page_index, $page_size, $condition, $order, $field);
        return $list;
    }
    /**
     * 核销员 检测
     * @param unknown $uid
     * @param unknown $uid
     */
    public function getShopVerificationInfo($uid, $shop_id){
        $verification_person = new NsVerificationPersonViewModel();
        $verification_person_count = $verification_person -> getCount(["uid" => $uid, "shop_id" => $shop_id]);
        return $verification_person_count;
    }
    
    /**
     * 用户虚拟商品详情
     * @param unknown $condition
     */
    public function getVirtualGoodsDetail($condition){
        $virtual_goods_model = new NsVirtualGoodsModel();
        $virtual_goods_info = $virtual_goods_model -> getInfo($condition, "*");
        if(!empty($virtual_goods_info)){
            $order_goods_model = new NsOrderGoodsModel();
            $order_goods_info = $order_goods_model -> getInfo(["order_goods_id" => $virtual_goods_info["order_goods_id"]], "goods_id");
            if(!empty($order_goods_info)){
                $goods_model = new NsGoodsModel();
                $goods_info = $goods_model -> getInfo(["goods_id" => $order_goods_info["goods_id"]], "goods_name, picture, goods_id");
                $virtual_goods_info["goods_id"] = $goods_info["goods_id"];
                $virtual_goods_info["goods_name"] = $goods_info["goods_name"];
                $album_picture_model = new AlbumPictureModel();
                $album_picture_info = $album_picture_model -> getInfo(["pic_id" => $goods_info["picture"]], "*");
                $virtual_goods_info["picture"] =  $album_picture_info;
            }
        }
        return $virtual_goods_info;
        
    }
    /**
     * 查询虚拟商品得到id
     * @param unknown $condition
     */
    public function getVirtualGoodsInfo($condition){
        $virtual_goods_model = new NsVirtualGoodsModel();
        $virtual_goods_info = $virtual_goods_model -> getInfo($condition, "virtual_goods_id");
        if(empty($virtual_goods_info)) {
            return 0;
        }else{
            return $virtual_goods_info["virtual_goods_id"];
        }
    }
    /**
     * 使过期的虚拟商品失效
     */
    public function virtualGoodsClose(){
        $virtual_goods_model = new NsVirtualGoodsModel();
        $time = time();
        $condition = array(
            "end_time" => array(["lt", $time] ,["neq", 0]),
            "use_status" => array("neq", -1)
        );
        $data = array(
            "use_status" => -1
        );
        $retval = $virtual_goods_model -> save($data, $condition);
        return $retval;
    }
}