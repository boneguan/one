<?php
/**
 * Distribution.php
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
namespace app\wap\controller;

use data\model\UserModel as UserModel;
use data\service\NfxPartner;
use data\service\NfxPromoter;
use data\service\NfxRegionAgent;
use data\service\NfxShopConfig;
use data\service\NfxUser as NfxUserService;
use data\service\Member as MemberService;
use data\service\Platform;
use data\service\Shop as ShopService;
use data\service\Address;
use think;
use data\service\Config;

/**
 * 分销
 *
 * @author Administrator
 *        
 */
class Distribution extends BaseController
{
    public $nfx_shop_config;
    public function __construct()
    {
        parent::__construct();
        
        $this->checkLogin();
        // 店铺配置
        $nfx_shop_config = new NfxShopConfig();
        $this->nfx_shop_config = $nfx_shop_config->getShopConfigDetail($this->shop_id);
        $this->assign('shop_config', $this->nfx_shop_config);
    }
    
    /**
     * 检测用户
     */
    private function checkLogin()
    {
        $uid = $this->uid;
        if (empty($uid)) {
            $redirect = __URL(__URL__ . "/wap/login");
            $this->redirect($redirect); // 用户未登录
        }
        $is_member = $this->user->getSessionUserIsMember();
        if (empty($is_member)) {
            $redirect = __URL(__URL__ . "/wap/login");
            $this->redirect($redirect); // 用户未登录
        }
    }
    
    /**
     * 推广中心
     */
    public function distributionCenter()
    {
        $member = new MemberService();
        $nfx_promoter = new NfxPromoter();
        $platform = new Platform();
        $member_info = $member->getMemberDetail($this->shop_id);
        // 头像
        if (! empty($member_info['user_info']['user_headimg'])) {
            $member_img = $member_info['user_info']['user_headimg'];
        } else {
            $member_img = '0';
        }
        
        // 会员是否为推广员信息
        $promoter_info = $nfx_promoter->getUserPromoter($this->uid, $this->shop_id);
        // 防止直接访问店铺中心，发生意想不到的错误
        if (empty($promoter_info)) {
            $this->redirect("index/index");
        }
        
        $promoter_detail = $nfx_promoter->getPromoterDetail($promoter_info['promoter_id']);
        
        $store_menu = [
            [
                "src" => "store_my_team.png",
                "title" => "我的团队",
                "value" => $promoter_detail["team_count"] . "个",
                "href" => __URL("APP_MAIN/distribution/teamList?promoter_id=" . $promoter_info['promoter_id'])
            ]
        ];
        if($this->nfx_shop_config["is_partner_enable"] > 0){
            if ($promoter_detail["shop_user_info"]["is_partner"] != 0) {
                array_push($store_menu, [
                    "src" => "store_shareholders.png",
                    "title" => "股东",
                    "value" => "股东资料",
                    "href" => __URL('APP_MAIN/distribution/homePartner?shop_id=' . $this->shop_id)
                ]);
            } else {
                array_push($store_menu, [
                    "src" => "store_shareholders.png",
                    "title" => "申请股东",
                    "value" => "申请股东",
                    "href" => __URL('APP_MAIN/distribution/applypartner?shop_id=' . $this->shop_id)
                ]);
            }
        }
        if($this->nfx_shop_config["is_regional_agent"] > 0){
            if ($promoter_detail["shop_user_info"]["region_center_id"] != 0) {
                array_push($store_menu, [
                    "src" => "store_region_agent.png",
                    "title" => "区域代理",
                    "value" => "区域代理资料",
                    "href" => __URL('APP_MAIN/distribution/homeRegionAgent?shop_id=' . $this->shop_id)
                ]);
            } else {
                array_push($store_menu, [
                    "src" => "store_region_agent.png",
                    "title" => "申请区域代理",
                    "value" => "申请区域代理",
                    "href" => __URL('APP_MAIN/distribution/applyregionalagent?shop_id=' . $this->shop_id)
                ]);
            }
        }
        
        array_push($store_menu, [
            "src" => "store_promote_qrcode.png",
            "title" => "推广二维码",
            "value" => "更好的推广自己",
            "href" => __URL('APP_MAIN/member/getWchatQrcode?shop_id=' . $this->shop_id)
        ]);
        
//         array_push($store_menu, [
//             "src" => "store_qrcode.png",
//             "title" => "店铺二维码",
//             "value" => "更好的推广店铺",
//             "href" => __URL('APP_MAIN/member/getShopQrcode?shop_id=' . $this->shop_id)
//         ]);
        
        array_push($store_menu, [
            "src" => "store_my_commission.png",
            "title" => "我的佣金",
            "value" => $promoter_detail["commission"]["commission_cash"],
            "href" => __URL('APP_MAIN/distribution/usershopcommission?shop_id=' . $this->shop_id)
        ]);
        
        // 广告位
        $store_adv = $platform->getPlatformAdvPositionDetail(1107);
        $this->assign('member_info', $member_info);
        $this->assign('member_img', $member_img);
        $this->assign('nick_name', $member_info['user_info']['nick_name']);
        $this->assign('promoter_info', $promoter_detail);
        $this->assign("store_menu", $store_menu);
        $this->assign('store_adv', $store_adv['adv_list'][0]);
        return view($this->style . "/Distribution/distributionCenter");
    }

    /**
     * 我的团队 wyj
     *
     * @return \think\response\View
     */
    public function teamList()
    {
        $nfx_promoter = new NfxPromoter();
        $promoter_id = request()->get("promoter_id", "");
        if (empty($promoter_id)) {
            $this->redirect("Index/index");
        } else {
            $team_list = $nfx_promoter->getPromoterTeamListNew($promoter_id);
            if(empty($team_list['array_self'])){
                $this->redirect("Index/index");
            }
            if(isset($team_list['array_self'][0]['uid']) && $team_list['array_self'][0]['uid'] != $this->uid){
                $this->error("您没有权限进行此操作");
            }
            $this->assign("team_list", $team_list);
        }
        return view($this->style . "/Distribution/teamList");
    }

    /**
     * 区域代理
     *
     * @return \think\response\View
     */
    public function applyRegionalAgent()
    {
        $nfx_region_agent = new NfxRegionAgent();
        if (request()->isAjax()) {
            $shop_id = request()->post("shop_id", "");
            $agent_type = request()->post("agent_type", "");
            $real_name = request()->post("real_name", "");
            $mobile = request()->post("mobile", "");
            $address = request()->post("address", "");
            $retval = $nfx_region_agent->PromoterRegionAgentApplay($shop_id, $this->uid, $agent_type, $real_name, $mobile, $address);
            return AjaxReturn($retval);
        } else {
            
            $region_config = $nfx_region_agent->getShopRegionAgentConfig($this->shop_id);
            // var_dump($region_config);
            if (empty($region_config)) {
                $this->error("当前店铺未设置区域代理");
            }
            
            $region_agent_info = $nfx_region_agent->getPromoterRegionAgentValidDetail($this->shop_id, $this->uid);
            $agent_type = empty($region_agent_info) ? '-1' : $region_agent_info['is_audit'];
            $this->assign('agent_type', $agent_type);
            $shop = new ShopService();
            $shop_user_account = $shop->getShopUserConsume($this->shop_id, $this->uid);
            $this->assign("shop_sale_money", $shop_user_account);
            $this->assign("region_config", $region_config);
            return view($this->style . "/Distribution/applyRegionalAgent");
        }
    }

    /**
     * 区域代理首页
     */
    public function homeRegionAgent()
    {
        $shop_config = new NfxRegionAgent();
        $shop_info = $shop_config->getShopRegionAgentConfig($this->shop_id);
        // $this->assign("shop_config",$shop_info);
        $nfx_region_agent = new NfxRegionAgent();
        
        $region_agent_info = $nfx_region_agent->getPromoterRegionAgentValidDetail($this->shop_id, $this->uid);
        $address = new Address();
        $address_info = $address->getProvinceName($region_agent_info['agent_provinceid']);
        $agent_name = '省代';
        if ($region_agent_info['agent_type'] > 1) {
            $address_info .= $address->getCityName($region_agent_info['agent_cityid']);
            $agent_name = '市代';
        }
        if ($region_agent_info['agent_type'] > 2) {
            $address_info .= $address->getDistrictName($region_agent_info['agent_districtid']);
            $agent_name = '区代';
        }
        $member = new MemberService(); // 会员信息
        $member_info = $member->getMemberInfo();
        $user = new UserModel();
        $nick_name = $user->getInfo([
            "uid" => $this->uid
        ], "nick_name");
        $nfx_user = new NfxUserService();
        $user_account = $nfx_user->getNfxUserAccount($this->uid, $this->shop_id); // 佣金
        if ($region_agent_info["agent_type"] == 1) {
            $rate = $shop_info["province_rate"];
        } elseif ($region_agent_info["agent_type"] == 2) {
            $rate = $shop_info["city_rate"];
        } else {
            $rate = $shop_info["district_rate"];
        }
        $region_agent = array(
            'nick_name' => $nick_name['nick_name'],
            'agent_name' => $agent_name,
            'address_info' => $address_info,
            'commission_region_agent' => $user_account['commission_region_agent'],
            'rate' => $rate
        );
        $this->assign('region_agent', $region_agent);
        
        return view($this->style . "/Distribution/homeRegionAgent");
    }

    /**
     * 推广员申请
     * 任鹏强
     */
    public function applyPromoter()
    {
        if (request()->isAjax()) {
            $promoter = new NfxPromoter();
            $uid = $this->uid;
            $shop_id = request()->post('shop_id', 0);
            $promoter_shop_name = request()->post('promoter_shop_name', '');
            $retval = $promoter->promoterApplay($uid, $shop_id, $promoter_shop_name);
            return AjaxReturn($retval);
        } else {
            $reapply = request()->get('reapply', '0');
            $nfx_shop_config = new NfxShopConfig();
            //获取店铺的分销配置
            $shop_nfx = $nfx_shop_config->getShopConfigDetail($this->instance_id);
            if($shop_nfx['is_distribution_enable'] == 0){
            	$this->error("当前店铺未开启分销");
            }
            	
            // 推广员信息表
            $nfx_promoter = new NfxPromoter();
            $promoter_info = $nfx_promoter->getUserPromoter($this->uid, $this->shop_id);
           
            // 获取店铺推广员等级
            $shop_id = $this->shop_id;
            $promoter_level = $nfx_promoter->getPromoterLevelAll($shop_id, "level_money asc");
            if (empty($promoter_level)) {
                $this->error("当前店铺未设置推广员");
            }
            
            // 获取用户在本店的消费
            $shop = new ShopService();
            $uid = $this->uid;
            $user_consume = $shop->getShopUserConsume($shop_id, $uid);
            $this->assign('reapply', $reapply);
            $this->assign('user_consume', $user_consume);
            $this->assign('promoter_level', $promoter_level);
            $this->assign('promoter_info', $promoter_info);
            return view($this->style . "/Distribution/applyPromoter");
        }
    }

    /**
     * 会员对于当前店铺的佣金情况
     */
    public function userShopCommission()
    {
        $nfx_user = new NfxUserService();
        $user_account = $nfx_user->getNfxUserAccount($this->uid, $this->shop_id);
        if (empty($user_account["commission"])) {
            $user_account["commission"] = 0.00;
        }
        if (empty($user_account["commission_locked"])) {
            $user_account["commission_locked"] = 0.00;
        }
        if (empty($user_account["commission_withdraw"])) {
            $user_account["commission_withdraw"] = 0.00;
        }
        $this->assign('user_account', $user_account);
        return view($this->style . "/Distribution/userShopCommission");
    }

    /**
     * 会员对于各个店铺佣金列表
     */
    public function userCommissionList()
    {
        $nfx_user = new NfxUserService();
        $user_account_list = $nfx_user->getUserAccountList($this->uid);
        $this->assign('user_account_list', $user_account_list);
        return view($this->style . "/Distribution/userCommissionList");
    }

    /**
     * 会员佣金记录（明细）
     */
    public function userAccountRecordsList()
    {
        if (request()->isAjax()) {
            $shop_id = request()->post('shop_id', '');
            $condition['nuar.shop_id'] = $shop_id;
            $condition['nuar.uid'] = $this->uid;
            $nfx_user = new NfxUserService();
            $account_records_list = $nfx_user->getNfxUserAccountRecordsList(1, 0, $condition, 'create_time desc');
            return $account_records_list;
        } else {
            // $this->assign('account_records_list',$account_records_list);
            return view($this->style . "/Distribution/userAccountRecordsList");
        }
    }

    /**
     * 具体项的佣金明细
     */
    public function userAccountRecordsDetail()
    {
        $condition['uid'] = $this->uid;
        $condition['shop_id'] = request()->get('shop_id', "");
        $type_id = request()->get('type_id', "");
        
        $nfx_user = new NfxUserService();
        $condition['account_type'] = $type_id;
        $account_records_detail = $nfx_user->getNfxUserAccountRecordsList(1, 0, $condition, 'create_time desc');
        
        if (! empty($account_records_detail)) {
            foreach ($account_records_detail as $k => $v) {
                $type_name = $v['type_name'];
            }
        } else {
            $account_type_id = $type_id;
            $account_records_type = $nfx_user->getUserAccountType($account_type_id);
            $type_name = $account_records_type['type_name'];
        }
        $this->assign('type_name', $type_name);
        $this->assign('account_records_detail', $account_records_detail);
        return view($this->style . "/Distribution/userAccountRecordsDetail");
    }

    /**
     * 分类佣金明细
     */
    public function typeUserAccountRecords()
    {
        $type_alis_id = request()->get('type_alis_id', '1');
        $condition['nuar.shop_id'] = $this->shop_id;
        $condition['nuar.uid'] = 157;
        $nfx_user = new NfxUserService();
        $account_records_list = $nfx_user->getNfxUserAccountRecordsList(1, 0, $condition, 'create_time desc');
        var_dump($account_records_list);
    }

    /**
     * 平台推广中心
     */
    public function extensionCenterList()
    {
        if ($this->shop_id > 0) {
            $member = new MemberService();
            $member_info = $member->getMemberDetail($this->shop_id);
            $this->assign('member_info', $member_info);
            
            if (! empty($member_info['user_info']['user_headimg'])) {
                $member_img = $member_info['user_info']['user_headimg'];
            } elseif (! empty($member_info['user_info']['qq_openid'])) {
                $member_img = $member_info['user_info']['qq_info_array']['figureurl_qq_1'];
            } elseif (! empty($member_info['user_info']['wx_openid'])) {
                $member_img = '0';
            } else {
                $member_img = '0';
            }
            $this->assign('member_img', $member_img);
            
            // 会员是否为推广员信息
            $nfx_promoter = new NfxPromoter();
            $promoter_info = $nfx_promoter->getUserPromoter($this->uid, $this->shop_id);
            
            // 防止直接访问店铺中心，发生意想不到的错误
            if (empty($promoter_info)) {
                $this->redirect("index/index");
            }
            
            $promoter_detail = $nfx_promoter->getPromoterDetail($promoter_info['promoter_id']);
            print_r(json_encode($promoter_detail));
            return;
            $this->assign('promoter_info', $promoter_detail);
            
            $store_menu = [
                [
                    "src" => "store_my_team.png",
                    "title" => "我的团队",
                    "value" => $promoter_detail["team_count"] . "个",
                    "href" => "APP_MAIN/distribution/teamList?promoter_id=" . $promoter_info['promoter_id']
                ]
            ];
            
            if ($promoter_detail["shop_user_info"]["is_partner"] != 0) {
                array_push($store_menu, [
                    "src" => "store_shareholders.png",
                    "title" => "股东",
                    "value" => "股东资料",
                    "href" => 'APP_MAIN/distribution/homePartner?shop_id=' . $this->shop_id
                ]);
            } else {
                array_push($store_menu, [
                    "src" => "store_shareholders.png",
                    "title" => "申请股东",
                    "value" => "申请股东",
                    "href" => 'APP_MAIN/distribution/applypartner?shop_id=' . $this->shop_id
                ]);
            }
            
            if ($promoter_detail["shop_user_info"]["region_center_id"] != 0) {
                array_push($store_menu, [
                    "src" => "store_region_agent.png",
                    "title" => "区域代理",
                    "value" => "区域代理资料",
                    "href" => 'APP_MAIN/distribution/homeRegionAgent?shop_id=' . $this->shop_id
                ]);
            } else {
                array_push($store_menu, [
                    "src" => "store_region_agent.png",
                    "title" => "申请区域代理",
                    "value" => "申请区域代理",
                    "href" => 'APP_MAIN/distribution/applyregionalagent?shop_id=' . $this->shop_id
                ]);
            }
            
            array_push($store_menu, [
                "src" => "store_promote_qrcode.png",
                "title" => "推广二维码",
                "value" => "更好的推广自己",
                "href" => 'APP_MAIN/member/getWchatQrcode?shop_id=' . $this->shop_id
            ]);
            
            array_push($store_menu, [
                "src" => "store_qrcode.png",
                "title" => "店铺二维码",
                "value" => "更好的推广店铺",
                "href" => 'APP_MAIN/member/getShopQrcode?shop_id=' . $this->shop_id
            ]);
            
            array_push($store_menu, [
                "src" => "store_my_commission.png",
                "title" => "我的佣金",
                "value" => $promoter_detail["commission"]["commission_cash"],
                "href" => 'APP_MAIN/distribution/usershopcommission?shop_id=' . $this->shop_id
            ]);
            
            $this->assign("store_menu", $store_menu);
            
            // 广告位
            $platform = new Platform();
            $store_adv = $platform->getPlatformAdvPositionDetail(1107);
            $this->assign('store_adv', $store_adv['adv_list'][0]);
            
            return view($this->style . "/Distribution/distributionCenter");
        } else {
            $nfx_promoter = new NfxPromoter();
            $promoter_shop_list = $nfx_promoter->getUserPromoterList($this->uid);
            $this->assign('promoter_shop_list', $promoter_shop_list);
            return view($this->style . "/Distribution/extensionCenterList");
        }
    }

    /**
     * 提现记录
     */
    public function ajaxUserCommissionWithdraw()
    {
        $nfx_user = new NfxUserService();
        $shop_id = request()->post('shop_id', '');
        $condition['shop_id'] = $shop_id;
        $condition['uid'] = $this->uid;
        $commission_withdraw_list = $nfx_user->getUserCommissionWithdraw(1, 0, $condition, 'ask_for_date desc');
        return $commission_withdraw_list;
    }

    /**
     * 股东申请
     */
    public function applyPartner()
    {
        $nfx_partner = new NfxPartner();
        if (request()->isAjax()) {
            $shop_id = request()->post('shop_id', '');
            $retval = $nfx_partner->partnerApplay($shop_id, $this->uid);
            return AjaxReturn($retval);
        }
        $shop = new ShopService();
        $shop_user_account = $shop->getShopUserConsume($this->shop_id, $this->uid);
        $partner_level_list = $nfx_partner->getPartnerLevelAll($this->instance_id);
        $shop_sale_money = 0;
        $is_meet = 0; // 是否满足申请股东最低消费金额
        $level_money_arr = array();
        foreach ($partner_level_list as $k => $v) {
            $level_money_arr[] = $v['level_money'];
        }
        if(!empty($level_money_arr)){
            $shop_sale_money = min($level_money_arr);
            $level_isexist = true;
        }else{
            $level_isexist = false;
        }
        if ($shop_user_account >= $shop_sale_money) {
            $is_meet = 1;
        }
        $this->assign("level_isexist", $level_isexist);
        $this->assign("is_meet", $is_meet);
        $this->assign("shop_user_account", $shop_user_account); // 用户消费金额
        $this->assign("shop_sale_money", $shop_sale_money); // 申请股东最低消费金额
        
        $partner_info = $nfx_partner->getPartnerValidDetail($this->shop_id, $this->uid);
        $agent_type = empty($partner_info) ? '2' : $partner_info['is_audit'];
        
        $this->assign('agent_type', $agent_type);
        return view($this->style . "/Distribution/applyPartner");
    }

    /**
     * 股东首页
     *
     * @return \think\response\View
     */
    public function homePartner()
    {
        $nfx_partner = new NfxPartner();
        $partner_info = $nfx_partner->getPartnerValidDetail($this->shop_id, $this->uid); // 股东信息
        $partner_level_info = $nfx_partner->getPartnerLevelDetail($partner_info['partner_level']); // 等级信息
        
        $nfx_user = new NfxUserService();
        $user_account = $nfx_user->getNfxUserAccount($this->uid, $this->shop_id); // 佣金
        
        $member = new MemberService(); // 会员信息
        $member_info = $member->getMemberInfo();
        $user = new UserModel();
        $nick_name = $user->getInfo([
            "uid" => $this->uid
        ], "nick_name");
        $partner = array(
            'nick_name' => $nick_name['nick_name'],
            'level_name' => $partner_level_info['level_name'],
            'commission_rate' => $partner_level_info['commission_rate'] . '%',
            'commission_partner' => $user_account['commission_partner'],
            'commission_partner_global' => $user_account['commission_partner_global']
        );
        $this->assign('partner', $partner);
        return view($this->style . "/Distribution/homePartner");
    }

    /**
     * 申请提现
     */
    public function toWithdraw()
    {
        $nfx_user = new NfxUserService();
        if (request()->isAjax()) {
            // 提现
            $uid = $this->uid;
            $withdraw_no = request()->post('withdraw_no', '');
            $bank_account_id = request()->post('bank_account_id', '');
            $cash = request()->post('cash', '');
            $shop_id = request()->post('shop_id', '');
            
            $retval = $nfx_user->addNfxCommissionWithdraw($shop_id, $withdraw_no, $uid, $bank_account_id, $cash);
            return AjaxReturn($retval);
        } else {
            // 选择的账户
            $member = new MemberService();
            $account_list = $member->getMemberBankAccount(1);
            $this->assign('account_list', $account_list);
            // 佣金统计情况
            $user_account = $nfx_user->getNfxUserAccount($this->uid, $this->shop_id);
            $this->assign('user_account', $user_account);
            $config_service = new Config();
            $withdraw_info = $config_service->getBalanceWithdrawConfig($this->shop_id);
            if($withdraw_info["is_use"] == 0 || $withdraw_info["value"]["withdraw_multiple"] <= 0){
                 $this->error("当前店铺未开启提现，请联系管理员！");
            }
            $this->assign('withdraw_info', $withdraw_info['value']);
            return view($this->style . "/Distribution/toWithdraw");
        }
    }
}