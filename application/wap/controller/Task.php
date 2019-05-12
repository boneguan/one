<?php
/**
 * Login.php
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
/**
 * 后台登录控制器
 */
namespace app\wap\controller;

use data\service\Config;
use data\service\Events;
use data\service\Upgrade;
use data\service\WebSite;
use think\Cache;
use think\Controller;
use data\service\Notice;
use data\service\Verification;
use think\Log;
\think\Loader::addNamespace('data', 'data/');

/**
 * 执行定时任务
 *
 * @author Administrator
 *        
 */
class Task extends Controller
{

    public function __construct()
    {
        parent::__construct();
    }

    function admin(){
    	$event = new Events();
    	
    	$event->ordersAutoEvaluate();
    }
    /**
     * 加载执行任务
     */
    public function load_task()
    {
        $redirect = __URL(__URL__ . "/wap/Task/event");
        http($redirect, $timeout = 1); 
        return 1;
    }
    
    /**
     * 自动执行任务
     */
    public function event(){
    
        $task_load = cache("task_load");
        $last_time = cache("last_load_time");
        if($last_time == false){
            $last_time = 0;
        }
        $time = time();
        //检测是否需要执行自动事件  1. 系统缓存没有  2. 长期未执行（两倍执行事件）
        if($task_load == false || $time-$last_time >120){
            ignore_user_abort();
            set_time_limit(0);
            cache("task_load", 1);
             
            do{
                $task_load = cache("task_load");
                if($task_load == false){
                    Log::write("清除缓存，可能进行了系统更新，跳出循环");
                    break;//跳出循环
                }
                $last_time = cache("last_load_time");
                if($last_time == false){
                    $last_time = 0;
                }
                $time = time();
                if(($time-$last_time) < 30)
                {
                    Log::write("跳出多余循环项，保证当前只存在一个循环");
                    break;//跳出循环
                }
                Log::write("检测循环");
                $event = new Events();
                $retval_mansong_operation = $event->mansongOperation();
                $retval_discount_operation = $event->discountOperation();
                $retval_auto_coupon_close = $event->autoCouponClose();
                $retval_auto_group_buy_close = $event->autoGroupBuyClose();
                $retval_auto_topic_close = $event->autoTopicClose();
                // 营销游戏变化活动状态
                $retval_auto_games_operation = $event->autoPromotionGamesOperation();
                $notice = new Notice();
                
                $notice->sendNoticeRecords();
                // 使用户的过期虚拟商品失效
                $verification = new Verification();
                $retval_virtual_goods_close = $verification->virtualGoodsClose();
        
                $is_open_pintuan = IS_SUPPORT_PINTUAN;
                if ($is_open_pintuan == 1) {
                    $retval_pintuan_close = $event->pintuanGroupClose(); // 拼团自动关闭
                }
        
                $is_support_bargain = IS_SUPPORT_BARGAIN;
                if($is_support_bargain == 1){
                    $retval_auto_bargain_opration = $event->bargainOperation();
                }
        
                $retval_auto_group_buy_close = $event->autoPresellOrder();
                $retval_order_close = $event->ordersClose();
                $retval_order_complete = $event->ordersComplete();

                $retval_order_autodeilvery = $event->autoDeilvery();
                
                // 订单自动评价
                $event->ordersAutoEvaluate();
                
                cache("last_load_time", time());
                sleep(60);
            }while(TRUE);
        }
    }
    /**
     * 当前用户是否授权
     */
    public function copyRightIsLoad()
    {
        $upgrade = new Upgrade();
        $is_load = $upgrade->isLoadCopyRight();
        $website = new WebSite();
        $web_site_info = $website->getWebSiteInfo();
        $result = array(
            "is_load" => $is_load
        );

        $bottom_info = array();
        if ($is_load == 0) {
            $config = new Config();
            $bottom_info = $config->getCopyrightConfig(0);
            $bottom_info["copyright_logo"] = $bottom_info["copyright_logo"];
        }
        if (! empty($web_site_info["web_icp"])) {
            $bottom_info['copyright_meta'] = $web_site_info["web_icp"];
        } else {
            $bottom_info['copyright_meta'] = '';
        }
        $bottom_info['web_gov_record'] = $web_site_info["web_gov_record"];
        $bottom_info['web_gov_record_url'] = $web_site_info["web_gov_record_url"];
        
        $result["bottom_info"] = $bottom_info;
        $result["default_logo"] = "/blue/img/logo.png";
        
        return $result;
    }
}