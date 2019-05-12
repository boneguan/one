<?php
/**
 * IBargain.php
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
 * @date : 2017年9月18日
 * @version : v1.0.0.0
 */
namespace data\api;
interface IBargain
{
    /**
     * 砍价配置信息
     * @param unknown $is_use  是否启用
     * @param unknown $activity_time 活动时间
     * @param unknown $bargain_max_number 最大可砍次数
     * @param unknown $cut_methods  刀法库
     * @param unknown $launch_cut_method 发起者刀法名
     * @param unknown $propaganda 宣传语
     * @param unknown $rule 规格介绍
     */
    function setConfig($is_use, $activity_time, $bargain_max_number, $cut_methods, $launch_cut_method, $propaganda, $rule);
    
    /**
     * 获取砍价配置信息
     */
    function getConfig();
    
    /**
     * 砍价活动添加/修改
     * @param number $bargain_id 0添加 
     */
    function setBargain($bargain_id, $bargain_name, $start_time, $end_time, $bargain_min_rate, $bargain_min_number, $one_min_rate, $one_max_rate, $goods_array, $remark = '');
    
    /**
     * 获取砍价活动信息
     */
    function getBargainInfo($bargain_id, $condition = []);
    
    /**
     * 获取砍价活动的详情信息
     * @param unknown $bargain_id
     * @param unknown $condition
     */
    function getBargainDetail($bargain_id, $condition = []);
    
    /**
     * 获取砍价活动列表
     * @param number $page_index
     * @param number $page_size
     * @param string $condition
     * @param string $order
     * @param string $field
     */
    function getBargainList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*');
    
    /**
     * 删除砍价活动
     * @param unknown $bargain_id
     */
    function delBargain($bargain_id);
    
    /**
     * 添加参加砍价活动的商品
     * @param unknown $bargain_id
     * @param unknown $goods_array
     */
    function addBargainGoods($bargain_id, $goods_array);
    
    /**
     * 获取该砍价活动下的商品
     * @param unknown $bargain_id
     * @param string $condition
     */
    function getBargainGoodsList($bargain_id, $condition = []);
    
    /**
     * 获取该砍价活动下的商品详情
     * @param unknown $bargain_id
     * @param unknown $goods_id
     */
    function getBargainGoodsInfo($bargain_id, $goods_id);
    
    /**
     * 发起砍价
     * @param unknown $bargain_id
     * @param unknown $sku_id
     * @param unknown $address_id
     */
    function addBargainLaunch($bargain_id, $sku_id, $address_id, $distribution_type);
    
    /**
     * 发起砍价详情
     * @param unknown $launch_id
     * @param string $condition
     */
    function getBargainLaunchInfo($launch_id, $condition = []);
    
    /**
     * 获取发起砍价的列表
     * @param number $page_index
     * @param number $page_size
     * @param string $condition
     * @param string $order
     * @param string $field
     */
    function getBargainLaunchList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*');
    
    /**
     * 修改发起砍价的状态 2活动结束 -1取消
     * @param unknown $launch_id
     */
    function setBargainLaunchStatus($launch_id, $status);
    
    /**
     * 添加参与砍价的信息
     * @param unknown $launch_id
     */
    function addBargainPartake($launch_id, $bargain_money = 0);
    
    /**
     * 获取参与砍价的列表
     * @param unknown $launch_id
     */
    function getBargainPartakeList($launch_id);
    
    /**
     * 更新发起该活动的砍价记录
     * @param unknown $launch_id
     */
    function setBargainPartakeRecord($launch_id);

}