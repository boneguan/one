<?php
/**
 * IPromote.php
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
 * 营销接口
 */
interface IPromotion
{

    /**
     * 获取优惠券类型列表
     *
     * @param number $page_index            
     * @param number $page_size            
     * @param string $condition            
     * @param string $order            
     */
    function getCouponTypeList($page_index = 1, $page_size = 0, $condition = '', $order = 'create_time desc');

    /**
     * 删除优惠券
     *
     * @param unknown $coupon_type_id            
     */
    function deletecouponType($coupon_type_id);

    /**
     * 获取优惠券类型详情
     *
     * @param unknown $coupon_type_id
     *            类型主键
     */
    function getCouponTypeDetail($coupon_type_id);

    /**
     * 添加优惠券类型
     *
     * @param unknown $coupon_name            
     * @param unknown $money            
     * @param unknown $count            
     * @param unknown $max_fetch            
     * @param unknown $at_least            
     * @param unknown $need_user_level            
     * @param unknown $range_type            
     * @param unknown $start_time            
     * @param unknown $end_time            
     * @param unknown $goods_list            
     */
    function addCouponType($coupon_name, $money, $count, $max_fetch, $at_least, $need_user_level, $range_type, $start_time, $end_time, $goods_list, $is_show, $term_of_validity_type, $fixed_term);

    /**
     * 修改优惠券类型
     *
     * @param unknown $data            
     */
    function updateCouponType($coupon_id, $coupon_name, $money, $count, $repair_count, $max_fetch, $at_least, $need_user_level, $range_type, $start_time, $end_time, $goods_list, $is_show, $term_of_validity_type, $fixed_term);

    /**
     * 获取店铺积分配置信息
     */
    function getPointConfig();

    /**
     * 店铺积分设置
     *
     * @param unknown $is_open            
     * @param unknown $convert_rate            
     * @param unknown $desc            
     */
    function setPointConfig($convert_rate, $is_open, $desc);

    /**
     * 获取优惠券类型的优惠券列表
     *
     * @param unknown $coupon_type_id            
     * @param unknown $get_type
     *            获取类型 0 标示全部
     * @param unknown $use_type
     *            获取类型 0 标示全部
     */
    function getTypeCouponList($coupon_type_id, $get_type = 0, $use_type = 0);

    /**
     * 获取优惠券详情
     *
     * @param unknown $coupon_id            
     */
    function getCouponDetail($coupon_id);

    /**
     * 赠品活动列表
     *
     * @param number $page_index            
     * @param number $page_size            
     * @param string $condition            
     * @param string $order            
     */
    function getPromotionGiftList($page_index = 1, $page_size = 0, $condition = '', $order = 'create_time desc');

    /**
     * 添加赠品活动
     *
     * @param unknown $gift_name            
     * @param unknown $start_time            
     * @param unknown $end_time            
     * @param unknown $days            
     * @param unknown $max_num            
     * @param unknown $goods_id            
     */
    function addPromotionGift($shop_id, $gift_name, $start_time, $end_time, $days, $max_num, $goods_id);

    /**
     * 修改赠品活动
     *
     * @param unknown $gift_id            
     * @param unknown $shop_id            
     * @param unknown $gift_name            
     * @param unknown $start_time            
     * @param unknown $end_time            
     * @param unknown $days            
     * @param unknown $max_num            
     * @param unknown $goods_id_array            
     */
    function updatePromotionGift($gift_id, $shop_id, $gift_name, $start_time, $end_time, $days, $max_num, $goods_id_array);

    /**
     * 删除赠品
     */
    function deletePromotionGift($gift_id);

    /**
     * 获取赠品详情
     *
     * @param unknown $gift_id            
     */
    function getPromotionGiftDetail($gift_id);

    /**
     * 获取满减送列表
     *
     * @param number $page_index            
     * @param number $page_size            
     * @param string $condition            
     * @param string $order            
     */
    function getPromotionMansongList($page_index = 1, $page_size = 0, $condition = '', $order = 'create_time desc');

    /**
     * 添加满减送活动
     *
     * @param unknown $mansong_name            
     * @param unknown $start_time            
     * @param unknown $end_time            
     * @param unknown $shop_id            
     * @param unknown $remark            
     * @param unknown $type            
     * @param unknown $range_type            
     * @param unknown $rule
     *            price,discount,fee_shipping,give_point,give_coupon,gift_id;price,discount,fee_shipping,give_point,give_coupon,gift_id
     * @param unknown $goods_id_array            
     */
    function addPromotionMansong($mansong_name, $start_time, $end_time, $shop_id, $remark, $type, $range_type, $rule, $goods_id_array);

    /**
     * 修改满减送活动
     *
     * @param unknown $mansong_id            
     * @param unknown $mansong_name            
     * @param unknown $start_time            
     * @param unknown $end_time            
     * @param unknown $shop_id            
     * @param unknown $remark            
     * @param unknown $type            
     * @param unknown $range_type            
     * @param unknown $rule
     *            price,discount,fee_shipping,give_point,give_coupon,gift_id;price,discount,fee_shipping,give_point,give_coupon,gift_id
     * @param unknown $goods_id_array            
     */
    function updatePromotionMansong($mansong_id, $mansong_name, $start_time, $end_time, $shop_id, $remark, $type, $range_type, $rule, $goods_id_array);

    /**
     * 获取满减送详情
     *
     * @param unknown $mansong_id            
     */
    function getPromotionMansongDetail($mansong_id);

    /**
     * 添加限时折扣
     *
     * @param unknown $discount_name            
     * @param unknown $start_time            
     * @param unknown $end_time            
     * @param unknown $remark            
     * @param unknown $goods_id_array
     *            goods_id:discount,goods_id:discount
     */
    function addPromotiondiscount($discount_name, $start_time, $end_time, $remark, $goods_id_array, $decimal_reservation_number);

    /**
     * 修改限时折扣
     *
     * @param unknown $discount_id            
     * @param unknown $discount_name            
     * @param unknown $start_time            
     * @param unknown $end_time            
     * @param unknown $remark            
     * @param unknown $goods_id_array            
     */
    function updatePromotionDiscount($discount_id, $discount_name, $start_time, $end_time, $remark, $goods_id_array, $decimal_reservation_number);

    /**
     * 关闭限时折扣活动
     *
     * @param unknown $discount_id            
     */
    function closePromotionDiscount($discount_id);

    /**
     * 获取限时折扣列表
     *
     * @param number $page_index            
     * @param number $page_size            
     * @param string $condition            
     * @param string $order            
     */
    function getPromotionDiscountList($page_index = 1, $page_size = 0, $condition = '', $order = 'create_time desc');

    /**
     * 获取限时折扣详情
     *
     * @param unknown $discount_id            
     */
    function getPromotionDiscountDetail($discount_id);

    /**
     * 删除限时折扣
     *
     * @param unknown $discount_id            
     */
    function delPromotionDiscount($discount_id);

    /**
     * 关闭满减送活动
     *
     * @param unknown $mansong_id            
     */
    function closePromotionMansong($mansong_id);

    /**
     * 删除满减送活动
     *
     * @param unknown $mansong_id            
     */
    function delPromotionMansong($mansong_id);

    /**
     * 得到店铺的满额包邮信息
     *
     * @param unknown $shop_id            
     */
    function getPromotionFullMail($shop_id);

    /**
     * 更新店铺的满额包邮活动
     *
     * @param unknown $shop_id
     *            店铺id
     * @param unknown $is_open
     *            是否开启
     * @param unknown $full_mail_money
     *            包邮所需金额
     * @param unknown $no_mail_province_id_array
     *            不包邮省id组
     * @param unknown $no_mail_city_id_array
     *            不包邮市id组
     */
    function updatePromotionFullMail($shop_id, $is_open, $full_mail_money, $no_mail_province_id_array, $no_mail_city_id_array);

    /**
     * 添加或编辑组合套餐
     *
     * @param unknown $id            
     * @param unknown $combo_package_name            
     * @param unknown $combo_package_price            
     * @param unknown $goods_id_array            
     * @param unknown $is_shelves            
     * @param unknown $shop_id            
     */
    function addOrEditComboPackage($id, $combo_package_name, $combo_package_price, $goods_id_array, $is_shelves, $shop_id, $original_price, $save_the_price);

    /**
     * 获取组合套餐详情
     *
     * @param unknown $id            
     */
    function getComboPackageDetail($id);

    /**
     * 获取组合套餐列表
     *
     * @param unknown $page_index            
     * @param unknown $page_size            
     * @param unknown $condition            
     * @param string $order            
     * @param string $field            
     */
    function getComboPackageList($page_index, $page_size, $condition, $order = "", $field = "*");

    /**
     * 删除组合套餐
     *
     * @param unknown $ids            
     */
    function deleteComboPackage($ids);

    /**
     *
     * @param number $page_index            
     * @param number $page_size            
     * @param string $condition            
     * @param string $order            
     */
    function getCouponTypeInfoList($page_index = 1, $page_size = 0, $condition = '', $order = '', $uid = 0);

    /**
     * 添加赠品发放记录
     *
     * 创建时间：2018年1月25日11:20:42
     *
     * @param 店铺id $shop_id            
     * @param 会员id $uid            
     * @param 会员昵称 $nick_name            
     * @param 礼品id $gift_id            
     * @param 礼品名称 $gift_name            
     * @param 商品图id $goods_picture            
     * @param 商品名称 $goods_name            
     * @param 领取类型1.满减2.游戏... $type            
     * @param 类型名称 $type_name            
     * @param 关联id $relate_id            
     * @param 备注 $remark            
     */
    function addPromotionGiftGrantRecords($shop_id, $uid, $nick_name, $gift_id, $gift_name, $goods_name, $goods_picture, $type, $type_name, $relate_id, $remark);

    /**
     * 获取赠品发放记录列表
     * 创建时间：2018年1月25日15:45:56
     *
     * @param unknown $shop_id            
     */
    function getPromotionGiftGrantRecordsList($page_index, $page_size, $condition, $order);
}