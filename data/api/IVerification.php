<?php
/**
 * IVerification.php
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

interface IVerification
{
    /**
     * 获取核销人员列表
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     */
    function getVerificationPersonnelList($page_index, $page_size, $condition, $order);
    
    /**
     * 添加核销人员
     * @param unknown $uid
     * @param unknown $shop_id
     */
    function addVerificationPersonne($uid, $shop_id);
}