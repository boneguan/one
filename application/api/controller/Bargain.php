<?php

/**
 * Order.php
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

use data\service\Member;
use data\service\User;
use data\service\Bargain as BargainService;

/**
 * 砍价控制器
 */
class Bargain extends BaseController

{

    /**
     * 砍价列表
     */
    public function bargainList()
    {
        $title = '砍价专区';

        $bargain = new BargainService ();

        $page_index = request()->post("page", 1);

        $condition = [
            'status' => 1
        ];

        $list = $bargain->getBargainGoodsPage($page_index, PAGESIZE, $condition);

        return $this->outMessage($title, $list);
    }

    /**
     * 砍价商品发起页面
     */
    public function bargainLaunch()
    {
        $title = '砍价活动页';

        $launch_id = request()->post('launch_id', 0);

        if ($launch_id == 0) {
            return $this->outMessage($title, '', '-50', "无法获取砍价活动信息");
        }
        if ($this->uid == 0 || empty($this->uid)) {
            return $this->outMessage($title, '', '-9999', "无法获取会员登录信息");
        }

        $bargain = new BargainService ();
        $member = new Member ();
        $user = new User ();
        $data = [];
        $launch_info = $bargain->getBargainLaunchInfo($launch_id);
        // 砍价主用户信息
        $user_info = $user->getUserInfoByUid($launch_info ['uid']);

        $is_self = 1;
        if ($this->uid != $launch_info ['uid']) {
            // 说明是分享出去的砍刀
            $is_self = 0;
        }
        $data ['is_self'] = $is_self;
        // 分享出去的需要手动砍刀

        // 砍价的商品信息
        $goods_info = $bargain->getBragainBySkuGoodsInfo($launch_info ['bargain_id'], $launch_info ['sku_id']);
        $partake = $bargain->getConfig();
        $surplus = number_format($launch_info ['goods_money'] - $launch_info ['bargain_money'] - $launch_info ['bargain_min_money'], 2, ".", "");
        $surplus = $surplus == '-0.00' ? 0.00 : $surplus;
        $launch_info ['surplus'] = $surplus;

        // 参与该活动的商品详情
        $bargain_goods_info = $bargain->getBargainGoodsInfo($launch_info ['bargain_id'], $launch_info ['goods_id']);

        // 参团列表
        $partake_list = $bargain->getBargainPartakeList($launch_id);
        $is_max_partake = $bargain->getBragainLaunchIsPartakeMax($this->uid, $launch_id);

        $data ['surplus'] = $surplus;
        $data ['partake_info'] = $partake;
        $data ['user_info'] = $user_info;
        $data ['launch_info'] = $launch_info;
        $data ['goods_info'] = $goods_info;
        $data ['launch_id'] = $launch_id;
        $data ['bargain_goods_info'] = $bargain_goods_info;
        $data ['partake_list'] = $partake_list;
        $data ['is_max_partake'] = $is_max_partake;
        $data ['current_time'] = $this->getCurrentTime();

        return $this->outMessage($title, $data);
    }

    // 帮助好友砍价
    public function helpBargain()
    {
        $title = '帮好友砍价接口';
        if (empty($this->uid)) {
        return $this->outMessage($title, '', -9999, '无法获取会员登录信息');
    }
        $launch_id = request()->post('launch_id', 0);
        if ($launch_id == 0) {
            $this->outMessage($title, [
                'data' => -9003
            ]);
        }
        $bargain = new BargainService ();
        // 发起的活动信息
        $res = $bargain->addBargainPartake($launch_id);
        $data = [
            'data' => $res
        ];
        return $this->outMessage($title, $data);
    }

    /**
     *
     * 得到当前时间戳的毫秒数
     *
     *
     *
     * @return number
     *
     */
    public function getCurrentTime()

    {
        $time = time();

        $time = $time * 1000;

        return $time;
    }

    /**
     * 我的砍价
     */
    public function myBargain()
    {
        $title = '我的砍价';
        if (empty($this->uid)) {
            return $this->outMessage($title, '', -9999, '无法获取会员登录信息');
        }

        $page_index = request()->post("page", 1);
        $condition = array();
        $condition["uid"] = $this->uid;

        // 还要考虑状态逻辑
        $bargain = new BargainService();
        $list = $bargain->getBargainLaunchList($page_index, PAGESIZE, $condition, 'start_time desc');
        $data = [
            'list' => $list,
            'current_time' => $this->getCurrentTime(),
        ];
        return $this->outMessage($title, $data);

    }
}