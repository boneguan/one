<?php
/**
 * NfxUser.php
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
use data\api\INfxUser;
use data\model\NfxShopMemberAssociationModel;
use data\model\NfxUserAccountRecordsModel;
use data\model\NfxUserAccountModel;
use data\model\NsMemberBankAccountModel;
use data\model\NfxUserAccountViewModel;
use data\model\NfxUserAccountRecordsViewModel;
use data\model\NfxUserCommissionWithdrawModel;
use Exception;
use think\Model;
use data\model\NfxCommissionDistributionModel;
use data\model\NfxPartnerModel;
use data\model\NfxPromoterModel;
use data\model\NfxCommissionPartnerGlobalModel;
use data\model\NfxCommissionPartnerModel;
use data\model\NfxCommissionRegionAgentModel;
use data\model\NfxPromoterRegionAgentModel;
use data\service\Shop;
use data\model\NfxPromoterLevelModel;
use data\model\NfxPartnerLevelModel;
use data\model\WeixinFansModel;
use data\model\UserModel as UserModel;
use data\model\NfxUserAccountTypeModel;
use data\model\NfxShopMemberViewModel;
use data\service\NsMemberAccountModel;
use data\service\Member\MemberAccount;
use data\service\Weixin;
use data\service\Config as WebConfig;
use think\Session;
use think\Log;


/**
 * 用户佣金服务层
 */
class NfxUser extends BaseService implements INfxUser
{

    /*
     * (non-PHPdoc)
     * @see \data\api\INfxUser::userAssociateShop()
     */
    public function userAssociateShop($uid, $shop_id, $session_id, $wx_openid = "")
    {
        //判断是否开启推广员申请，未开启注册就成为推广员
        $shop_config = new NfxShopConfig();
        $shop_config_info = $shop_config->getShopConfigDetail($this->instance_id);
        
        //获取用户名称
        $user_model = new UserModel();
        $user_nick_name = $user_model->getInfo(['uid' => $uid], 'nick_name');
       
        $promoter_model = new NfxPromoterModel();
        $promoter_count = $promoter_model -> getCount(["shop_id" => $shop_id, "uid" => $uid, "is_audit" => 1]);
        $tag_promoter_session = Session::get("tag_promoter");
        $tag_promoter = !empty($tag_promoter_session) ? $tag_promoter_session : 0;
        if($promoter_count > 0 && $tag_promoter == 0){
            return 1;
        }
        
        $nfx_user = new NfxShopMemberAssociationModel();
        $count = $nfx_user->getCount(['uid' => $uid,'shop_id' => $shop_id]);
        
        $source_uid = 0;
        $promoter_id = 0;
        $partner_id = 0;
        $weixin = new Weixin();
        $user_info = $weixin->getUserWeixinSubscribeData($uid, $shop_id);
        if(empty($user_info) && !empty($wx_openid)){
            $weixin -> getWeixinFansInfoByWxOpenid($wx_openid);
        }
        
        $source_info = array();
        if (!empty($user_info["source_uid"]) && $user_info["source_uid"] > 0) {
            $source_info = $this->getShopMemberAssociation($user_info["source_uid"], $shop_id);
        }
        if (count($source_info) > 0) {
            $source_uid = $user_info["source_uid"];
            $promoter_id = $source_info["promoter_id"];
            $partner_id = $source_info["partner_id"];
        } else {
            if ($session_id > 0) {
                $session_info = $this->getShopMemberAssociation($session_id, $shop_id);
                if (count($session_info) > 0) {
                    $source_uid = $session_id;
                    $promoter_id = $session_info["promoter_id"];
                    $partner_id = $session_info["partner_id"];
                }
            }
        }
        $nfx_user = new NfxShopMemberAssociationModel();
        $data = array(
            "shop_id" => $shop_id,
            "source_uid" => $source_uid,
            "promoter_id" => $promoter_id,
            "partner_id" => $partner_id
        );
            
        if ($count == 0) {
            $data["uid"] = $uid;
            $data["create_time"] = time();
            $shop_member_return = $nfx_user->save($data);
            
            if($shop_config_info['is_distribution_start']==0 && $shop_config_info['is_distribution_enable'] != 0){
                $promoter = new NfxPromoter();
                $shop_id = $this->instance_id;
                $promoter_shop_name = $user_nick_name['nick_name'];
                $result = $promoter->promoterApplay($uid, $shop_id, $promoter_shop_name);
            }
            return $nfx_user->id;
        } else{
            $data["modify_time"] = time();
            $shop_member_return = $nfx_user->save($data, ["uid" => $uid]);
//             if($shop_config_info['is_distribution_start']==0){
//                 $promoter = new NfxPromoter();
//                 $shop_id = $this->instance_id;
//                 $promoter_shop_name = $user_nick_name['nick_name'];
//                 $result = $promoter->promoterApplay($uid, $shop_id, $promoter_shop_name);
//             }
           //执行修改
           //修改之后设定
            $promoter_model = new NfxPromoterModel();
            $promoter_info = $promoter_model -> getInfo(["uid" => $uid, "is_audit" => [">=", 0]], "*");

            if(!empty($promoter_info)){
                $parent_parmoter_info = $promoter_model -> getInfo(["uid" => $source_uid, "is_audit" => 1], "*");
                if(!empty($parent_parmoter_info)){
                    $promoter_model ->save(["parent_promoter" => $parent_parmoter_info["promoter_id"]], ["promoter_id" => $promoter_info["promoter_id"]]);
                }
                $nfx_user -> save(['promoter_id' => $promoter_info["promoter_id"]], ["uid" => $uid]);
            }
            Session::set("tag_promoter", 0);
            return 1;
        }
           
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\INfxUser::getUserPromoter()
     */
    public function getUserPromoter($uid,$shop_id)
    {
        $nfx_user = new NfxShopMemberAssociationModel();
        $nfx_userinfo = $nfx_user->getInfo(['uid' => $uid, 'shop_id' => $shop_id], '*');
        if(!empty($nfx_userinfo))
        {
            if($nfx_userinfo['promoter_id'] != 0)
            {
                $promoter = new NfxPromoterModel();
                $promoter_info = $promoter->getInfo(['promoter_id' => $nfx_userinfo['promoter_id']], 'promoter_shop_name,uid');
                $user = new UserModel();
                $user_info = $user->getInfo(['uid' => $promoter_info['uid']], 'nick_name');
                return array(
                    'promoter_id' => $nfx_userinfo['promoter_id'],
                    'promoter_shop_name' => $promoter_info['promoter_shop_name'],
                    'promoter_uid' => $promoter_info['uid'],
                    'promoter_nick_name' => $user_info['nick_name']
                );
            }else{
                return '';
            }
        }else{
            return '';
        }
        
        
        // TODO Auto-generated method stub
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\INfxUser::modifyUserPromoter()
     */
    public function modifyUserPromoter($uid, $shop_id, $promoter_id)
    {
        // TODO Auto-generated method stub
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\INfxUser::getUserRole()
     */
    public function getUserRole($uid, $shop_id)
    {
        // TODO Auto-generated method stub
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\INfxUser::addNfxUserAccountRecords()
     */
    public function addNfxUserAccountRecords($uid, $shop_id, $money, $account_type, $type_alis_id, $is_display, $is_calculate, $text, $batchcode)
    {
        $user_account_records = new NfxUserAccountRecordsModel();
        $user_account_records->startTrans();
        try {
            $data = array(
                'uid' => $uid,
                'shop_id' => $shop_id,
                'money' => $money,
                'account_type' => $account_type,
                'type_alis_id' => $type_alis_id,
                'is_display' => $is_display,
                'is_calculate' => $is_calculate,
                'text' => $text,
                'batchcode' => $batchcode,
                'create_time' => time()
            );
            $user_account_records->save($data);
            $this->updateMemberAccount($uid, $shop_id, $account_type);
            $user_account_records->commit();
            return 1;
        } catch (\Exception $e) {
            $user_account_records->rollback();
            return $e->getMessage();
        }
        // TODO Auto-generated method stub
    }

    /**
     * 修正账户金额
     *
     * @param unknown $uid            
     * @param unknown $shop_id            
     * @param unknown $account_type            
     */
    private function updateMemberAccount($uid, $shop_id, $account_type)
    {
        $account_model = new NfxUserAccountModel();
        $count = $account_model->where([
            'uid' => $uid,
            'shop_id' => $shop_id
        ])->count();
        if ($count == 0) {
            // 新建账户
            $account_model = new NfxUserAccountModel();
            $data = array(
                'uid' => $uid,
                'shop_id' => $shop_id,
                'create_time' => time()
            );
            $account_model->save($data);
        }
        $account_records_model = new NfxUserAccountRecordsModel();
        $money = $account_records_model->where([
            'uid' => $uid,
            'shop_id' => $shop_id,
            'account_type' => $account_type,
            'is_calculate' => 1
        ])->sum('money');
        $data_money['modify_time'] = time();
        switch ($account_type) {
            case 1:
                $data_money['commission_promoter'] = $money;
                break;
            case 2:
                $data_money['commission_region_agent'] = $money;
                break;
            case 3:
                $data_money['commission_channelagent'] = $money;
                break;
            case 4:
                $data_money['commission_partner'] = $money;
                break;
            case 5:
                $data_money['commission_partner_global'] = $money;
                break;
            case 10:
                $commission_lock = $this->getMemberCommissionLocked($uid, $shop_id);
                $data_money['commission_locked'] = $commission_lock;
                $data_money['commission_withdraw'] = - ($money + $commission_lock);
                break;
            default:
                break;
        }
        $account_model = new NfxUserAccountModel();
        $account_model->save($data_money, [
            'uid' => $uid,
            'shop_id' => $shop_id
        ]);
        $this->correctUserCommission($uid, $shop_id);
    }

    /**
     * 查询用户冻结的金额
     *
     * @param unknown $uid            
     * @param unknown $shop_id            
     */
    private function getMemberCommissionLocked($uid, $shop_id)
    {
        $commission_withdraw_model = new NfxUserCommissionWithdrawModel();
        $money = $commission_withdraw_model->where([
            'uid' => $uid,
            'shop_id' => $shop_id,
            'status' => 0
        ])->sum('cash');
        return $money;
    }

    /**
     * 重新计算可提现佣金
     *
     * @param unknown $uid            
     * @param unknown $shop_id            
     * @return Ambigous <number, \think\false, boolean, string>
     */
    private function correctUserCommission($uid, $shop_id)
    {
        $account_model = new NfxUserAccountModel();
        $account_data = $account_model->getInfo([
            'uid' => $uid,
            'shop_id' => $shop_id
        ], '*');
        $commission_cash = $account_data['commission_promoter'] + $account_data['commission_partner'] + $account_data['commission_partner_global'] + $account_data['commission_region_agent'] + $account_data['commission_partner_team'] + $account_data['commission_promoter_team'] + $account_data['commission_channelagent'];
        $data = array(
            'commission' => $commission_cash,
            'commission_cash' => $commission_cash - $account_data['commission_withdraw'] - $account_data['commission_locked']
        );
        $retval = $account_model->save($data, [
            'uid' => $uid,
            'shop_id' => $shop_id
        ]);
        
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\INfxUser::getNfxUserAccount()
     */
    public function getNfxUserAccount($uid, $shop_id)
    {
        $account_model = new NfxUserAccountModel();
        $account_data = $account_model->getInfo([
            'uid' => $uid,
            'shop_id' => $shop_id
        ], '*');
        if (! empty($account_data)) {
            // 保留两位小数并且四舍五入
            foreach ($account_data as $key => $item) {
                if (strpos($key, 'commission') === 0) {
                    $account_data[$key] = sprintf("%.2f", $item);
                }
            }
        }
        return $account_data;
        // TODO Auto-generated method stub
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\INfxUser::getUserAccountList()
     */
    public function getUserAccountList($uid)
    {
        $account_view_model = new NfxUserAccountViewModel();
        $list = $account_view_model->getViewQuery([
            'nua.uid' => $uid
        ], 'nua.create_time desc');
        return $list;
    }

    /*
     * 手机瑞
     * (non-PHPdoc)
     * @see \data\api\INfxUser::getNfxUserAccountRecordsList()
     */
    public function getNfxUserAccountRecordsList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        // TODO Auto-generated method stub
        $account_records_model = new NfxUserAccountRecordsViewModel();
        $condition['is_display'] = 1;
        
        $acount_records_list = $account_records_model->getViewQuery($page_index, $page_size, $condition, $order);
        if (! empty($acount_records_list)) {
            // 保留两位小数并且四舍五入
            foreach ($acount_records_list as $key => $item) {
                $item['money'] = sprintf("%.2f", $item['money']);
            }
        }
        return $acount_records_list;
    }
    
    
    /*
     * pc后台
     * (non-PHPdoc)
     * @see \data\api\INfxUser::getNfxUserAccountRecordsList()
     */
    public function getPcNfxUserAccountRecordsList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        // TODO Auto-generated method stub
        $account_records_model = new NfxUserAccountRecordsViewModel();
        $condition['is_display'] = 1;
    
        $acount_records_list = $account_records_model->getViewList($page_index, $page_size, $condition, $order);
        
        if (! empty($acount_records_list)) {
            // 保留两位小数并且四舍五入
            foreach ($acount_records_list['data'] as $key => $item) {
    
                $acount_records_list['data'][$key]['money'] = sprintf("%.2f", $item['money']);
                
                /* 获取用户名 */
                $user = new UserModel();
                $userinfo = $user->getInfo(['uid' => $item['uid']]);
                /* 获取推广编号 */
                $promoter = new NfxPromoterModel();
                $prometerinfo = $promoter->getInfo(['uid' => $item['uid']]);
                
                $account_info = array();
                if($item["account_type"] == 1){
                    $nfx_commission_distribution = new NfxCommissionDistributionModel();
                    $account_info = $nfx_commission_distribution->getInfo(["id"=>$item["type_alis_id"], "shop_id"=>$item["shop_id"]]);                    
                }elseif($item["account_type"] == 4){
                    $nfx_commission_partner = new NfxCommissionPartnerModel();
                    $account_info = $nfx_commission_partner->getInfo(["id"=>$item["type_alis_id"], "shop_id"=>$item["shop_id"]]);
                }elseif($item["account_type"] == 5){
                    $nfx_commission_partner_global = new NfxCommissionPartnerGlobalModel();
                    $account_info = $nfx_commission_partner_global->getInfo(["id"=>$item["type_alis_id"],"uid"=>$item["uid"], "shop_id"=>$item["shop_id"]]);
                }elseif($item["account_type"] == 2){
                    $nfx_commission_region_agent = new NfxCommissionRegionAgentModel();
                    $account_info = $nfx_commission_region_agent->getInfo(["id"=>$item["type_alis_id"],"uid"=>$item["uid"], "shop_id"=>$item["shop_id"]]);
                }
                $acount_records_list['data'][$key]['account_info'] = $account_info;
                $acount_records_list['data'][$key]['userinfo'] = $userinfo;
                $acount_records_list['data'][$key]['prometerinfo'] = $prometerinfo;

            }
            
        }
        return $acount_records_list;
    }    
    
    

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\niushop\IMember::memberBankAccount()
     */
    public function getUserBankAccount($is_default=0)
    {
        $user_bank_account = new NsMemberBankAccountModel();
        $uid = $this->uid;
        $bank_account_list = '';
        if (! empty($uid)) {
            
            if(empty($is_default)){
                $bank_account_list = $user_bank_account->getQuery(['uid' => $uid], '*', '');
            }else{
                $bank_account_list = $user_bank_account->getQuery(['uid' => $uid,'is_default'=>1], '*', '');
            }
            
        }
        
        return $bank_account_list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\niushop\IMember::addMemberBankAccount()
     */
    public function addUserBankAccount($uid, $bank_type, $branch_bank_name, $realname, $account_number, $mobile)
    {
        $user_bank_account = new NsMemberBankAccountModel();
        $user_bank_account->startTrans();
        try {
            $data = array(
                'uid' => $uid,
                'bank_type' => $bank_type,
                'branch_bank_name' => $branch_bank_name,
                'realname' => $realname,
                'account_number' => $account_number,
                'mobile' => $mobile,
                'create_date' => time(),
                'modify_date' => time()
            );
            
            $user_bank_account->save($data);
            $account_id = $user_bank_account->id;
            $retval = $this->setUserBankAccountDefault($uid, $account_id);
            $user_bank_account->commit();
            return $account_id;
        } catch (Exception $e) {
            $user_bank_account->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\niushop\IMember::updateMemberBankAccount()
     */
    public function updateUserBankAccount($account_id, $branch_bank_name, $realname, $account_number, $mobile)
    {
        $user_bank_account = new NsMemberBankAccountModel();
        $user_bank_account->startTrans();
        try {
            
            $data = array(
                'branch_bank_name' => $branch_bank_name,
                'realname' => $realname,
                'account_number' => $account_number,
                'mobile' => $mobile,
                'modify_date' => time()
            );
            $user_bank_account->save($data, [
                'id' => $account_id
            ]);
            $retval = $this->setUserBankAccountDefault($this->uid, $account_id);
            $user_bank_account->commit();
            return $account_id;
        } catch (Exception $e) {
            $user_bank_account->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\niushop\IMember::delMemberBankAccount()
     */
    public function delUserBankAccount($account_id)
    {
        $user_bank_account = new NsMemberBankAccountModel();
        $uid = $this->uid;
        $retval = $user_bank_account->destroy([
            'uid' => $uid,
            'id' => $account_id
        ]);
        return $retval;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\niushop\IMember::setMemberBankAccountDefault()
     */
    public function setUserBankAccountDefault($uid, $account_id)
    {
        $user_bank_account = new NsMemberBankAccountModel();
        $user_bank_account->update([
            'is_default' => 0
        ], [
            'uid' => $uid,
            'is_default' => 1
        ]);
        $user_bank_account->update([
            'is_default' => 1
        ], [
            'uid' => $uid,
            'id' => $account_id
        ]);
        return $account_id;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\niushop\IMember::getMemberBankAccountDetail()
     */
    public function getUserBankAccountDetail($id)
    {
        $user_bank_account = new NsMemberBankAccountModel();
        $bank_account_info = $user_bank_account->getInfo([
            'id' => $id
        ], '*');
        return $bank_account_info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\INfxUser::getUserCommissionWithdraw()
     */
    public function getUserCommissionWithdraw($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $user_commission_withdraw = new NfxUserCommissionWithdrawModel();
        $list = $user_commission_withdraw->pageQuery($page_index, $page_size, $condition, $order, '*');
        if (! empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                $user = new UserModel();
                $userinfo = $user->getInfo([
                    'uid' => $v['uid']
                ]);
                $list['data'][$k]['real_name'] = $userinfo["nick_name"];
                $promoter = new NfxPromoterModel();
                $prometerinfo = $promoter->getInfo(['uid' => $v['uid']]);
                $list['data'][$k]['promoter_no'] = $prometerinfo['promoter_no'];
                $list['data'][$k]['promoter_shop_name'] = $prometerinfo['promoter_shop_name'];
            }
        }
        return $list;
    }
    /**
     * 通过店铺id 得到提现的流水号
     *
     * @param unknown $shop_id
     */
    public function createWidthdrawNo($shop_id)
    {
        $time_str = date('Ymd');
        $commission_withdraw = new NfxUserCommissionWithdrawModel();
        $commission_obj = $commission_withdraw->getFirstData([
            "shop_id" => $shop_id
        ], "id DESC");
        $num = 0;
        if (! empty($commission_obj)) {
            $withdraw_no_max = $commission_obj["withdraw_no"];
            if (empty($withdraw_no_max)) {
                $num = 1;
            } else {
                if (substr($time_str, 0, 8) == substr($withdraw_no_max, 0, 8)) {
                    $max_no = substr($withdraw_no_max, 8, 7);
                    $num = $max_no * 1 + 1;
                } else {
                    $num = 1;
                }
            }
        } else {
            $num = 1;
        }
        $withdraw_no = $time_str . sprintf("%07d", $num);
        return $withdraw_no;
    }
    /**
     * (non-PHPdoc)
     *
     * @see \data\api\INfxUser::addNfxCommissionWithdraw()
     */
    public function addNfxCommissionWithdraw($shop_id, $withdraw_no, $uid, $bank_account_id, $cash)
    {
        //得到本店的提现设置
        $config_service = new WebConfig();
        $withdraw_obj = $config_service->getBalanceWithdrawConfig($shop_id);
        $withdraw_info=$withdraw_obj["value"];
        if(empty($withdraw_info)){
            return 0;
        }
        if($withdraw_info["withdraw_multiple"] != 0){
            $mod = $cash%$withdraw_info["withdraw_multiple"];
            if($mod != 0){
                return 0;
            }
        }
        if($cash < $withdraw_info["withdraw_cash_min"]){
            return 0;
        }
        $user_account = new NfxUserAccountModel();
        $user_account_info = $user_account->getInfo(["shop_id"=>$shop_id, "uid"=>$uid],"*");
        if(empty($user_account)){
            return 0;
        }
        if($user_account_info["commission_cash"] < $cash ||  $cash <= 0){
            return 0;
        }
        $user_bank_account = new NsMemberBankAccountModel();
        $bank_account_info = $user_bank_account->getInfo([
            'id' => $bank_account_id
        ], '*');
        
        $brank_name = $bank_account_info['branch_bank_name'];
        // 提现方式如果不是银行卡，则显示账户类型名称
        if ($bank_account_info['account_type'] != 1) {
            $brank_name = $bank_account_info['account_type_name'];
        }
        
        $withdraw_no = $this->createWidthdrawNo($shop_id);
        $commission_withdraw = new NfxUserCommissionWithdrawModel();
        $data = array(
            'shop_id' => $shop_id,
            'withdraw_no' => $withdraw_no,
            'uid' => $uid,
            'bank_name' => $brank_name,
            'account_number' => $bank_account_info['account_number'],
            'realname' => $bank_account_info['realname'],
            'mobile' => $bank_account_info['mobile'],
            'cash' => $cash,
            'ask_for_date' => time(),
            'status' => 0,
            'modify_date' => time()
        );
        $commission_withdraw->save($data);
        $this->addNfxUserAccountRecords($uid, $shop_id, - $cash, 10, $commission_withdraw->id, 1, 1, "用户申请佣金提现", $withdraw_no);
        if ($commission_withdraw->id) {
            $params['id'] = $commission_withdraw->id;
            $params['type'] = 'commission';
            hook("memberWithdrawApplyCreateSuccess", $params);
        }
        return $commission_withdraw['id'];
    }

    /**
     * 发放这个订单的三级分销
     *
     * @param unknown $order_id            
     */
    public function updateCommissionDistributionIssue($order_id)
    {
        $commission_distribution_model = new NfxCommissionDistributionModel();
        $commission_distribution_model->startTrans();
        try {
            $condition["order_id"] = $order_id;
            $condition["is_issue"] = 0;
            $distribution_list = $commission_distribution_model->getQuery($condition, "*", "");
            if (! empty($distribution_list) && count($distribution_list) > 0) {
                foreach ($distribution_list as $k => $distribution_obj) {
                    $promote_model = new NfxPromoterModel();
                    $promote_Obj = $promote_model->get($distribution_obj["promoter_id"]);
                    if($distribution_obj["commission_money"]>0){
                        $this->addNfxUserAccountRecords($promote_Obj["uid"], $distribution_obj["shop_id"], $distribution_obj["commission_money"], 1, $distribution_obj["id"], 1, 1, "订单交易完成，三级分销佣金进行结算。", $distribution_obj["serial_no"]);
                    }
                    $commission_distribution_model->update([
                        'is_issue' => 1
                    ], [
                        'id' => $distribution_obj["id"]
                    ]);
                }
            }
            $commission_distribution_model->commit();
            return 1;
        } catch (Exception $e) {
            $commission_distribution_model->rollback();
            return $e->getCode();
        }
    }

    /**
     * 发放订单的全球分红
     *
     * @param unknown $order_id            
     */
    public function updateCommissionPartnerIssue($order_id)
    {
        $commission_partner_model = new NfxCommissionPartnerModel();
        $commission_partner_model->startTrans();
        try {
            $condition["order_id"] = $order_id;
            $condition["is_issue"] = 0;
            $global_list = $commission_partner_model->getQuery($condition, "*", "");
            if (! empty($global_list) && count($global_list) > 0) {
                foreach ($global_list as $k => $global_obj) {
                    $partner_model = new NfxPartnerModel();
                    $partner_obj = $partner_model->get($global_obj["partner_id"]);
                    $uid = $partner_obj["uid"];
                    if($global_obj["commission_money"]>0){
                        $this->addNfxUserAccountRecords($uid, $global_obj["shop_id"], $global_obj["commission_money"], 4, $global_obj["id"], 1, 1, "订单交易完成，股东分红佣金进行结算。", $global_obj["serial_no"]);
                    }
                    $commission_partner_model->update([
                        'is_issue' => 1
                    ], [
                        'id' => $global_obj["id"]
                    ]);
                }
            }
            $commission_partner_model->commit();
            return 1;
        } catch (Exception $e) {
            $commission_partner_model->rollback();
            return $e->getCode();
        }
    }

    /**
     * 发放订单的区域代理
     *
     * @param unknown $order_id            
     */
    public function updateCommissionRegionAgentIssue($order_id)
    {
        $commission_region_model = new NfxCommissionRegionAgentModel();
        $commission_region_model->startTrans();
        try {
            $condition["order_id"] = $order_id;
            $condition["is_issue"] = 0;
            $region_list = $commission_region_model->getQuery($condition, "*", "");
            if (! empty($region_list) && count($region_list) > 0) {
                foreach ($region_list as $k => $region_obj) {
                    if($region_obj["commission"]>0){
                        $this->addNfxUserAccountRecords($region_obj["uid"], $region_obj["shop_id"], $region_obj["commission"], 2, $region_obj["id"], 1, 1, "订单交易完成，区域代理佣金进行结算。", $region_obj["serial_no"]);
                    }
                    $commission_region_model->update([
                        'is_issue' => 1
                    ], [
                        'id' => $region_obj["id"]
                    ]);
                }
            }
            $commission_region_model->commit();
            return 1;
        } catch (Exception $e) {
            $commission_region_model->rollback();
            return $e->getCode();
        }
    }

    /**
     * 更新 推广员的等级
     *
     * @param unknown $uid            
     */
    public function updatePromoterLevel($uid, $shop_id)
    {
        $shop_service = new Shop();
        $Consume_money = $shop_service->getShopUserConsume($shop_id, $uid);
        $promoter_model = new NfxPromoterModel();
        $promoter_level_model = new NfxPromoterLevelModel();
        $promoter_info = $promoter_model->getInfo(["uid"=>$uid, "shop_id"=>$shop_id, "is_audit"=>1],"promoter_level, promoter_id");
        if(!empty($promoter_info)){
            $level_info = $promoter_level_model->getInfo(["level_id"=>$promoter_info["promoter_level"]], "*");
            $condition = array(
                "shop_id" => $shop_id
            );
            if(!empty($level_info)){
                if($level_info["level_money"] < $Consume_money){
                    $condition["level_money"] = ["<=", $Consume_money];
                }else{
                    $condition["level_money"] = ["<=", $level_info["level_money"]];
                }
                $level_list = $promoter_level_model->getFirstData($condition, "level_money desc");
                if(!empty($level_list)){
                    $level_id = $level_list["level_id"];
                    $promoter_model->save([
                        'promoter_level' => $level_id
                    ], [
                        'uid' => $uid,
                        "shop_id" => $shop_id,
                        "is_audit" => 1
                    ]);
                }
            }
        }
//         $condition = array(
//             "level_money" => ["<=", $Consume_money],
//             "shop_id" => $shop_id
//         );
//         $level_list = $promoter_level_model->getFirstData($condition, "level_money desc");
//         $level_list = $promoter_level_model->where("level_money<=$Consume_money and shop_id=$shop_id")
//             ->order("level_money desc")
//             ->limit(0, 1)
//             ->select();
//         if (! empty($level_list) && count($level_list) > 0) {
//             $level_id = $level_list[0]["level_id"];
//             $promoter_model = new NfxPromoterModel();
//             $promoter_info = $promoter_model->getInfo(["uid"=>$uid, "shop_id"=>$shop_id, "is_audit"=>1],"promoter_level, promoter_id");
//             if(!empty($promoter_info)){
//                 $level_info = $promoter_level_model->getInfo(["level_id"=>$promoter_info["promoter_level"]], "*");
//                 if(!empty($level_info)){
//                     if($level_info["level_money"] < $level_list["level_money"]){
                        
//                     }
//                 }
//             }
//             $promoter_model->update([
//                 'promoter_level' => $level_id
//             ], [
//                 'uid' => $uid,
//                 "shop_id" => $shop_id,
//                 "is_audit" => 1,
//                 "is_lock" => 0
//             ]);
//         }
    }

    /**
     * 更新股东的等级
     *
     * @param unknown $uid            
     */
    public function updatePartnerLevel($uid, $shop_id)
    {
        $shop_service = new Shop();
        $Consume_money = $shop_service->getShopUserConsume($shop_id, $uid);
        $partner_level_model = new NfxPartnerLevelModel();
        $partner_model = new NfxPartnerModel();
        $partner_info = $partner_model->getInfo(["uid"=>$uid, "shop_id"=>$shop_id, "is_audit"=>1],"partner_level, partner_id");
        if(!empty($partner_info)){
            $level_info = $partner_level_model->getInfo(["level_id"=>$partner_info["partner_level"]], "*");
            $condition = array(
                "shop_id" => $shop_id
            );
            if(!empty($level_info)){
                if($level_info["level_money"] < $Consume_money){
                    $condition["level_money"] = ["<", $Consume_money];
                }else{
                    $condition["level_money"] = ["<", $level_info["level_money"]];
                }
                $level_list = $partner_level_model->getFirstData($condition, "level_money desc");
                if(!empty($level_list)){
                    $level_id = $level_list["level_id"];
                    $partner_model->save([
                        'partner_level' => $level_id
                    ], [
                        'uid' => $uid,
                        "shop_id" => $shop_id,
                        "is_audit" => 1
                    ]);
                }
            }
        }
//         $shop_service = new Shop();
//         $Consume_money = $shop_service->getShopUserConsume($shop_id, $uid);
//         $partner_level_model = new NfxPartnerLevelModel();
//         $level_list = $partner_level_model->where(" level_money<= $Consume_money and shop_id=$shop_id ")
//             ->order("level_money desc")
//             ->limit(0, 1)
//             ->select();
//         if (! empty($level_list) && count($level_list) > 0) {
//             $level_id = $level_list[0]["level_id"];
//             $partner_model = new NfxPartnerModel();
//             $partner_model->update([
//                 'partner_level' => $level_id
//             ], [
//                 'uid' => $uid,
//                 "shop_id" => $shop_id,
//                 "is_audit" => 1,
//                 "is_lock" => 0
//             ]);
//         }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\INfxUser::getWeixinFansDetail()
     */
    public function getWeixinFansDetail($uid, $shop_id)
    {
        $weixin_fans = new WeixinFansModel();
        $weixin_fans_info = $weixin_fans->getInfo(array(
            "instance_id" => $shop_id,
            "uid" => $uid
        ), "*");
        return $weixin_fans_info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\INfxUser::getShopMemberAssociation()
     */
    public function getShopMemberAssociation($uid, $shop_id)
    {
        $nfx_user = new NfxShopMemberAssociationModel();
        $data = $nfx_user->getInfo(array(
            "shop_id" => $shop_id,
            "uid" => $uid
        ));
        return $data;
    }

    /**
     * 获取店铺用户佣金列表(non-PHPdoc)
     *
     * @see \data\api\INfxUser::getShopUserAccountList()
     */
    public function getShopUserAccountList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $user_account = new NfxUserAccountModel();
        $list = $user_account->pageQuery($page_index, $page_size, $condition, $order, '*');
        if (! empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                $user = new UserModel();
                $userinfo = $user->getInfo([
                    'uid' => $v['uid']
                ]);
                $list['data'][$k]['real_name'] = $userinfo["nick_name"];
                $list['data'][$k]['user_tel'] = $userinfo["user_tel"];
                $promoter = new NfxPromoterModel();
                $promoter_count = $promoter->where(array(
                    "shop_id" => $v["shop_id"],
                    "uid" => $v["uid"],
                    "is_audit" => 1
                ))->count();
                if ($promoter_count > 0) {
                    $list['data'][$k]['is_promoter'] = 1;
                } else {
                    $list['data'][$k]['is_promoter'] = 0;
                }
                $partner = new NfxPartnerModel();
                $partner_count = $partner->where(array(
                    "shop_id" => $v["shop_id"],
                    "uid" => $v["uid"],
                    "is_audit" => 1
                ))->count();
                if ($partner_count > 0) {
                    $list['data'][$k]['is_partner'] = 1;
                } else {
                    $list['data'][$k]['is_partner'] = 0;
                }
                $promoter_region_agent = new NfxPromoterRegionAgentModel();
                $promoter_region_agent_count = $promoter_region_agent->where(array(
                    "shop_id" => $v["shop_id"],
                    "uid" => $v["uid"],
                    "is_audit" => 1
                ))->count();
                if ($promoter_region_agent_count > 0) {
                    $list['data'][$k]['is_region_agent'] = 1;
                } else {
                    $list['data'][$k]['is_region_agent'] = 0;
                }
            }
        }
        return $list;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\INfxUser::UserCommissionWithdrawAudit()
     */
    public function UserCommissionWithdrawAudit($shop_id, $id, $status, $transfer_type, $transfer_name, $transfer_money, $transfer_remark, $transfer_no, $transfer_account_no, $type_id)
    {
        // TODO Auto-generated method stub
        $user_commission_withdraw = new NfxUserCommissionWithdrawModel();
        $user_commission_withdraw_info = $user_commission_withdraw->getInfo(['id' => $id], '*');
        
        if($user_commission_withdraw_info["transfer_status"] != 1 && $user_commission_withdraw_info["status"] != 1 && $status != - 1){
            if ($transfer_type == 1) {
                /**
                 * 线下转账
                 */
                $transfer_status = 1;
                $transfer_result = "会员提现, 线下转账成功";
            } else {
                /**
                 * 线上转账
                 */
                if ($type_id == 1) {
                    // 线上微信转账
                    $user = new UserModel();
                    $userinfo = $user->getInfo([
                        'uid' => $user_commission_withdraw_info['uid']
                    ]);
                    $openid = $userinfo["wx_openid"];
                    $unify = new UnifyPay();
                    $wechat_retval = $unify->wechatTransfers($openid, $user_commission_withdraw_info["withdraw_no"], $transfer_money * 100, $user_commission_withdraw_info['realname'], $transfer_remark);
                    $transfer_result = $wechat_retval['msg'];
                    if ($wechat_retval["is_success"] > 0) {
                        $transfer_status = 1;
                    } else {
                        $transfer_status = - 1;
                    }
                } else {
                    // 线上支付包转账
                    $unify = new UnifyPay();
                    $alipay_retval = $unify->aliayTransfers($user_commission_withdraw_info["withdraw_no"], $user_commission_withdraw_info["account_number"], $transfer_money);
                    if ($alipay_retval["result_code"] == "SUCCESS") {
                        $transfer_status = 1;
                        $transfer_result = "支付宝线上转账成功!";
                        $transfer_no = $alipay_retval["order_id"];
                    } elseif ($alipay_retval["result_code"] == "FAIL") {
                        $transfer_status = - 1;
                        $transfer_result = $alipay_retval["error_msg"] . "," . $alipay_retval["error_code"];
                    }
                }
            }
        }
        if ($transfer_status != - 1) {
            $user_commission_withdraw = new NfxUserCommissionWithdrawModel();
            $retval = $user_commission_withdraw->where(array(
                "shop_id" => $shop_id,
                "id" => $id
            ))->update(array(
                "status" => $status
            ));
            $user_commission_withdraw = new NfxUserCommissionWithdrawModel();
            $user_commission_withdraw_info = $user_commission_withdraw->getInfo(['id' => $id], '*');
            
            if ($retval > 0 && $status == - 1) {
                $this->addNfxUserAccountRecords($user_commission_withdraw_info["uid"], $shop_id, $user_commission_withdraw_info["cash"], 10, $user_commission_withdraw_info["id"], 1, 1, "退回" . $user_commission_withdraw_info["realname"] . "佣金" . $user_commission_withdraw_info["cash"], $user_commission_withdraw_info["withdraw_no"]);
            } else {
                $this->updateMemberAccount($user_commission_withdraw_info["uid"], $shop_id, 10);
            }
            return array(
                "code" => $retval,
                "message" => $transfer_result
            );
        } else {
            return array(
                "code" => $transfer_status,
                "message" => $transfer_result
            );
        }
        
    }

    /**
     * (non-PHPdoc)
     * @see \data\api\INfxUser::userCommissionWithdrawRefuse()
     */
    public function userCommissionWithdrawRefuse($shop_id, $id, $status, $remark){
        
        $user_commission_withdraw = new NfxUserCommissionWithdrawModel();
        $retval = $user_commission_withdraw->where(array(
            "shop_id" => $shop_id,
            "id" => $id
        ))->update(array(
            "status" => $status,
            "memo" => $remark
        ));
        $user_commission_withdraw = new NfxUserCommissionWithdrawModel();
        $user_commission_withdraw_info = $user_commission_withdraw->getInfo([
            'id' => $id
        ], '*');
        if ($retval > 0 && $status == - 1) {
            $this->addNfxUserAccountRecords($user_commission_withdraw_info["uid"], $shop_id, $user_commission_withdraw_info["cash"], 10, $user_commission_withdraw_info["id"], 1, 1, "退回" . $user_commission_withdraw_info["realname"] . "佣金" . $user_commission_withdraw_info["cash"], $user_commission_withdraw_info["withdraw_no"]);
        } else {
            $this->updateMemberAccount($user_commission_withdraw_info["uid"], $shop_id, 10);
        }
        return $retval;
    }
    
    /**
     * (non-PHPdoc)
     * @see \data\api\INfxUser::getMemberWithdrawalsDetails()
     */
    public function getMemberWithdrawalsDetails($id){
     
        $user_commission_withdraw = new NfxUserCommissionWithdrawModel();
        $retval = $user_commission_withdraw->getInfo([
            'id' => $id
        ], '*');
        if (! empty($retval)) {
            $user = new UserModel();
            $userinfo = $user->getInfo([
                'uid' => $retval['uid']
            ]);
            $retval['real_name'] = $userinfo["nick_name"];
        }
        return $retval;
    }
    /**
     * (non-PHPdoc)
     *
     * @see \data\api\INfxUser::getUserAccountType()
     */
    public function getUserAccountType($account_type_id)
    {
        $account_type = new NfxUserAccountTypeModel();
        $account_type_info = $account_type->getInfo([
            'type_id' => $account_type_id
        ], '*');
        return $account_type_info;
    }
    
    /**
     * (non-PHPdoc)
     * @see \data\api\INfxUser::getUserAccountTypeList()
     */
    public function getUserAccountTypeList($condition = '1=1'){
        
        $account_type = new NfxUserAccountTypeModel();
        $account_type_list = $account_type->getQuery($condition, '*', '');
        return $account_type_list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\INfxUser::getShopMemberList()
     */
    public function getShopMemberList($shop_id, $page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $view_model = new NfxShopMemberViewModel();
        $list = $view_model->getViewList($page_index, $page_size, $condition, $order);
        if (! empty($list)) {
            foreach ($list['data'] as $k => $v) {
              $member_account = new MemberAccount();
              $list['data'][$k]['point'] =  $member_account->getMemberPoint($v['uid'], $shop_id);
              $list['data'][$k]['balance'] = $member_account->getMemberBalance($v['uid']);
              $list['data'][$k]['coin'] = $member_account->getMemberCoin($v['uid']);
            }
        }
        return $list;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\INfxUser::getShopUserAccountRecord()
     */
    public function getShopUserAccountRecord($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        // TODO Auto-generated method stub
        $shop_user_account_record = new NfxUserAccountRecordsModel();
        $list = $shop_user_account_record->pageQuery($page_index, $page_size, $condition, $order, '*');
        // var_dump($page_index);
        
        if (! empty($list)) {
            foreach ($list['data'] as $k => $v) {
                $user = new UserModel();
                $userinfo = $user->getInfo([
                    'uid' => $v['uid']
                ]);
                $list['data'][$k]['user_name'] = $userinfo["user_name"];
                $user_account_type = new NfxUserAccountTypeModel();
                $account_type_info = $user_account_type->getInfo([
                    'type_id' => $v["account_type"]
                ], "type_name");
                $list['data'][$k]["type_name"] = $account_type_info["type_name"];
            }
        }
        return $list;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\INfxUser::getShopCommissionCount()
     */
    public function getShopCommissionCount($shop_id, $start_date, $end_date)
    {
        // TODO Auto-generated method stub
        $condition["shop_id"] = $shop_id;
        $user_account_type = new NfxUserAccountTypeModel();
        if ($start_date != "") {
            $condition["create_time"][] = [
                ">",
                $start_date
            ];
        }
        if ($end_date != "") {
            $condition["create_time"][] = [
                "<",
                $end_date
            ];
        }
        $user_account_type_all = $user_account_type->all();
        $shop_user_account_record = new NfxUserAccountRecordsModel();
        $user_account_record_all = $shop_user_account_record->all($condition);
        foreach ($user_account_type_all as $k => $v) {
            $money = 0;
            foreach ($user_account_record_all as $t => $n) {
                if ($n["account_type"] == $v["type_id"]) {
                    $money = $money + $n["money"];
                }
            }
            $user_account_type_all[$k]["money"] = $money;
        }
        return $user_account_type_all;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\INfxUser::getShopWithdrawCount()
     */
    public function getShopWithdrawCount($condition)
    {
        // TODO Auto-generated method stub
        $user_commission_withdraw = new NfxUserCommissionWithdrawModel();
        // 店铺用户已提现
        $user_withdraw_money = $user_commission_withdraw->where($condition)
            ->where(array(
            "status" => 1
        ))
            ->sum("cash");
        // 店铺提现审核中
        $user_withdraw_audit_money = $user_commission_withdraw->where($condition)
            ->where(array(
            "status" => 0
        ))
            ->sum("cash");
        return [
            "user_withdraw_money" => $user_withdraw_money,
            "user_withdraw_audit_money" => $user_withdraw_audit_money
        ];
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\INfxUser::getShopUserCommissionCount()
     */
    public function getShopUserCommissionCount($shop_id)
    {
        // TODO Auto-generated method stub
        // 待结算佣金
        $commission_distribution = new NfxCommissionDistributionModel();
        $commission_distribution_audit = $commission_distribution->where("is_issue = 0 and shop_id =" . $shop_id)->sum("commission_money");
        $commission_partner = new NfxCommissionPartnerModel();
        $commission_partner_audit = $commission_partner->where("is_issue = 0 and shop_id =" . $shop_id)->sum("commission_money");
        $commission_region_agent = new NfxCommissionRegionAgentModel();
        $commission_region_agent_audit = $commission_region_agent->where("is_issue = 0 and shop_id =" . $shop_id)->sum("commission");
        $commission_audit = $commission_distribution_audit + $commission_partner_audit + $commission_region_agent_audit;
        // 待发放
        $user_commission_withdraw = new NfxUserCommissionWithdrawModel();
        $user_withdraw_audit_money = $user_commission_withdraw->where(array(
            "status" => 0,
            "shop_id" => $shop_id
        ))->sum("cash"); // 提现审核中
        $user_account = new NfxUserAccountModel();
        $user_commission_cash = $user_account->where("shop_id =" . $shop_id)->sum("commission_cash"); // 可发放
        $commission = $user_withdraw_audit_money + $user_commission_cash;
        return [
            "commission" => $commission,
            "commission_audit" => $commission_audit
        ];
    }

    public function isShopMember($shop_id, $uid)
    {
        $nfx_user = new NfxShopMemberAssociationModel();
        $count = $nfx_user->where([
            "shop_id" => $shop_id,
            "uid" => $uid
        ])->count();
        return $count;
    }
    /**
     * (non-PHPdoc)
     * @see \data\api\INfxUser::getUserParent()
     */
    public function getUserParent($shop_id, $uid)
    {
        $nfx_user = new NfxShopMemberAssociationModel();
        $data = $nfx_user->getInfo(['shop_id'=>$shop_id,'uid'=>$uid], 'source_uid');
        return $data;
    }
}
