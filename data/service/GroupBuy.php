<?php
/**
 * Shop.php
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
 * @date : 2015.1.17
 * @version : v1.0.0.0
 */
namespace data\service;

/**
 * 团购服务层
 */
use data\service\BaseService as BaseService;
use data\api\IGroupBuy;

use think\Cache;
use data\model\NsPromotionGroupBuyModel;
use Qiniu\json_decode;
use data\model\NsPromotionGroupBuyLadderModel;
use data\service\Order\OrderGroupBuy;
use data\model\NsGoodsModel;
use think\Log;
use data\model\NsOrderModel;
use data\model\AlbumPictureModel;

class GroupBuy extends BaseService implements IGroupBuy
{

    function __construct()
    {
        parent::__construct();
    }
	/* (non-PHPdoc)
     * @see \data\api\IGroupBuy::getPromotionGroupBuyList()
     */
    public function getPromotionGroupBuyList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        // TODO Auto-generated method stub
        $promotion_group_buy = new NsPromotionGroupBuyModel();
        $list = $promotion_group_buy->pageQuery($page_index, $page_size, $condition, $order, '*');
        
        $goods_model = new NsGoodsModel();
        foreach($list['data'] as $key=>$val){
            $goods_info = $goods_model->getInfo(['goods_id'=>$val['goods_id']],'goods_name');
             
            $list['data'][$key]["goods_name"] = $goods_info['goods_name'];
        }
        return $list;
    }

    /**
     * (non-PHPdoc)
     * @see \data\api\IGroupBuy::getPromotionGroupBuyDetail()
     */
    function getPromotionGroupBuyDetail($group_id){
    
        $promotion_group_buy = new NsPromotionGroupBuyModel();
        $promotion_group_buy_ladder = new NsPromotionGroupBuyLadderModel();
    
        $info = $promotion_group_buy->getInfo(['group_id'=>$group_id],'*');
        $price_list = $promotion_group_buy_ladder->getQuery(['group_id'=>$group_id], '*', 'id asc');
    
        $info['price_list'] = $price_list;
    
        return $info;
    }
    
	/* (non-PHPdoc)
     * @see \data\api\IGroupBuy::addPromotionGroupBuy()
     */
    public function addPromotionGroupBuy($shop_id, $goods_id, $start_time, $end_time, $max_num, $group_name, $price_json, $group_id = 0, $remark)
    {
        //添加活动
        $promotion_group_buy = new NsPromotionGroupBuyModel();
        
        $promotion_group_buy->startTrans();
        
        try {
            $min_num = 0;
            $price_array = json_decode($price_json, true);
            if(empty($price_array)){
                $promotion_group_buy->rollback();
                return 0;
            }
            foreach($price_array as $t=> $m){
                if($min_num == 0 || $min_num >=$m["0"]){
                    $min_num = $m["0"];
                }
            }
            // TODO Auto-generated method stub
            $data = array(
                "shop_id" => $shop_id,
                "start_time" => $start_time,
                "goods_id" => $goods_id,
                "end_time" => $end_time,
                "max_num" => $max_num,
                "min_num" => $min_num,
                "group_name" => $group_name,
                'remark'=>$remark,
            );
            if($group_id > 0){
                $data["modify_time"] = time();
                $res = $promotion_group_buy->save($data, ["group_id" => $group_id]);
            }else{
                $data["create_time"] = time();
                $res = $promotion_group_buy->save($data);
                $group_id = $promotion_group_buy->group_id;
            }
            //创建阶梯价格
            //首先删掉原来的阶梯价格
            $promotion_group_buy_ladder = new NsPromotionGroupBuyLadderModel();
            $result = $promotion_group_buy_ladder ->destroy(["group_id" => $group_id]);
            if(empty($price_array)){
                $promotion_group_buy->rollback();
                return 0;
            }else{
                //循环添加阶梯价格
                foreach($price_array as $k => $v){
                    $promotion_group_buy_ladder = new NsPromotionGroupBuyLadderModel();
                    $temp_data = array(
                        "group_id" => $group_id,
                        "num" => $v[0],
                        "group_price" => $v[1]
                    );
                    $retval = $promotion_group_buy_ladder -> save($temp_data);
                }
            }
            $promotion_group_buy->commit();
            return $group_id;
        } catch (\Exception $e) {
            $promotion_group_buy->rollback();
            return 0;
        }
    }
	/* (non-PHPdoc)
     * @see \data\api\IGroupBuy::getGoodsFirstPromotionGroupBuy()
     */
    public function getGoodsFirstPromotionGroupBuy($goods_id)
    {
        // TODO Auto-generated method stub
        $promotion_group_buy = new NsPromotionGroupBuyModel();
        $time = time();
        $promotion_group_buy_info = $promotion_group_buy->getFirstData(["goods_id" => $goods_id,"start_time"=>["lt", $time ], "end_time" => ["gt" , $time], "status" => 0], "create_time desc");
        if(!empty($promotion_group_buy_info)){
            $promotion_group_buy_ladder = new NsPromotionGroupBuyLadderModel();
            $promotion_group_buy_ladder_query = $promotion_group_buy_ladder->getQuery(["group_id" => $promotion_group_buy_info["group_id"]], "*", '');
            $promotion_group_buy_info["price_array"] = $promotion_group_buy_ladder_query;
        }
        return $promotion_group_buy_info;
    }
    
    /**
     * (non-PHPdoc)
     * @see \data\api\IGroupBuy::delPromotionGroupBuy()
     */
    function delPromotionGroupBuy($group_id){
        $promotion_group_buy = new NsPromotionGroupBuyModel();
        $promotion_group_buy_ladder = new NsPromotionGroupBuyLadderModel();
    
        $promotion_group_buy->startTrans();
        try {
            $res1 = $promotion_group_buy->destroy(['group_id'=>array("in", $group_id)]);
            $res2 = $promotion_group_buy_ladder->destroy(['group_id'=>array("in", $group_id)]);
    
            if($res1 > 0 || $res2 > 0){
                $promotion_group_buy->commit();
            }
    
            return $res1;
        }catch(\Exception $e){
             
            $promotion_group_buy->rollback();
            Log::write('团购删除失败：'.$e->getMessage());
            return 0;
        }
    
    }
    
    /**
     * 团购订单创建
     * @param unknown $order_type
     * @param unknown $out_trade_no
     * @param unknown $pay_type
     * @param unknown $shipping_type
     * @param unknown $order_from
     * @param unknown $buyer_ip
     * @param unknown $buyer_message
     * @param unknown $buyer_invoice
     * @param unknown $shipping_time
     * @param unknown $receiver_mobile
     * @param unknown $receiver_province
     * @param unknown $receiver_city
     * @param unknown $receiver_district
     * @param unknown $receiver_address
     * @param unknown $receiver_zip
     * @param unknown $receiver_name
     * @param unknown $point
     * @param unknown $user_money
     * @param unknown $goods_sku_list
     * @param unknown $platform_money
     * @param unknown $pick_up_id
     * @param unknown $shipping_company_id
     * @param unknown $coin
     */
    public function groupBuyOrderCreate($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $coin, $distribution_time_out){
        $order_pintuan = new OrderGroupBuy();
        $order_service = new Order();
        $retval = $order_pintuan->orderCreateGroupBuy($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $coin, '', $distribution_time_out);
        runhook("Notify", "orderCreate", array(
        "order_id" => $retval
        ));
        // 针对特殊订单执行支付处理
        if ($retval > 0) {
            hook('orderCreateSuccess', [
            'order_id' => $retval
            ]);
            // 货到付款
            if ($pay_type == 4) {
                $order_service->orderOnLinePay($out_trade_no, 4);
            } else {
                $order_model = new NsOrderModel();
                $order_info = $order_model->getInfo([
                    'order_id' => $retval
                ], '*');
                if (! empty($order_info)) {
                    if ($order_info['user_platform_money'] != 0) {
                        if ($order_info['pay_money'] == 0) {
                            $order_service->orderOnLinePay($out_trade_no, 5);
                        }
                    } else {
            
                        if ($order_info['pay_money'] == 0) {
                            $order_service->orderOnLinePay($out_trade_no, 1); // 默认微信支付
                        }
                    }
                }
            }
        }
    
        return $retval;
    }
    
    /**
     * 获取团购商品列表
     * @param number $page_index
     * @param number $page_size
     * @param string $condition
     * @param string $order
     * @param string $field
     */
    public function getPromotionGroupBuyGoodsList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        // TODO Auto-generated method stub
        $promotion_group_buy = new NsPromotionGroupBuyModel();
        $picture = new AlbumPictureModel();
        $ns_promotion_group_buy_ladder = new NsPromotionGroupBuyLadderModel();
        
        $goods_model = new NsGoodsModel();
        $viewObj = $goods_model->alias("ng")
        ->join('ns_promotion_group_buy npgb', 'ng.goods_id = npgb.goods_id', 'left')
        ->field($field);
        $queryList = $goods_model->viewPageQueryNew($viewObj, $page_index, $page_size, $condition, "", $order);
        
        $viewObj = $goods_model->alias("ng")
           ->join('ns_promotion_group_buy npgb', 'ng.goods_id = npgb.goods_id', 'left')
           ->field($field);
        $queryCount = $goods_model->viewCount($viewObj, $condition);
        $list = $goods_model->setReturnList($queryList, $queryCount, $page_size);

        foreach($list['data'] as $key=>$val){
            $picture_info = $picture -> getInfo(["pic_id"=>$val['picture']]);
            $list['data'][$key]["picture"] = $picture_info;
            $group_buy_ladder_info = $ns_promotion_group_buy_ladder->getInfo(['group_id'=>$val['group_id']],"group_price");
            $list['data'][$key]["group_price"] = $group_buy_ladder_info["group_price"];
            // 商品是否收藏
            if (! empty($this->uid)) {
                $member = new Member();
                 $list['data'][$key]['is_favorite'] = $member->getIsMemberFavorites($this->uid, $val['goods_id'], 'goods');
            } else {
                 $list['data'][$key]['is_favorite'] = 0;    
            }
        }
        return $list;
    }
}
