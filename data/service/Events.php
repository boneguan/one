<?php
/**
 * Events.php
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
use data\api\IEvents;
use data\model\NsPromotionMansongModel;
use data\service\Order;
use data\model\NsOrderModel;
use data\model\NsPromotionMansongGoodsModel;
use data\model\NsPromotionDiscountModel;
use data\model\NsPromotionDiscountGoodsModel;
use data\model\NsGoodsSkuModel;
use data\model\NsGoodsModel;
use data\model\NsCouponModel;
use data\model\NsPromotionGamesModel;
use think\Log;
use data\model\NsTuangouGroupModel;
use data\model\NsPromotionGroupBuyModel;
use data\model\NsOrderPresellModel;
use data\model\NsPromotionBargainGoodsModel;
use data\model\NsPromotionTopicModel;
use data\model\NsPromotionBargainModel;
use data\model\NsPromotionBargainLaunchModel;
use data\model\NsOrderGoodsModel;
use data\model\AlbumPictureModel;

/**
 * 计划任务
 */
class Events implements IEvents{
    /**
     * (non-PHPdoc)
     * @see \data\api\IEvents::giftClose()
     */
    public function giftClose(){
        
    }
    /**
     * (non-PHPdoc)
     * @see \data\api\IEvents::mansongClose()
     */
    public function mansongOperation(){
        $mansong = new NsPromotionMansongModel();
        $mansong->startTrans();
        try{
            $time = time();
            $condition_close = array(
                'end_time' => array('LT', $time),
                'status'   => array('NEQ', 3)
            );
            $condition_start = array(
                'start_time' => array('ELT', $time),
                'status'   => 0
            );
            $mansong->save(['status' => 4], $condition_close);
            $mansong->save(['status' => 1], $condition_start);
            $mansong_goods = new NsPromotionMansongGoodsModel();
            $mansong_goods->save(['status' => 4], $condition_close);
            $mansong_goods->save(['status' => 1], $condition_start);
            $mansong->commit();
            return 1;
        }catch (\Exception $e)
        {
            $mansong->rollback();
            return $e->getMessage();
        }
       
    }
    /**
     * (non-PHPdoc)
     * @see \data\api\IEvents::ordersClose()
     */
    public function ordersClose(){
        $order_model = new NsOrderModel();
       
        try{
            $config = new Config();
            $config_info = $config->getConfig(0, 'ORDER_BUY_CLOSE_TIME');

            if(!empty($config_info['value']) && $config_info['value'] != 0)
            {
                $close_time = $config_info['value'];
            }else{
                return 1;
            }
            $time = time()-$close_time*60;//订单自动关闭
            $condition = array(
                'order_status' => array('in','0'),
                'create_time'  => array('LT', $time),
                'payment_type' => array('neq', 6)
            );
            $order_list = $order_model->getQuery($condition, 'order_id', '');
            $presell_order_condition = array(
                'order_status' => array('in','6'),
                'order_type' => 6,
                'create_time'  => array('LT', $time),
            );
            $presell_order_list = $order_model -> getQuery($presell_order_condition, 'order_id', '');
            if(!empty($order_list))
            {   
                if(!empty($presell_order_list)){
                    foreach ($presell_order_list as $v){
                        array_push($order_list, $v);
                    }
                }
                $order = new Order();
                foreach ($order_list as $k => $v)
                {
                    if(!empty($v['order_id']))
                    {
                        $order->orderClose($v['order_id']);
                    }
                   
                }
                    
            }
            return 1;
        }catch (\Exception $e)
        {
            return $e->getMessage();
        }
        
    }
    /**
     * (non-PHPdoc)
     * @see \data\api\IEvents::ordersComplete()
     */
    public function ordersComplete(){
    	
        $order_model = new NsOrderModel();
        try{
            
            $config = new Config();
            $config_info = $config->getConfig(0, 'ORDER_DELIVERY_COMPLETE_TIME');
                        
            if($config_info['value'] != '')
            {
                $complete_time = $config_info['value'];
            }else{
                $complete_time = 7;//7天
            }
            $time = time()-3600*24*$complete_time;//订单自动完成

            $condition = array(
                'order_status' => 3,
               	'sign_time'  => array('LT', $time)
            );
            $order_list = $order_model->getQuery($condition, 'order_id', '');
          
            if(!empty($order_list))
            {
                $order = new Order();
                
                foreach ($order_list as $k => $v)
                {
                	Log::write('执行条数---'.$k);
                    if(!empty($v['order_id']))
                    {
                        $order->orderComplete($v['order_id']);
                    }
                    
                }
        
            }
     
            return 1;
        }catch (\Exception $e)
        {
            return $e->getMessage();
        }
    }
    /**
     * (non-PHPdoc)
     * @see \data\api\IEvents::discountOperation()
     */
    public function discountOperation(){
        $discount = new NsPromotionDiscountModel();
        $discount->startTrans();
        try{
            $time = time();
            $discount_goods = new NsPromotionDiscountGoodsModel();
            /************************************************************结束活动**************************************************************/
            $condition_close = array(
                'end_time' => array('LT', $time),
                'status'   => array('NEQ', 3)
            );
             $discount->save(['status' => 4], $condition_close);
             $discount_close_goods_list = $discount_goods->getQuery($condition_close, '*', '');
             if(!empty($discount_close_goods_list))
             {
                 foreach ( $discount_close_goods_list as $k => $discount_goods_item)
                 {
                     $goods = new NsGoodsModel();
             
                     $data_goods = array(
                         'promotion_type' => 2,
                         'promote_id'     => $discount_goods_item['discount_id']
                     );
                     $goods_id_list = $goods->getQuery($data_goods, 'goods_id', '');
                     if(!empty($goods_id_list))
                     {
                         foreach($goods_id_list as $k => $goods_id)
                         {
                             $goods_info = $goods->getInfo(['goods_id' => $goods_id['goods_id']], 'promotion_type,price');
                             $goods->save(['promotion_price' => $goods_info['price']], ['goods_id'=> $goods_id['goods_id'] ]);
                             $goods_sku = new NsGoodsSkuModel();
                             $goods_sku_list = $goods_sku->getQuery(['goods_id'=> $goods_id['goods_id'] ], 'price,sku_id', '');
                             foreach ($goods_sku_list as $k_sku => $sku)
                             {
                                 $goods_sku = new NsGoodsSkuModel();
                                 $data_goods_sku = array(
                                     'promote_price' => $sku['price']
                                 );
                                 $goods_sku->save($data_goods_sku, ['sku_id' => $sku['sku_id']]);
                             }
                             
                         }
                        
                     }
                     $goods->save(['promotion_type' => 0, 'promote_id' => 0], $data_goods);
                    
                 }
             }
             $discount_goods->save(['status' => 4], $condition_close);
             /************************************************************结束活动**************************************************************/
             /************************************************************开始活动**************************************************************/
            $condition_start = array(
                'start_time' => array('ELT', $time),
                'status'   => 0
            );
            //查询待开始活动列表
            $discount_goods_list = $discount_goods->getQuery($condition_start, '*', '');
            if(!empty($discount_goods_list))
            {
                foreach ( $discount_goods_list as $k => $discount_goods_item)
                {
                    $goods = new NsGoodsModel();
                    $goods_info = $goods->getInfo(['goods_id' => $discount_goods_item['goods_id']],'promotion_type,price');
                    
                    $promotion_price = $goods_info['price'] * $discount_goods_item['discount']/10;
                    if($discount_goods_item['decimal_reservation_number'] >= 0){
                        $promotion_price = sprintf("%.2f", round($promotion_price, $discount_goods_item['decimal_reservation_number']));
                    }
                    
                    $data_goods = array(
                        'promotion_type' => 2,
                        'promote_id'     => $discount_goods_item['discount_id'],
                        'promotion_price'  => $promotion_price
                    );
                    
                    $goods->save($data_goods,['goods_id' => $discount_goods_item['goods_id']]);
                    $goods_sku = new NsGoodsSkuModel();
                    $goods_sku_list = $goods_sku->getQuery(['goods_id'=> $discount_goods_item['goods_id'] ], 'price,sku_id', '');
                    foreach ($goods_sku_list as $k_sku => $sku)
                    {
                        $goods_sku = new NsGoodsSkuModel();
                        $promote_price = $sku['price']*$discount_goods_item['discount']/10;
                        if($discount_goods_item['decimal_reservation_number'] >= 0){
                            $promote_price = sprintf("%.2f", round($promote_price, $discount_goods_item['decimal_reservation_number']));
                        }
                        $data_goods_sku = array(
                            'promote_price' => $promote_price
                        );
                        $goods_sku->save($data_goods_sku, ['sku_id' => $sku['sku_id']]);
                    }
                }
            }
            $discount_goods->save(['status' => 1], $condition_start);
            $discount->save(['status' => 1], $condition_start);
            /************************************************************开始活动**************************************************************/
            $discount->commit();
            return 1;
        }catch (\Exception $e)
        {
            $discount->rollback();
            return $e;
        }
    }
    /**
     * (non-PHPdoc)
     * @see \data\api\IEvents::autoDeilvery()
     */
    public function autoDeilvery(){
        $order_model = new NsOrderModel();

        try{
        
            $config = new Config();
            $config_info = $config->getConfig(0, 'ORDER_AUTO_DELIVERY');
            if(!empty($config_info['value'])&$config_info['value'] != 0)
            {
                $delivery_time = $config_info['value'];
            }else{
                return 1;
            }
            $time = time()-3600*24*$delivery_time;//订单自动完成
        
            $condition = array(
                'order_status' => 2,
                'consign_time'  => array('LT', $time)
            );
            $order_list = $order_model->getQuery($condition, 'order_id', '');
             if(!empty($order_list))
            {
                $order = new \data\service\Order\Order();
                foreach ($order_list as $k => $v)
                {
                    if(!empty($v['order_id']))
                    {
                        $order->orderAutoDelivery($v['order_id']);
                    }
                    
                }
        
            } 

            return 1;
        }catch (\Exception $e)
        {
            return $e->getMessage();
        }
    }
    
    /**
     * 优惠券自动过期
     * {@inheritDoc}
     * @see \data\api\IEvents::autoCoupon()
     */
    public function autoCouponClose(){
        $ns_coupon_model = new NsCouponModel();
        $ns_coupon_model->startTrans();
        try{
            $condition['end_time'] = array(['LT', time()], ['NEQ', 0]);
            $condition['state'] = array('NEQ',2);//排除已使用的优惠券
            $count = $ns_coupon_model->getCount($condition);
            $res = -1;
            if($count){
                $res = $ns_coupon_model->save(['state'=>3],$condition);
            }
            $ns_coupon_model->commit();
            return $res;
        }catch (\Exception $e)
        {
            $ns_coupon_model->rollback();
            return $e->getMessage();
        }
    }
    
    /**
     * 营销游戏自动执行操作，改变活动状态
     * 创建时间：2018年1月30日11:45:48 王永杰
     */
    public function autoPromotionGamesOperation(){
        $model = new NsPromotionGamesModel();
        $model->startTrans();
        try{
            $time = time();
            
            //活动开始条件：当前时间大于开始时间，并且活动状态等于0（未开始）
            $condition_start = array(
                'start_time' => array('ELT', $time),
                'status'   => 0
            );
            
            //活动结束条件：当前时间大于结束时间，并且活动状态不等于-1（已结束）
            $condition_close = array(
                'end_time' => array('LT', $time),
                'status'   => array('NEQ', -1)
            );
            
            $start_count = $model->getCount($condition_start);
            $close_count = $model->getCount($condition_close);
            
            if($start_count){
                $model->save(['status'=>1],$condition_start);
            }
            
            if($close_count){
                $model->save(['status'=>-1],$condition_close);
            }
            
            $model->commit();
        }catch(\Exception $e){
            $model->rollback();
            return $e->getMessage();
        }
    }
    /*
     * (non-PHPdoc)
     * @see \data\api\IEvents::pintuanGroupClose()
     */
    public function pintuanGroupClose()
    {
        // TODO Auto-generated method stub
        // 拼团过期时关闭拼团订单
        $pintuan_group = new NsTuangouGroupModel();
        $pintuan_group->startTrans();
        try {
            $condition['end_time'] = array(
                'LT',
                time()
            );
            $condition['status'] = array(
                'EQ',
                1
            ); 
            $count = $pintuan_group->getCount($condition);
            $res = - 1;
            if ($count) {
                $res = $pintuan_group->save([
                    'status' => - 1
                ], $condition);
            }
            $pintuan_group->commit();
            return $res;
        } catch (\Exception $e) {
            $pintuan_group->rollback();
            return $e->getMessage();
        }
    }
    
    /*
     * 团购活动自动过期
     */
   public function  autoGroupBuyClose()
   {
       $promotion_group_buy = new NsPromotionGroupBuyModel();
       $promotion_group_buy->startTrans();
       try{
           $condition['end_time'] = array('LT',time());
           $condition['status'] = array('NEQ',0);//排成已使用的 团购
           $count = $promotion_group_buy->getCount($condition);
           $res = -1;
           if($count){
               $res = $promotion_group_buy->save(['status'=>-1],$condition);
           }
           $promotion_group_buy->commit();
           return $res;
       }catch (\Exception $e)
       {
           $promotion_group_buy->rollback();
           return $e->getMessage();
       }
   }
   
   /**
    * 预售订单预售结束
    */
   public function autoPresellOrder(){
       
       $presell_order_model = new NsOrderPresellModel();
       $presell_order_model->startTrans();
       
       try {
        
           $condition = array(
               'order_status' => 1,
               'presell_delivery_time' => array('elt', time())
//                'presell_delivery_time' => array('elt', 1522293275)
           );
           $presell_order_list = $presell_order_model->getQuery($condition, 'relate_id, payment_type, is_full_payment', '');
          
           $presell_order_model->save(['order_status'=> 2], $condition);
           
           foreach($presell_order_list as $item){
               $order_model = new NsOrderModel();
               $order_condition = array(
                   'order_id' => $item['relate_id'],
                   'order_status' => 7
               );
               $order_model->save(['order_status'=>0], $order_condition);
               
               if($item['is_full_payment'] == 1){
                   $order_service = new Order();
                   $order_service->orderOffLinePay($item['relate_id'], $item['payment_type'], 0); // 默认微信支付
               }
           }
           $presell_order_model->commit();
       } catch (\Exception $e) {
           
            $presell_order_model->rollback();
           return $e->getMessage();
       }
       
   }
   
   /**
    * 专题活动自动状态		
    */
   public function autoTopicClose()
   {
	   	$model = new NsPromotionTopicModel();
	   	$model->startTrans();
	   	try{
	   		$time = time();
	   	
	   		//活动开始条件：当前时间大于开始时间，并且活动状态等于0（未开始）
	   		$condition_start = array(
	   				'start_time' => array('ELT', $time),
	   				'status'   => 0
	   		);
	   	
	   		//活动结束条件：当前时间大于结束时间，并且活动状态不等于4（已结束）
	   		$condition_close = array(
	   				'end_time' => array('LT', $time),
	   				'status'   => array('NEQ', 4)
	   		);
	   	
	   		$start_count = $model->getCount($condition_start);
	   		$close_count = $model->getCount($condition_close);
	   	
	   		if($start_count){
	   			$model->save(['status'=>1],$condition_start);
	   		}
	   	
	   		if($close_count){
	   			$model->save(['status'=>4],$condition_close);
	   		}
	   	
	   		$model->commit();
	   	}catch(\Exception $e){
	   		$model->rollback();
	   		return $e->getMessage();
	   	}
   }
   
   /**
    * 砍价操作
    */
   public function bargainOperation(){
       
       $bargain = new NsPromotionBargainModel();
       $bargain->startTrans();
       try{
           $time = time();
           $condition_close = array(
               'end_time' => array('LT', $time),
               'status'   => array('NEQ', 3)
           );
           $condition_start = array(
               'start_time' => array('ELT', $time),
               'status'   => 0
           );
           $bargain->save(['status' => 4], $condition_close);
           
           $bargain_goods = new NsPromotionBargainGoodsModel();
           $bargain_goods->save(['status' => 4], $condition_close);
           
           //只有砍价配置是开启的状态下才能开启砍价活动
           $bargain_service = new Bargain();
           $config = $bargain_service->getConfig();
           if($config['is_use'] == 1){
               $bargain->save(['status' => 1], $condition_start);
               $bargain_goods->save(['status' => 1], $condition_start);
           }
           
           $bargain_launch = new NsPromotionBargainLaunchModel();
          
           $condition_close_launch = array(
               'end_time' => array('LT', $time),
               'status'   => 1
           );
           
           $address = new Address();
           $list = $bargain_launch->getQuery($condition_close_launch, 'launch_id,end_time, bargain_money,sku_id, launch_id, receiver_mobile, receiver_province, receiver_city, receiver_district, receiver_address, receiver_zip, receiver_name, uid, shipping_type, pick_up_id', '');
                
           foreach($list as $item){
               //创建订单
           	
               $order_service = new Order();
               $out_trade_no = $order_service->getOrderTradeNo();
               $address_info = $address -> getAddress($item['receiver_province'], $item['receiver_city'], $item['receiver_district']);
               $address_info .= '&nbsp;'.$item['receiver_address'];
               $order_id = $order_service->orderCreateBragain($out_trade_no, $item['sku_id'].":1",$item['receiver_mobile'], $item['receiver_province'], $item['receiver_city'],$item['receiver_district'],$address_info, $item['receiver_zip'], $item['receiver_name'], $item['bargain_money'], $item['uid'], $item['launch_id'], $item['shipping_type'], $item['pick_up_id']);
               $bargain_launch = new NsPromotionBargainLaunchModel();
               $bargain_launch->save(['status' => 2, 'order_id'=>$order_id], ['launch_id' => $item['launch_id']]);
               if($order_id){
                   // 砍价成功用户通知
                   runhook("Notify", "bargainSuccessOrFailUser", [
                       'launch_id' => $item['launch_id'],
                       'order_no' => $out_trade_no,
                       'type' => 'success'
                   ]);
                   // 砍价成功商家通知
                   runhook("Notify", "bargainSuccessBusiness", [
                       'launch_id' => $item['launch_id'],
                       'order_no' => $out_trade_no
                   ]);
               }
           }
           
           $bargain->commit();
           return 1;
       }catch (\Exception $e)
       {
           $bargain->rollback();
           return $e->getMessage();
       }
   }
   
   /**
    * 订单自动评价
    */
   public function ordersAutoEvaluate(){
       $order = new Order();
       $config = new Config();
       $config_info = $config -> getConfig(0, 'SYSTEM_DEFAULT_EVALUATE');
       $config_info = json_decode($config_info['value'], true);
       
       if($config_info['day'] > 0){
           $time = time() - ($config_info['day'] * 24 * 60 * 60);
           $condition = array(
               'order_status' => 4,
               'finish_time' => ['<', $time],
               'is_evaluate' => 0,
               'is_deleted' => 0
           );
           
           $ns_order = new NsOrderModel();
           $count = $ns_order -> getCount($condition);

           if($count){
               try {
                   $order_list = $ns_order -> getQuery($condition, 'order_id,order_no,user_name,order_id,shop_id,buyer_id', '');
                   $ns_order_goods = new NsOrderGoodsModel();
                   foreach ($order_list as $order_item){
                       $order_goods_list = $order_goods_condition = array(
                           'is_evaluate' => 0,
                           'order_id' => $order_item['order_id']
                       );
                       $order_goods_list = $ns_order_goods -> getQuery($order_goods_list, 'order_goods_id,goods_id,goods_name,goods_money,goods_picture', '');
                       
                       
                       if(!empty($order_goods_list)){
                           $data = array();
                           foreach ($order_goods_list as $order_goods_item){
                           		$picture_model = new AlbumPictureModel();
                           		$img_info = $picture_model->getInfo(['pic_id'=>$order_goods_item['goods_picture']]); 
                               array_push($data,
                                   array(
                                       'order_id' => $order_item['order_id'],
                                       'order_no' => $order_item['order_no'],
                                       'order_goods_id' => $order_goods_item['order_goods_id'],
                                       'goods_id' => $order_goods_item['goods_id'],
                                       'goods_name' => $order_goods_item['goods_name'],
                                       'goods_price' => $order_goods_item['goods_money'],
                                       'goods_image' => $img_info['pic_cover_small'],
                                       'shop_id' => $order_item['shop_id'],
                                       'shop_name' => "默认",
                                       'content' => $config_info['evaluate'],
                                       'addtime' => time(),
                                       'image' => '',
                                       'member_name' => $order_item['user_name'],
                                       'explain_type' => 1,
                                       'uid' => $order_item['buyer_id'],
                                       'is_anonymous' => 1,
                                       'scores' => 5
                                   )
                               );
                           }
                           $order -> addGoodsEvaluate($data, $order_item['order_id']);
                       }
                   }               
               } catch (\Exception $e) {
                   Log::write("系统默认评价错误，错误信息".$e->getMessage());
               }
           }
       }
   }
   
   
}
