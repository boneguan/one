<?php
/**
 * NfxPromoter.php
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
use data\service\BaseService;
use data\model\NfxPromoterModel;
use data\model\NfxPromoterLevelModel;
use data\api\INfxCommissionCalculate;
use data\model\NsOrderModel;
use data\model\NsOrderGoodsModel;
use data\model\NsOrderGoodsPromotionDetailsModel;
use data\model\NfxShopMemberAssociationModel;
use data\model\NfxGoodsCommissionRateModel;
use data\model\NfxCommissionDistributionModel;
use data\model\NfxPartnerLevelModel;
use data\model\NfxCommissionPartnerModel;
use data\model\NfxShopConfigModel;
use data\model\NfxCommissionRegionAgentModel;
use data\model\NfxPromoterRegionAgentModel;
use data\model\NfxShopRegionAgentConfigModel;
use data\service\NfxShopConfig;
use think\Log;
/**
 *佣金计算
 */
class NfxPromoter extends BaseService
{
    private $order_info;    //订单信息
    private $buyer_uid;     //购买人
    private $promoter_id;   //购买人推广员
    private $partner_id;    //购买人股东
    private $shop_id;
    private $is_distribution_enable; // 是否开启三级分销
    private $is_distribution_audit; // 推广员是否需要审核
    private $is_regional_agent; // 是否开启区域代理
    private $is_partner_enable; // 股东分红是否开启 
    private $is_global_enable; // 全球分红是否开启
    private $is_distribution_start; // 分销是否申请
    
    
    
    /**
     * 构造函数
     * @param number $order_id
     */
    public function __construct($order_id = 0, $order_goods_ids=null)
    {
        $this->order_info = $this->getOrderInfo($order_id, $order_goods_ids);
        $this->shop_id = 0;
        $this->getShopConfig();
        $this->getPromoter();
    }
    /**
     * 获取订单金额相关信息
     * @param unknown $order_id
     */
    private function getOrderInfo($order_id, $order_goods_ids)
    {
        if($order_id != 0)
        {
            //订单基础信息
            $order_model = new NsOrderModel();
            $order_info = $order_model->get($order_id);
            //订单项信息
            $order_goods_model = new NsOrderGoodsModel();
            $condition["order_id"]=$order_id;
            if(!empty($order_goods_ids)){
                $condition["order_goods_id"]=array("in",$order_goods_ids);
            }
            $order_goods_list = $order_goods_model->getQuery($condition, '*', '');
            foreach ($order_goods_list as $k => $order_goods)
            {
                $order_goods_promotion = new NsOrderGoodsPromotionDetailsModel();
                $promotion_money = $order_goods_promotion->where(['order_id' => $order_id, 'sku_id' => $order_goods['sku_id']])->sum('discount_money');
                if(empty($promotion_money))
                {
                    $promotion_money = 0;
                }
               // $goods_sku = new NsGoodsSkuModel();
               // $goods_sku_info = $goods_sku->getInfo(['sku_id' => $order_goods['sku_id']], 'cost_price');
                $order_goods_list[$k]['promotion_money'] = $promotion_money;
                $order_goods_list[$k]['cost_price'] = $order_goods['cost_price']*$order_goods['num'];
                $order_goods_list[$k]['real_pay'] = $order_goods['goods_money']+$order_goods['adjust_money']-$order_goods['refund_real_money']-$promotion_money;
                

                //判断分销佣金:是0.使用利润or 1.销售价格
                $shop_config = new NfxShopConfig();
                $this->instance_id=0;
                $shop_config_info = $shop_config->getShopConfigDetail($this->instance_id);
                if($shop_config_info['distribution_use']==1){
                      $goods_return_money=$order_goods['goods_money']-$order_goods['refund_real_money']-$promotion_money;
                }else{
                      $goods_return_money=$order_goods['goods_money']-$order_goods['refund_real_money']-$promotion_money-$order_goods['cost_price'];
                }
                
                if ($goods_return_money < 0){
                    $goods_return_money = 0;
                }
                $order_goods_list[$k]['goods_return'] = $goods_return_money; 
                //计算佣金分配信息
                $goods_commission_rate_model = new NfxGoodsCommissionRateModel();
                $commission_rate = $goods_commission_rate_model->getInfo(['goods_id' => $order_goods['goods_id']], '*');
                if(empty($commission_rate))
                {
                    $commission_rate = '';
                }
                $order_goods_list[$k]['commission_rate'] = $commission_rate;
            }
            $order_info['order_goods_list'] = $order_goods_list;
            return $order_info;
        }else{
            return '';
        }
    }
    /**
     * 获取购买人，推广员，股东
     */
    private function getPromoter(){
        if(!empty($this->order_info))
        {
            $this->buyer_uid = $this->order_info['buyer_id'];
            $shop_member_association = new NfxShopMemberAssociationModel();
            $promoter_info = $shop_member_association->getInfo(['shop_id' => $this->shop_id, 'uid' => $this->buyer_uid], 'promoter_id, partner_id');
            
            if(!empty($promoter_info))
            {
                if($this->is_distribution_start == 1){
                    $this->promoter_id = $promoter_info['promoter_id'];
                }else{
                    //判断此推广员id是否是本会员的推广员id
                    $promoter_model = new NfxPromoterModel();
                    $promoter_real_info = $promoter_model -> getInfo(['shop_id' => $this->shop_id, 'uid' => $this->buyer_uid, "promoter_id" => $promoter_info['promoter_id']], "parent_promoter");
                    if(!empty($promoter_real_info)){
                            $this->promoter_id = $promoter_real_info['parent_promoter'];
                    }else{
                            $this->promoter_id = $promoter_info['promoter_id'];
                    }
                }
                $this->partner_id = $promoter_info['partner_id'];
            }else{
                $this->promoter_id = 0;
                $this->partner_id = 0;
            }
           
        }
    }
    private function getShopConfig(){
        $shop_config = new NfxShopConfigModel();
        $shop_config_info = $shop_config->getInfo(['shop_id'=>$this->shop_id]);
        $this->is_distribution_enable = $shop_config_info["is_distribution_enable"];
        $this->is_distribution_audit = $shop_config_info["is_distribution_audit"];
        $this->is_regional_agent = $shop_config_info["is_regional_agent"];
        $this->is_partner_enable = $shop_config_info["is_partner_enable"];
        $this->is_global_enable = $shop_config_info["is_global_enable"];
        $this->is_distribution_start = $shop_config_info["is_distribution_start"];
    }
	/* (non-PHPdoc)
     * @see \data\api\INfxCommissionCalculate::orderdistributionCommission()
     */
    public function orderdistributionCommission()
    {
        if($this->is_distribution_enable == 1){
           $commossion_distribution = new NfxCommissionDistributionModel();
           if(empty($this->order_info))
           {
               return 1;
           }
           
           $commossion_distribution->startTrans();
           try{
               foreach ($this->order_info['order_goods_list'] as $k => $order_goods)
               {
                   if(empty($order_goods['commission_rate']))
                   {
                       continue;
                   }
                   
                   //获取佣金信息
                   $commission_rate = $order_goods['commission_rate'];
                   if(!empty($commission_rate))
                   {
                       if($commission_rate['is_open'])    //启动分销
                       {
                           //查询对应推广员
                           $promoter_id = $this->promoter_id;
                          // Log::write('promoter_id！'.$promoter_id);
                           if($promoter_id != 0)
                           {
                               $promoter_model = new NfxPromoterModel();
                               $promoter_info = $promoter_model->getInfo(['promoter_id' => $promoter_id], 'parent_promoter,promoter_level');
                               $promoter_level = $promoter_info['promoter_level'];
                               $promoter_level_model = new NfxPromoterLevelModel();
                               $promoter_level_info = $promoter_level_model->get($promoter_level);
                               //当前推广员获取佣金
                               $retval = $this->addOrderDistributionCommission($this->shop_id, $promoter_id, $this->order_info['order_id'], $order_goods['order_goods_id'], $order_goods['real_pay'], $order_goods['cost_price'], $order_goods['goods_return'], 0, $commission_rate['distribution_commission_rate'], $promoter_level_info['level_0']);
                               $parent_promoter = $promoter_info['parent_promoter'];
                               if($parent_promoter != 0)
                               {
                                   $this->addOrderDistributionCommission($this->shop_id, $parent_promoter, $this->order_info['order_id'], $order_goods['order_goods_id'], $order_goods['real_pay'], $order_goods['cost_price'], $order_goods['goods_return'], 1, $commission_rate['distribution_commission_rate'], $promoter_level_info['level_1']);
                                   $parent_promoter_info = $promoter_model->getInfo(['promoter_id' => $parent_promoter], 'parent_promoter');
                                   $grand_promoter = $parent_promoter_info['parent_promoter'];
                                   if($grand_promoter != 0)
                                   {
                                        $this->addOrderDistributionCommission($this->shop_id, $grand_promoter, $this->order_info['order_id'], $order_goods['order_goods_id'], $order_goods['real_pay'], $order_goods['cost_price'], $order_goods['goods_return'], 2, $commission_rate['distribution_commission_rate'], $promoter_level_info['level_2']);
                                   }
                               }
                               
                           }
                         
                       
                       }
                   }
               }
               $commossion_distribution->commit();
               return 1;
           } catch (\Exception $e)
           {
               $commossion_distribution->rollback();
               return $e->getMessage();
           }
        }else{
            return 1;
        }
       
        
    }
    /**
     * 订单退款后 更新三级分销的佣金
     * (non-PHPdoc)
     * @see \data\api\INfxCommissionCalculate::updateOrderDistributionCommission()
     */
    public function updateOrderDistributionCommission(){
        $commossion_distribution = new NfxCommissionDistributionModel();
        if(empty($this->order_info))
        {
            return 1;
        }
        $commossion_distribution->startTrans();
        try{
            foreach ($this->order_info['order_goods_list'] as $k => $order_goods)
            {
                #订单项id
                $order_goods_id=$order_goods["order_goods_id"];
                #查询当前订单项产生的三级分销佣金流水
                $commission_list=$commossion_distribution->getQuery(["order_goods_id"=>$order_goods_id], "*", "");
                #得到当前订单的利润
                $goods_return=$order_goods["goods_return"];
                #商品的买价
                $goods_money=$order_goods["real_pay"];
                if(!empty($commission_list) && count($commission_list)>0){
                    foreach ($commission_list as $k=>$commission_obj){
                        $id=$commission_obj["id"];
                        $goods_commission_rate=$commission_obj["goods_commission_rate"];
                        $commission_rate=$commission_obj["commission_rate"];
                        $commission_money=$goods_return*$goods_commission_rate/100*$commission_rate/100;
                        $commossion_distribution->update(["goods_money"=>$goods_money, 
                            "goods_return"=>$goods_return, "commission_money"=>$commission_money], ["id"=>$id]);
                    }
                }
            }
            $commossion_distribution->commit();
            return 1;
        } catch (\Exception $e)
        {
            $commossion_distribution->rollback();
            return $e->getMessage();
        }
    }
    /**
     * 添加佣金分配信息
     * @param unknown $shop_id
     * @param unknown $promoter_id  推广员ID
     * @param unknown $order_id     订单ID
     * @param unknown $order_goods_id  订单商品ID
     * @param unknown $goods_money     商品实际卖价
     * @param unknown $goods_cost
     * @param unknown $goods_return
     * @param unknown $promoter_level
     * @param unknown $goods_commission_rate
     * @param unknown $commission_rate
     */
    private function addOrderDistributionCommission($shop_id, $promoter_id, $order_id, $order_goods_id, $goods_money, $goods_cost,$goods_return, $promoter_level, $goods_commission_rate, $commission_rate)
    {
        $commossion_distribution = new NfxCommissionDistributionModel();
        if($goods_return < 0)
        {
            $goods_return = 0;
        }
   
        $data = array(
            'serial_no'            => getSerialNo(),
            'shop_id'              => $shop_id,
            'promoter_id'          => $promoter_id,
            'promoter_level'       => $promoter_level,
            'order_id'             => $order_id,
            'order_goods_id'       => $order_goods_id,
            'goods_money'          => $goods_money,
            'goods_cost'           => $goods_cost,
            'goods_return'         => $goods_return,
            'goods_commission_rate'=> $goods_commission_rate,
            'commission_rate'      => $commission_rate,
            'commission_money'     => $goods_return*$goods_commission_rate/100*$commission_rate/100,
            'create_time'          => time()
        );
     
        $commossion_distribution->save($data);
        return $commossion_distribution->id;
    }
	/* (non-PHPdoc)
     * @see \data\api\INfxCommissionCalculate::orderPartnerCommission()
     */
    public function orderPartnerCommission()
    {
        if($this->is_partner_enable == 1){
            
            $commission_partner = new NfxCommissionPartnerModel();
            if(empty($this->order_info))
            {
                return 1;
            }
            $commission_partner->startTrans();
            try{
                foreach ($this->order_info['order_goods_list'] as $k => $order_goods)
                {
                    if(empty($order_goods['commission_rate']))
                    {
                        continue;
                    }
                    //获取佣金信息
                    $commission_rate = $order_goods['commission_rate'];
                    if(!empty($commission_rate))
                    {
                        if($commission_rate['is_open'])    //启动分销
                        {
                            //查询对应推广员
                            $partner_id = $this->partner_id;
                            if($partner_id != 0)
                            {
                                
                                 $partner = new NfxPartner();
                                 $parents_array = $partner->getPartnerParents($partner_id);
                                 $partner_commission_rate = 0;
                                 foreach ($parents_array as $k => $parent_partner)
                                 {
                                     $partner_level_model = new NfxPartnerLevelModel();
                                     $partner_level_info = $partner_level_model->get($parent_partner['partner_level']);
                                     if($partner_level_info['commission_rate'] > $partner_commission_rate)
                                     {
                                         $real_commission_rate = $partner_level_info['commission_rate'] - $partner_commission_rate;
                                         $partner_commission_rate = $partner_level_info['commission_rate'];
                                         $this->addOrderPartnerCommission($this->shop_id, $parent_partner['partner_id'], $parent_partner['partner_level'], $partner_id, $this->order_info['order_id'], $order_goods['order_goods_id'], $order_goods['real_pay'], $order_goods['cost_price'], $order_goods['goods_return'], $commission_rate['partner_commission_rate'], $real_commission_rate);
                                         
                                     }
                                 }
                            }
                             
                             
                        }
                    }
                }
                $commission_partner->commit();
                return 1;
            } catch (\Exception $e)
            {
                $commission_partner->rollback();
                return $e->getMessage();
            }
        }else{
            return 1;
        }
    }
    
    /**
     * 重新计算订单的订单的股东分红
     * (non-PHPdoc)
     * @see \data\api\INfxCommissionCalculate::updateOrderPartnerCommission()
     */
    public function updateOrderPartnerCommission(){
        $commission_partner = new NfxCommissionPartnerModel();
        if(empty($this->order_info))
        {
            return 1;
        }
        $commission_partner->startTrans();
        try{
            foreach ($this->order_info['order_goods_list'] as $k => $order_goods)
            {
                #订单项id
                $order_goods_id=$order_goods["order_goods_id"];
                #查询当前订单项产生的股东佣金流水
                $commission_list=$commission_partner->getQuery(["order_goods_id"=>$order_goods_id], "*", "");
                #得到当前订单的利润
                $goods_return=$order_goods["goods_return"];
                #商品的买价
                $goods_money=$order_goods["real_pay"];
                if(!empty($commission_list) && count($commission_list)>0){
                    foreach ($commission_list as $k=>$commission_obj){
                        $id=$commission_obj["id"];
                        $goods_commission_rate=$commission_obj["goods_commission_rate"];
                        $commission_rate=$commission_obj["commission_rate"];
                        $commission_money=$goods_return*$goods_commission_rate/100*$commission_rate/100;
                        $commission_partner->update(["goods_money"=>$goods_money,
                            "goods_return"=>$goods_return, "commission_money"=>$commission_money], ["id"=>$id]);
                    }
                }
            }
            $commission_partner->commit();
            return 1;
        } catch (\Exception $e)
        {
            $commission_partner->rollback();
            return $e->getMessage();
        }
    }
    /**
     * 添加股东分红
     * @param unknown $shop_id
     * @param unknown $partner_id
     * @param unknown $partner_level
     * @param unknown $order_partner_id
     * @param unknown $order_id
     * @param unknown $order_goods_id
     * @param unknown $goods_money
     * @param unknown $goods_cost
     * @param unknown $goods_return
     * @param unknown $goods_commission_rate
     * @param unknown $commission_rate
     */
    private function addOrderPartnerCommission($shop_id, $partner_id, $partner_level,$order_partner_id, $order_id, $order_goods_id, $goods_money, $goods_cost,$goods_return, $goods_commission_rate, $commission_rate)
    {
        
        $commission_partner = new NfxCommissionPartnerModel();
        if($goods_return < 0)
        {
            $goods_return = 0;
        }
        $data = array(
            'serial_no'            => getSerialNo(),
            'shop_id'              => $shop_id,
            'partner_id'           => $partner_id,
            'partner_level'        => $partner_level,
            'order_partner_id'     => $order_partner_id,
            'order_id'             => $order_id,
            'order_goods_id'       => $order_goods_id,
            'goods_money'          => $goods_money,
            'goods_cost'           => $goods_cost,
            'goods_return'         => $goods_return,
            'goods_commission_rate'=> $goods_commission_rate,
            'commission_rate'      => $commission_rate,
            'commission_money'     => $goods_return*$goods_commission_rate/100*$commission_rate/100,
            'create_time'          => time()
        );
        $commission_partner->save($data);
        return $commission_partner->id;
        
    }
 
    /* 区域代理分红
     * @see \data\api\INfxCommissionCalculate::orderRegionAgentCommission()
     */
    public function orderRegionAgentCommission()
    {
        $region_agent_model=new NfxCommissionRegionAgentModel();
        if(empty($this->order_info))
        {
            return 1;
        }
        
        $receiver_province=$this->order_info["receiver_province"];
        $receiver_city=$this->order_info["receiver_city"];
        $receiver_district=$this->order_info["receiver_district"];
        $promote_region_agent_model=new NfxPromoterRegionAgentModel();
        #查询当前县的代理人
        $condition["agent_type"]=1;
        $condition["agent_provinceid"]=$receiver_province;
        $condition["is_audit"]=1;
        $province_region=$promote_region_agent_model->getInfo($condition, "*");
        #查询当前市的代理人
        $condition_city["agent_type"]=2;
        $condition_city["agent_cityid"]=$receiver_city;
        $condition_city["is_audit"]=1;
        $city_region=$promote_region_agent_model->getInfo($condition_city, "*");
        #查询当前区县的代理人
        $condition_district["agent_type"]=3;
        $condition_district["agent_districtid"]=$receiver_district;
        $condition_district["is_audit"]=1;
        $district_region=$promote_region_agent_model->getInfo($condition_district, "*");
        #查询当前店铺的区域代理的分红比率
        $shop_region_agent_model=new NfxShopRegionAgentConfigModel();
        $condition_config["shop_id"]=$this->shop_id;
        $region_agent_config=$shop_region_agent_model->getInfo($condition_config, "*");
        $province_rate=0;
        $city_rate=0;
        $district_rate=0;
        if(!empty($region_agent_config)){
            $province_rate=$region_agent_config["province_rate"];
            $city_rate=$region_agent_config["city_rate"];
            $district_rate=$region_agent_config["district_rate"];
            $region_agent_model->startTrans();
            try{
                foreach ($this->order_info['order_goods_list'] as $k => $order_goods)
                {
                    if(empty($order_goods['commission_rate']))
                    {
                        continue;
                    }
                    //获取佣金信息
                    $commission_rate = $order_goods['commission_rate'];
                    if(!empty($commission_rate))
                    {
                        if($commission_rate['is_open'])    //启动分销
                        {
                            #当前订单项的利润
                            $goods_return=$order_goods["goods_return"];
                            #当前商品所拿出的区域代理比率
                            $region_rate=$commission_rate["regionagent_commission_rate"];
                            #计算区域代理的金额
                            $goods_return=$goods_return*$region_rate/100;
                            #该省具有代理商
                            if(!empty($province_region)){

                                 $province_commission=$goods_return*$province_rate/100;
                                 $this->addOrderRegionAgentCommission($this->shop_id, $province_region["region_agent_id"], 
                                     $province_region["uid"], $province_region["promoter_id"], $order_goods["order_id"], 
                                     $order_goods["order_goods_id"], $order_goods["real_pay"], $order_goods["cost_price"], 
                                     $order_goods["goods_return"], 1, $province_rate, $province_commission, $region_rate);
                            }
                            #该市具有代理商
                            if(!empty($city_region)){
                                $city_commission=$goods_return*$city_rate/100;
                                $this->addOrderRegionAgentCommission($this->shop_id, $city_region["region_agent_id"],
                                    $city_region["uid"], $city_region["promoter_id"], $order_goods["order_id"],
                                    $order_goods["order_goods_id"], $order_goods["real_pay"], $order_goods["cost_price"],
                                    $order_goods["goods_return"], 2, $city_rate, $city_commission, $region_rate);
                            }
                            #该区县具体有代理商
                            if(!empty($district_region)){
                                $district_commission=$goods_return*$district_rate/100;
                                $this->addOrderRegionAgentCommission($this->shop_id, $district_region["region_agent_id"],
                                    $district_region["uid"], $district_region["promoter_id"], $order_goods["order_id"],
                                    $order_goods["order_goods_id"], $order_goods["real_pay"], $order_goods["cost_price"],
                                    $order_goods["goods_return"], 3, $district_rate, $district_commission, $region_rate);
                            }
                        }
                     }
                  }
                $region_agent_model->commit();
                return 1;
                } catch (\Exception $e){
                        $region_agent_model->rollback();
                        return $e->getMessage();
                }
        }else{
            return 1;
        }
    }
    
    /**
     * 重新计算订单的区域代理分红
     * (non-PHPdoc)
     * @see \data\api\INfxCommissionCalculate::updateOrderRegionAgentCommission()
     */
    public function updateOrderRegionAgentCommission(){
        $region_agent_model=new NfxCommissionRegionAgentModel();
        if(empty($this->order_info))
        {
            return 1;
        }
        $region_agent_model->startTrans();
        try{
            foreach ($this->order_info['order_goods_list'] as $k => $order_goods)
            {
                #订单项id
                $order_goods_id=$order_goods["order_goods_id"];
                #查询当前订单项产生的区域代理佣金流水
                $commission_list=$region_agent_model->getQuery(["order_goods_id"=>$order_goods_id], "*", "");
                #得到当前订单的利润
                $goods_return=$order_goods["goods_return"];
                #商品的买价
                $goods_money=$order_goods["real_pay"];
                if(!empty($commission_list) && count($commission_list)>0){
                    foreach ($commission_list as $k=>$commission_obj){
                        $id=$commission_obj["id"];
                        $goods_commission_rate=$commission_obj["goods_commission_rate"];
                        $commission_rate=$commission_obj["commission_rate"];
                        $commission_money=$goods_return*$goods_commission_rate/100*$commission_rate/100;
                        $region_agent_model->update(["goods_money"=>$goods_money,
                        "goods_return"=>$goods_return, "commission"=>$commission_money], ["id"=>$id]);
                    }
                 }
            }
            $region_agent_model->commit();
            return 1;
        } catch (\Exception $e)
        {
            $region_agent_model->rollback();
            return $e->getMessage();
        }
        
    }
    /**
     * 区域代理分红记录添加
     * @param unknown $shop_id
     * @param unknown $region_agent_id
     * @param unknown $uid
     * @param unknown $promoter_id
     * @param unknown $order_id
     * @param unknown $order_goods_id
     * @param unknown $goods_money
     * @param unknown $goods_cost
     * @param unknown $goods_return
     * @param unknown $commission_type
     * @param unknown $commission_rate
     * @param unknown $commission
     */
    private function addOrderRegionAgentCommission($shop_id, $region_agent_id, $uid, $promoter_id, $order_id, $order_goods_id, 
         $goods_money, $goods_cost, $goods_return, $commission_type, $commission_rate, $commission, $goods_commission_rate ){
        $region_agent_model=new NfxCommissionRegionAgentModel();
        $data=array(
            "shop_id"=>$shop_id, 
            "region_agent_id"=>$region_agent_id, 
            "uid"=>$uid, 
            "serial_no"=>getSerialNo(),
            "promoter_id"=>$promoter_id, 
            "order_id"=>$order_id, 
            "order_goods_id"=>$order_goods_id,
            "goods_money"=>$goods_money, 
            "goods_cost"=>$goods_cost, 
            "goods_return"=>$goods_return, 
            "commission_type"=>$commission_type, 
            "commission_rate"=>$commission_rate, 
            "commission"=>$commission,
            "goods_commission_rate"=>$goods_commission_rate
        );
        $region_agent_model->save($data);
        return $region_agent_model->id;
    }

}