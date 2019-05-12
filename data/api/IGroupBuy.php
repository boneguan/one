<?php
/**
 * IAddress.php
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
namespace data\api;

/**
 * 团购接口
 */
interface IGroupBuy
{
    /**
     * 团购列表
     * @param number $page_index
     * @param number $page_size
     * @param string $condition
     * @param string $order
     * @param string $field
     */
    function getPromotionGroupBuyList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*');
    
    /**
     * 获取团购活动详情
     * @param number $group_id
     */
    function getPromotionGroupBuyDetail($group_id);
    
    /**
     * 添加 修改团购
     * @param unknown $shop_id
     * @param unknown $goods_id
     * @param unknown $start_time
     * @param unknown $end_time
     * @param unknown $max_num
     * @param unknown $min_num
     * @param unknown $group_name
     * @param number $group_id
     */
    function addPromotionGroupBuy($shop_id, $goods_id, $start_time, $end_time, $max_num, $group_name, $price_json, $group_id = 0, $remark );
    
    /**
     * 获取商品团购活动信息
     */
    function getGoodsFirstPromotionGroupBuy($goods_id);
    
    /**
     * 删除团购活动
     * @param number $group_id
     */
    function delPromotionGroupBuy($group_id);
    
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
    function groupBuyOrderCreate($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $coin, $distribution_time_out);
    
}
