<?php
/**
 * Member.php
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

/**
 * 前台会员服务层
 */
use data\api\IMember as IMember;
use data\model\AlbumPictureModel;
use data\model\NsCouponModel;
use data\model\NsGoodsModel;
use data\model\NsMemberAccountModel;
use data\model\NsMemberAccountRecordsModel;
use data\model\NsMemberAccountRecordsViewModel;
use data\model\NsMemberBalanceWithdrawModel;
use data\model\NsMemberExpressAddressModel;
use data\model\NsMemberFavoritesModel;
use data\model\NsMemberLevelModel;
use data\model\NsMemberModel as NsMemberModel;
use data\model\NsMemberRechargeModel;
use data\model\NsOrderModel;
use data\model\NsShopApplyModel;
use data\model\NsShopModel;
use data\model\UserModel as UserModel;
use data\service\Address;
use data\service\Goods;
use data\service\Member\MemberAccount;
use data\service\Member\MemberCoupon;
use data\service\User as User;
use data;
use data\model\NsCouponTypeModel;
use data\model\NsMemberBankAccountModel;
use data\model\NsMemberViewModel;
use data\model\NsPointConfigModel;
use data\service\Config;
use data\service\NfxUser;
use data\service\promotion\PromoteRewardRule;
use data\service\WebSite;
use Prophecy\Exception\Exception;
use think\Cookie;
use think\Session;
use data\model\NsOrderPaymentModel;

class Member extends User implements IMember
{

    function __construct()
    {
        parent::__construct();
    }
    
    /*
     * 前台添加会员(non-PHPdoc)
     * @see \data\api\IMember::registerMember()
     */
public function registerMember($user_name, $password, $email, $mobile, $user_qq_id, $qq_info, $wx_openid, $wx_info, $wx_unionid)
    {
        // if (! empty($user_name)) {
        // if (! preg_match("/^(?!\d+$)[\da-zA-Z]*$/i", $user_name)) {
        // return USER_WORDS_ERROR;
        // }
        // }
        if (! empty($user_qq_id) || ! empty($wx_openid) || ! empty($wx_unionid)) {
            $is_false = true;
        } else {
            if (trim($user_name) != "") {
                $error_info = $this->verifyValue($user_name, $password, "plain");
                $is_false = $error_info[0];
            } else {
                if ($mobile != "" && $email == "") {
                    if ($user_name == "") {
                        $user_name = $mobile;
                        $error_info = $this->verifyValue($user_name, $password, "mobile");
                        $is_false = $error_info[0];
                    }
                } else {
                    $error_info = $this->verifyValue($user_name, $password, "email");
                    $is_false = $error_info[0];
                }
            }
        }
        if (! $is_false) {
            return $error_info[1];
        }
        $res = parent::add($user_name, $password, $email, $mobile, 0, $user_qq_id, $qq_info, $wx_openid, $wx_info, $wx_unionid, 1);
        if ($res > 0) {
            // 获取默认会员等级id
            $member_level = new NsMemberLevelModel();
            $level_info = $member_level->getInfo([
                'is_default' => 1
            ], 'level_id');
            $member_level = $level_info['level_id'];
            $member = new NsMemberModel();
            $user_info_obj = parent::getUserInfoByUid($res);
            $data = array(
                'uid' => $res,
                'member_name' => $user_info_obj["nick_name"],
                'member_level' => $member_level,
                'reg_time' => time()
            );
            $retval = $member->save($data);
            hook('memberRegisterSuccess', $data);
            // 注册会员送积分
            $promote_reward_rule = new PromoteRewardRule();
            // 添加关注
            switch (NS_VERSION) {
                case NS_VER_B2C:
                    break;
                case NS_VER_B2C_FX:
                    if (! empty($_SESSION['source_uid'])) {
                        // 判断当前版本
                        $nfx_user = new NfxUser();
                        $nfx_user->userAssociateShop($res, 0, $_SESSION['source_uid']);
                        Session::set("tag_promoter", 0);
                    } else {
                        // 判断当前版本
                        $nfx_user = new NfxUser();
                        $nfx_user->userAssociateShop($res, 0, 0);
                        $shop_config = new NfxShopConfig();
                        $shop_config_info = $shop_config->getShopConfigDetail($this->instance_id);
                        if($shop_config_info['is_distribution_start']==0){
                            Session::set("tag_promoter", 1);
                        }
                       
                    }
                    break;
            }
            // 平台赠送积分
            $promote_reward_rule->RegisterMemberSendPoint(0, $res);
            // 注册成功后短信与邮箱提醒
            $params['shop_id'] = $this->instance_id;
            $params['user_id'] = $res;
            runhook('Notify', 'registAfter', $params);
            // 直接登录
            if (! empty($user_name)) {
                $this->login($user_name, $password);
            } elseif (! empty($mobile)) {
                $this->login($mobile, $password);
            } elseif (! empty($email)) {
                $this->login($email, $password);
            } elseif (! empty($user_qq_id)) {
                $this->qqLogin($user_qq_id);
            } elseif (! empty($wx_openid)) {
                $this->wchatLogin($wx_openid);
            } elseif (! empty($wx_unionid)) {
                $this->wchatUnionLogin($wx_unionid);
            }
        }
        return $res;
        // TODO Auto-generated method stub
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::addMember()
     */
    public function addMember($user_name, $password, $email, $sex, $status, $mobile, $member_level)
    {
        $res = parent::add($user_name, $password, $email, $mobile, 0, '', '', '', '', '', 1);
        if ($res > 0) {
            $member = new NsMemberModel();
            $data = array(
                'uid' => $res,
                'member_name' => $user_name,
                'member_level' => $member_level,
                'reg_time' => time()
            );
            $retval = $member->save($data);
            $user = new UserModel();
            $user->save([
                'user_status' => $status,
                'sex' => $sex
            ], [
                'uid' => $res
            ]);
            switch (NS_VERSION) {
                case NS_VER_B2C:
                    break;
                case NS_VER_B2C_FX:
                    // 判断当前版本
                    $nfx_user = new NfxUser();
                    $nfx_user->userAssociateShop($res, 0, 0);
                    break;
            }
            return $res;
        } else {
            return $res;
        }
    }

    /**
     * 通过用户id更新用户的昵称
     *
     * @param unknown $uid            
     * @param unknown $nickName            
     */
    public function updateNickNameByUid($uid, $nickName)
    {
        $user = new UserModel();
        $result = $user->save([
            'nick_name' => $nickName,
            "current_login_time" => time()
        ], [
            'uid' => $uid
        ]);
        return $result;
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IMember::deleteMember()
     */
    public function deleteMember($uid)
    {
        // TODO Auto-generated method stub
        $user = new UserModel();
        $user->startTrans();
        try {
            // 删除user信息
            $user->destroy($uid);
            $member = new NsMemberModel();
            // 删除member信息
            $retval = $member->destroy($uid);
            $member_account = new NsMemberAccountModel();
            // 删除会员账户信息
            $member_account->destroy([
                'uid' => array(
                    'in',
                    $uid
                )
            ]);
            // 删除会员账户记录信息
            $member_account_records = new NsMemberAccountRecordsModel();
            $member_account_records->destroy([
                'uid' => array(
                    'in',
                    $uid
                )
            ]);
            // 删除会员取现记录表
            $member_balance_withdraw = new NsMemberBalanceWithdrawModel();
            $member_balance_withdraw->destroy([
                'uid' => array(
                    'in',
                    $uid
                )
            ]);
            // 删除会员银行账户表
            $member_bank_account = new NsMemberBankAccountModel();
            $member_bank_account->destroy([
                'uid' => array(
                    'in',
                    $uid
                )
            ]);
            // 删除会员地址表
            $member_express_address = new NsMemberExpressAddressModel();
            $member_express_address->destroy([
                'uid' => array(
                    'in',
                    $uid
                )
            ]);
            $user->commit();
            return 1;
        } catch (\Exception $e) {
            $user->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 会员列表
     *
     * @param number $page_index            
     * @param number $page_size            
     * @param string $condition            
     * @param string $order            
     * @param string $field            
     */
    public function getMemberList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $member_view = new NsMemberViewModel();
        $result = $member_view->getViewList($page_index, $page_size, $condition, $order);
        foreach ($result['data'] as $k => $v) {
            $member_account = new MemberAccount();
            $result['data'][$k]['point'] = $member_account->getMemberPoint($v['uid'], '');
            $result['data'][$k]['balance'] = $member_account->getMemberBalance($v['uid']);
            $result['data'][$k]['coin'] = $member_account->getMemberCoin($v['uid']);

            if(NS_VERSION == NS_VER_B2C_FX){
                $promoter = new NfxPromoter();
                $result['data'][$k]['member_promoter'] = $promoter->getShopMemberAssociation($v['uid']);
            }
        }
        return $result;
    }

    /**
     * 获取积分列表
     *
     * @param unknown $page_index            
     * @param unknown $page_size            
     * @param unknown $condition            
     * @param string $order            
     * @param string $field            
     * @return multitype:number unknown
     */
    public function getPointList($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $member_account = new NsMemberAccountRecordsViewModel();
        $list = $member_account->getViewList($page_index, $page_size, $condition, 'nmar.create_time desc');
        if (! empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['type_name'] = MemberAccount::getMemberAccountRecordsName($v['from_type']);
                if($list['data'][$k]['account_type'] == 1 && ($list['data'][$k]['from_type']==1 || $list['data'][$k]['from_type'] ==2)){
                    $ns_order = new NsOrderModel();
                    $data_content = array();
                    $data_content = $ns_order -> getInfo(["order_id"=>$list['data'][$k]['data_id']],"order_id,order_no");
                    $list['data'][$k]['data_content'] = $data_content;
                }
            }
        }
        return $list;
    }

    /**
     * 获取
     *
     * @param unknown $page_index            
     * @param unknown $page_size            
     * @param unknown $condition            
     * @param string $order            
     * @param string $field            
     * @return multitype:number unknown
     */
    public function getAccountList($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $member_account = new NsMemberAccountRecordsViewModel();
        $list = $member_account->getViewList($page_index, $page_size, $condition, 'nmar.create_time desc');
        if (! empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['type_name'] = MemberAccount::getMemberAccountRecordsName($v['from_type']);
                $list['data'][$k]['account_type_name'] = MemberAccount::getMemberAccountRecordsTypeName($v['account_type']);
                if($list['data'][$k]['account_type'] == 2 && ($list['data'][$k]['from_type']==1 || $list['data'][$k]['from_type'] ==2)){
                    $ns_order = new NsOrderModel();
                    $data_content = array();
                    $data_content = $ns_order -> getInfo(["order_id"=>$list['data'][$k]['data_id']],"order_id,order_no");
                    $list['data'][$k]['data_content'] = $data_content;
                }
            }
        }
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getDefaultExpressAddress()
     */
    public function getDefaultExpressAddress()
    {
        $express_address = new NsMemberExpressAddressModel();
        $data = $express_address->getInfo([
            'uid' => $this->uid,
            'is_default' => 1
        ], '*');
        // 处理地址信息
        if (! empty($data)) {
            $address = new Address();
            $address_info = $address->getAddress($data['province'], $data['city'], $data['district']);
            $data['address_info'] = $address_info;
        }
        
        return $data;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberInfo()
     */
    public function getMemberInfo($uid = '')
    {
        // 获取ID
        if(empty($uid)){
            $uid = $this->uid;
        }
        // 获取当前会员积分数
        $member = new NsMemberModel();
        if (! empty($this->uid)) {
            $data = $member->getInfo([
                'uid' => $uid
            ], '*');
        } else {
            $data = '';
        }
        return $data;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberDetail()
     */
    public function getMemberDetail($shop_id = '', $uid = '')
    {   
        //获取会员ID
        if(empty($uid)){
            $uid = $this->uid;
        }
        
        // 获取基础信息
        if (! empty($uid)) {
            $member_info = $this->getMemberInfo($uid);
            if (empty($member_info)) {
                $member_info = array(
                    'level_id' => 0
                );
            }
            // 获取user信息
            
            $user_info = $this->getUserDetail($uid);
            $member_info['user_info'] = $user_info;
            
            // 获取优惠券信息
            $member_account = new MemberAccount();
            $member_info['point'] = $member_account->getMemberPoint($uid, $shop_id);
            $member_info['balance'] = $member_account->getMemberBalance($uid);
            $member_info['coin'] = $member_account->getMemberCoin($uid);
            // 会员等级名称
            $member_level = new NsMemberLevelModel();
            $level_name = $member_level->getInfo([
                'level_id' => $member_info['member_level']
            ], 'level_name');
            $member_info['level_name'] = $level_name['level_name'];
        } else {
            $member_info = '';
        }
        
        return $member_info;
    }

    /**
     * 获取用户的手机号
     *
     * @return unknown|string
     */
    public function getUserTelephone()
    {
        if (! empty($this->uid)) {
            
            $user = new UserModel();
            $res = $user->getInfo([
                'uid' => $this->uid
            ], 'user_tel');
            return $res['user_tel'];
        } else {
            return '';
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberImage()
     */
    public function getMemberImage($uid)
    {
        $user_model = new UserModel();
        $user_info = $user_model->getInfo([
            'uid' => $uid
        ], '*');
        if (! empty($user_info['user_headimg'])) {
            $member_img = $user_info['user_headimg'];
        } elseif (! empty($user_info['qq_openid'])) {
            $qq_info_array = json_decode($user_info['qq_info'], true);
            $member_img = $qq_info_array['figureurl_qq_1'];
        } elseif (! empty($user_info['wx_openid'])) {
            $member_img = '0';
        } else {
            $member_img = '0';
        }
        return $member_img;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::updateMemberInformation()
     */
    public function updateMemberInformation($user_name, $user_qq, $real_name, $sex, $birthday, $location, $user_headimg)
    {
        $useruser = new UserModel();
        $birthday = empty($birthday) ? '0000-00-00' : $birthday;
        $data = array(
            
            // 2017/2/22修改为nick_name 昵称
            "nick_name" => $user_name,
            "user_qq" => $user_qq,
            "real_name" => $real_name,
            "sex" => $sex,
            "birthday" => getTimeTurnTimeStamp($birthday),
            "location" => $location
        );
        $data2 = array(
            "user_headimg" => $user_headimg
        );
        if ($user_headimg == "") {
            $result = $useruser->save($data, [
                'uid' => $this->uid
            ]);
        } else {
            $result = $useruser->save($data2, [
                'uid' => $this->uid
            ]);
        }
        return $result;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::addMemberExpressAddress()
     */
    public function addMemberExpressAddress($consigner, $mobile, $phone, $province, $city, $district, $address, $zip_code, $alias)
    {
        $express_address = new NsMemberExpressAddressModel();
        $express_address->save([
            'is_default' => 0
        ], [
            'uid' => $this->uid
        ]);
        $express_address = new NsMemberExpressAddressModel();
        $data = array(
            'uid' => $this->uid,
            'consigner' => $consigner,
            'mobile' => $mobile,
            'phone' => $phone,
            'province' => $province,
            'city' => $city,
            'district' => $district,
            'address' => $address,
            'zip_code' => $zip_code,
            'alias' => $alias,
            'is_default' => 0
        );
        $express_address->save($data);
        $this->updateAddressDefault($express_address->id);
        return $express_address->id;
    }

    /**
     * 修改会员收货地址
     */
    public function updateMemberExpressAddress($id, $consigner, $mobile, $phone, $province, $city, $district, $address, $zip_code, $alias)
    {
        $express_address = new NsMemberExpressAddressModel();
        $data = array(
            'uid' => $this->uid,
            'consigner' => $consigner,
            'mobile' => $mobile,
            'phone' => $phone,
            'province' => $province,
            'city' => $city,
            'district' => $district,
            'address' => $address,
            'zip_code' => $zip_code,
            'alias' => $alias
        );
        $retval = $express_address->save($data, [
            'id' => $id
        ]);
        
        $retval = $this->updateAddressDefault($id);
        
        return $retval;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberExpressAddressList()
     */
    public function getMemberExpressAddressList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $express_address = new NsMemberExpressAddressModel();
        $data = $express_address->pageQuery($page_index, $page_size, [
            'uid' => $this->uid
        ], 'id desc', '*');
        // 处理地址信息
        if (! empty($data)) {
            foreach ($data['data'] as $key => $val) {
                $address = new Address();
                $address_info = $address->getAddress($val['province'], $val['city'], $val['district']);
                $data['data'][$key]['address_info'] = $address_info;
            }
        }
        return $data;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberExpressAddressDetail()
     */
    public function getMemberExpressAddressDetail($id)
    {
        $express_address = new NsMemberExpressAddressModel();
        $data = $express_address->get($id);
        if ($data['uid'] == $this->uid) {
            return $data;
        } else {
            return '';
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::memberAddressDelete()
     */
    public function memberAddressDelete($id)
    {
        $express_address = new NsMemberExpressAddressModel();
        $count = $express_address->where(array(
            "uid" => $this->uid
        ))->count();
        if ($count == 1) {
            return USER_ADDRESS_DELETE_ERROR;
        } else {
            $express_address_info = $express_address->getInfo([
                'id' => $id,
                'uid' => $this->uid
            ]);
            
            $res = $express_address->destroy($id);
            
            if ($express_address_info['is_default'] == 1) {
                $express_address_info = $express_address->where(array(
                    "uid" => $this->uid
                ))
                    ->order("id desc")
                    ->limit(0, 1)
                    ->select();
                $res = $this->updateAddressDefault($express_address_info[0]['id']);
            }
            
            return $res;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::updateAddressDefault()
     */
    public function updateAddressDefault($id)
    {
        $express_address = new NsMemberExpressAddressModel();
        $res = $express_address->save([
            'is_default' => 0
        ], [
            'uid' => $this->uid
        ]);
        $res = $express_address->save([
            'is_default' => 1
        ], [
            'id' => $id
        ]);
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberPointList()
     */
    function getShopAccountListByUser($uid, $page_index, $page_size)
    {
        $userMessage = new NsMemberAccountModel();
        $data = array(
            'uid' => $uid
        );
        $result = $userMessage->pageQuery($page_index, $page_size, $data, 'id asc', 'shop_id,point,balance');
        return $result;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberPointList()
     */
    public function getMemberPointList($start_time, $end_time)
    {
        $member_account = new NsMemberAccountRecordsModel();
        $condition = array(
            'uid' => $this->uid,
            'account_type' => 1,
            'create_time' => array(
                'EGT',
                getTimeTurnTimeStamp($start_time)
            ),
            'create_time' => array(
                'ELT',
                getTimeTurnTimeStamp($end_time)
            )
        );
        $list = $member_account->getQuery($condition, 'sign,number,from_type,data_id,text,create_time', 'create_time desc');
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getPageMemberPointList()
     */
    public function getPageMemberPointList($start_time, $end_time, $page_index, $page_size, $shop_id)
    {
        $member_account = new NsMemberAccountRecordsModel();
        $condition = array(
            'uid' => $this->uid,
            'account_type' => 1,
            'shop_id' => $shop_id
        /*     'create_time' =>array('EGT', $start_time),
            'create_time' =>array('ELT', $end_time) */
        
        );
        $list = $member_account->pageQuery($page_index, $page_size, $condition, 'create_time desc', 'sign,number,from_type,data_id,text,create_time');
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberBalanceList()
     */
    public function getMemberBalanceList($start_time, $end_time)
    {
        $member_account = new NsMemberAccountRecordsModel();
        $condition = array(
            'uid' => $this->uid,
            'account_type' => 2,
            'create_time' => array(
                'EGT',
                getTimeTurnTimeStamp($start_time)
            ),
            'create_time' => array(
                'ELT',
                getTimeTurnTimeStamp($end_time)
            )
        );
        $list = $member_account->getQuery($condition, 'sign,number,from_type,data_id,text,create_time', 'create_time desc');
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::etPageMemberBalanceList()
     */
    public function getPageMemberBalanceList($start_time, $end_time, $page_index, $page_size, $shop_id)
    {
        $member_account = new NsMemberAccountRecordsModel();
        $condition = array(
            'uid' => $this->uid,
            'account_type' => 2,
            'shop_id' => $shop_id
        );
        $list = $member_account->pageQuery($page_index, $page_size, $condition, 'create_time desc', 'sign,number,from_type,data_id,text,create_time');
        if (! empty($list['data'])) {}
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberBalanceList()
     */
    public function getMemberCoinList($start_time, $end_time)
    {
        $member_account = new NsMemberAccountRecordsModel();
        $condition = array(
            'uid' => $this->uid,
            'account_type' => 3,
            'create_time' => array(
                'EGT',
                getTimeTurnTimeStamp($start_time)
            ),
            'create_time' => array(
                'ELT',
                getTimeTurnTimeStamp($end_time)
            )
        );
        $list = $member_account->getQuery($condition, 'sign,number,from_type,data_id,text,create_time', 'create_time desc');
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::etPageMemberBalanceList()
     */
    public function getPageMemberCoinList($start_time, $end_time, $page_index, $page_size, $shop_id)
    {
        $member_account = new NsMemberAccountRecordsModel();
        $condition = array(
            'uid' => $this->uid,
            'account_type' => 3,
            'create_time' => array(
                'EGT',
                getTimeTurnTimeStamp($start_time)
            ),
            'create_time' => array(
                'ELT',
                getTimeTurnTimeStamp($end_time)
            ),
            'shop_id' => $shop_id
        );
        $list = $member_account->pageQuery($page_index, $page_size, $condition, 'create_time desc', 'sign,number,from_type,data_id,text,create_time');
        if (! empty($list['data'])) {}
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getOrderNumber()
     */
    public function getOrderNumber($order_id)
    {
        $member_account = new NsOrderModel();
        $condition = array(
            "order_id" => array(
                "EQ",
                $order_id
            )
        );
        $data = $member_account->getInfo($condition, "out_trade_no");
        return $data;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberCounponList()
     */
    public function getMemberCounponList($type, $shop_id = '')
    {
        $mebercoupon = new MemberCoupon();
        $list = $mebercoupon->getUserCouponList($type, $shop_id);
        return $list;
    }

    public function getUserCouponCount($type, $shop_id)
    {
        $mebercoupon = new MemberCoupon();
        $count = $mebercoupon->getUserCouponCount($type, $shop_id);
        return $count;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getShopNameByShopId()
     */
    public function getShopNameByShopId($shop_id)
    {
        $member_account = new NsShopModel();
        $condition = array(
            "shop_id" => array(
                "EQ",
                $shop_id
            )
        );
        return $member_account->getInfo($condition, "shop_name")['shop_name'];
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getWebSiteInfo()
     */
    public function getWebSiteInfo()
    {
        $web_site = new WebSite();
        $web_info = $web_site->getWebSiteInfo();
        return $web_info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberFavorites()
     */
    public function getMemberGoodsFavoritesList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $fav = new NsMemberFavoritesModel();
        $list = $fav->getGoodsFavouitesViewList($page_index, $page_size, $condition, $order);
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberFavorites()
     */
    public function getMemberShopsFavoritesList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $fav = new NsMemberFavoritesModel();
        $list = $fav->getShopsFavouitesViewList($page_index, $page_size, $condition, $order);
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberFavorites()
     */
    public function getMemberShopFavoritesList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $fav = new NsMemberFavoritesModel();
        $list = $fav->getFavouitesViewList($page_index, $page_size, $condition, $order);
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::addMemberFavouites()
     */
    public function addMemberFavouites($fav_id, $fav_type, $log_msg)
    {
        if (empty($this->uid)) {
            return 0;
        }
        $member_favorites = new NsMemberFavoritesModel();
        $count = $member_favorites->where(array(
            "fav_id" => $fav_id,
            "uid" => $this->uid,
            "fav_type" => $fav_type
        ))->count("log_id");
        // 检查数据表中，防止用户重复收藏
        if ($count > 0) {
            return 0;
        }
        if ($fav_type == 'goods') {
            // 收藏商品
            $goods = new NsGoodsModel();
            $goods_info = $goods->getInfo([
                'goods_id' => $fav_id
            ], 'goods_name,shop_id,price,picture,collects');
            // 查询商品图片信息
            $album = new AlbumPictureModel();
            $picture = $album->getInfo([
                'pic_id' => $goods_info['picture']
            ], 'pic_cover_small');
            $shop_name = "";
            $shop_logo = "";
            $shop_id = 0;
            switch (NS_VERSION) {
                case NS_VER_B2C:
                case NS_VER_B2C_FX:
                    $web_site = new WebSite();
                    $web_info = $web_site->getWebSiteInfo();
                    $shop_name = $web_info['title'];
                    $shop_logo = $web_info['logo'];
                    break;
            }
            $data = array(
                'uid' => $this->uid,
                'fav_id' => $fav_id,
                'fav_type' => $fav_type,
                'fav_time' => time(),
                'shop_id' => $shop_id,
                'shop_name' => $shop_name,
                'shop_logo' => $shop_logo,
                'goods_name' => $goods_info['goods_name'],
                'goods_image' => $picture['pic_cover_small'],
                'log_price' => $goods_info['price'],
                'log_msg' => $log_msg
            );
            $retval = $member_favorites->save($data);
            $goods->save(array(
                "collects" => $goods_info["collects"] + 1
            ), [
                "goods_id" => $fav_id
            ]);
            return $retval;
        } elseif ($fav_type == 'shop') {
            $shop = new NsShopModel();
            $shop_info = $shop->getInfo([
                'shop_id' => $fav_id
            ], 'shop_name,shop_logo,shop_collect');
            $data = array(
                'uid' => $this->uid,
                'fav_id' => $fav_id,
                'fav_type' => $fav_type,
                'fav_time' => time(),
                'shop_id' => $fav_id,
                'shop_name' => $shop_info['shop_name'],
                'shop_logo' => empty($shop_info['shop_logo']) ? ' ' : $shop_info['shop_logo'],
                'goods_name' => '',
                'goods_image' => '',
                'log_price' => 0,
                'log_msg' => $log_msg
            );
            $retval = $member_favorites->save($data);
            $shop->save(array(
                "shop_collect" => $shop_info["shop_collect"] + 1
            ), [
                "shop_id" => $fav_id
            ]);
            return $retval;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::deleteMemberFavorites()
     */
    public function deleteMemberFavorites($fav_id, $fav_type)
    {
        $retval = false;
        $member_favorites = new NsMemberFavoritesModel();
        /*
         * if(!empty($this->uid)){
         * $condition=array(
         * 'fav_id'=>$fav_id,
         * 'fav_type'=>$fav_type,
         * 'uid'=>$this->uid
         * );
         * $retval=$member_favorites->destroy($condition);
         * }
         * return $retval;
         */
        if (! empty($this->uid)) {
            if ($fav_type == 'goods') {
                // 收藏商品
                $goods = new NsGoodsModel();
                $goods_info = $goods->getInfo([
                    'goods_id' => $fav_id
                ], 'goods_name,shop_id,price,picture,collects');
                $condition = array(
                    'fav_id' => $fav_id,
                    'fav_type' => $fav_type,
                    'uid' => $this->uid
                );
                $retval = $member_favorites->destroy($condition);
                $collect = empty($goods_info["collects"]) ? 0 : $goods_info["collects"];
                $collect --;
                if ($collect < 0) {
                    $collect = 0;
                }
                $goods->save([
                    "collects" => $collect
                ], [
                    "goods_id" => $fav_id
                ]);
                return $retval;
            } elseif ($fav_type == 'shop') {
                $shop = new NsShopModel();
                $shop_info = $shop->getInfo([
                    'shop_id' => $fav_id
                ], 'shop_name,shop_logo,shop_collect');
                $condition = array(
                    'fav_id' => $fav_id,
                    'fav_type' => $fav_type,
                    'uid' => $this->uid
                );
                $retval = $member_favorites->destroy($condition);
                $shop_collect = empty($shop_info["shop_collect"]) ? 0 : $shop_info["shop_collect"];
                $shop_collect --;
                if ($shop_collect < 0) {
                    $shop_collect = 0;
                }
                $shop->save([
                    "shop_collect" => $shop_collect
                ], [
                    "shop_id" => $fav_id
                ]);
                return $retval;
            }
        }
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \data\api\IMember::getIsMemberFavorites()
     */
    public function getIsMemberFavorites($uid, $fav_id, $fav_type)
    {
        $member_favorites = new NsMemberFavoritesModel();
        $condition = array(
            'uid' => $uid,
            'fav_id' => $fav_id,
            'fav_type' => $fav_type
        );
        $res = $member_favorites->where($condition)->count();
        return $res;
    }

    /**
     * 获取浏览历史
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberViewHistory()
     */
    public function getMemberViewHistory()
    {
        $has_history = Cookie::has('goodshistory');
        if ($has_history) {
            $goods_id_array = Cookie::get('goodshistory');
            $goods = new Goods();
            $list = $goods->getGoodsQueryLimit([
                'ng.goods_id' => array(
                    'in',
                    $goods_id_array
                ),
                'ng.state' => 1
            ], "ng.goods_id,ng.goods_name,ng_sap.pic_cover_mid,ng.price,ng.point_exchange_type,ng.point_exchange,ng.promotion_price");
            return $list;
        } else {
            return '';
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberAllViewHistory()
     */
    public function getMemberAllViewHistory($uid, $start_time, $end_time)
    {}

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::addMemberViewHistory()
     */
    public function addMemberViewHistory($goods_id)
    {
        $has_history = Cookie::has('goodshistory');
        if ($has_history) {
            $goods_id_array = Cookie::get('goodshistory');
            Cookie::set('goodshistory', $goods_id_array . ',' . $goods_id, 3600);
        } else {
            Cookie::set('goodshistory', $goods_id, 3600);
        }
        return 1;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::deleteMemberViewHistory()
     */
    public function deleteMemberViewHistory()
    {
        if (Cookie::has('goodshistory')) {
            Session::set('goodshistory', Cookie::get('goodshistory'));
        }
        Cookie::set('goodshistory', null);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberIsApplyShop()
     */
    public function getMemberIsApplyShop($uid)
    {
        if ($this->is_system == 1) {
            return 'is_system';
        } else {
            // 是否正在申请
            $shop_apply = new NsShopApplyModel();
            $apply = $shop_apply->get([
                'uid' => $uid
            ]);
            if (! empty($apply)) {
                if ($apply['apply_state'] == - 1) {
                    // 已被拒绝
                    return 'refuse_apply';
                } else 
                    if ($apply['apply_state'] == 2) {
                        // 已同意
                        return 'is_system';
                    } else {
                        // 存在正在申请
                        return 'is_apply';
                    }
            } else {
                // 可以申请
                return 'apply';
            }
        }
    }

    /**
     * 猜你喜欢(non-PHPdoc)
     *
     * @see \data\api\IMember::getGuessMemberLikes()
     */
    public function getGuessMemberLikes()
    {
        $history = Cookie::has('goodshistory') ? Cookie::get('goodshistory') : Session::get('goodshistory');
        if (! empty($history)) {
            $history_array = explode(",", $history);
            $goods_id = $history_array[count($history_array) - 1];
            $goods_model = new NsGoodsModel();
            $category_id = $goods_model->getInfo([
                'goods_id' => $goods_id
            ], 'category_id');
        } else {
            $category_id['category_id'] = 0;
        }
        $goods = new Goods();
        
        $goods_list = $goods->getGoodsQueryLimit([
            'ng.category_id' => $category_id['category_id'],
            'ng.state' => 1
        ], "ng.goods_id,ng.goods_name,ng_sap.pic_cover_mid,ng.price,ng.point_exchange_type,ng.point_exchange,ng.promotion_price", PAGESIZE);
        
        return $goods_list;
    }

    /**
     * 用户余额
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberAccount()
     */
    public function getMemberAccount($uid, $shop_id)
    {
        $member_account = new NsMemberAccountModel();
        $account_info = $member_account->getInfo([
            'uid' => $uid,
            'shop_id' => $shop_id
        ], 'point');
        if (empty($account_info)) {
            $account_info["point"] = 0;
        }
        $account = new MemberAccount();
        $account_info['balance'] = $account->getMemberBalance($uid);
        $account_info['coin'] = $account->getMemberCoin($uid);
        return $account_info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::memberPointToBalance()
     */
    public function memberPointToBalance($uid, $shop_id, $point)
    {
        $member_account_model = new NsMemberAccountModel();
        $member_account_model->startTrans();
        try {
            $member_account_info = $this->getMemberAccount($uid, $shop_id);
            if ($point > $member_account_info['point']) {
                $member_account_model->commit();
                return LOW_POINT;
            } else {
                $point_config = new NsPointConfigModel();
                $point_info = $point_config->getInfo([
                    'shop_id' => $shop_id
                ], 'is_open, convert_rate');
                if ($point_info['is_open'] == 0 || empty($point_info)) {
                    $member_account_model->rollback();
                    return "积分兑换功能关闭";
                } else {
                    $member_account = new MemberAccount();
                    $exchange_balance = $member_account->pointToBalance($point, $shop_id);
                    $retval = $member_account->addMemberAccountData($shop_id, 1, $uid, 0, $point * (- 1), 3, 0, '积分兑换余额');
                    if ($retval < 0) {
                        $member_account_model->rollback();
                        return $retval;
                    }
                    $retval = $member_account->addMemberAccountData($shop_id, 2, $uid, 1, $exchange_balance, 3, 0, '积分兑换余额');
                    if ($retval < 0) {
                        $member_account_model->rollback();
                        return $retval;
                    }
                    $member_account_model->commit();
                    return 1;
                }
            }
        } catch (\Exception $e) {
            $member_account_model->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::memberShopPointCount()
     */
    public function memberShopPointCount($uid = 0, $shop_id = 0)
    {
        $member_account_model = new NsMemberAccountModel();
        $point_count = $member_account_model->getInfo([
            'shop_id' => $shop_id,
            'uid' => $uid
        ], 'point')['point'];
        return $point_count;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::memberShopBalanceCount()
     */
    public function memberShopBalanceCount($uid = 0, $shop_id = 0)
    {
        $member_account_model = new NsMemberAccountModel();
        $balance_count = $member_account_model->getInfo([
            'shop_id' => $shop_id,
            'uid' => $uid
        ], 'balance')['balance'];
        return $balance_count;
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IMember::getMemberAll()
     */
    public function getMemberAll($condition)
    {
        // TODO Auto-generated method stub
        $user = new UserModel();
        $user_data = $user->all($condition);
        return $user_data;
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IMember::getMemberCount()
     */
    public function getMemberCount($condition)
    {
        // TODO Auto-generated method stub
        $user = new UserModel();
        $user_sum = $user->where($condition)->count();
        return $user_sum;
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IMember::getMemberMonthCount()
     */
    public function getMemberMonthCount($begin_date, $end_date)
    {
        // TODO Auto-generated method stub
        // $begin = date('Y-m-01', strtotime(date("Y-m-d")));
        // $end = date('Y-m-d', strtotime("$begin +1 month -1 day"));
        $user = new UserModel();
        $condition["reg_time"] = [
            [
                ">",
                $begin_date
            ],
            [
                "<",
                $end_date
            ]
        ];
        // 一段时间内的注册用户
        $user_list = $user->all($condition);
        $begintime = strtotime($begin_date);
        $endtime = strtotime($end_date);
        
        $list = array();
        for ($start = $begintime; $start <= $endtime; $start += 24 * 3600) {
            $list[date("Y-m-d", $start)] = array();
            $user_num = 0;
            foreach ($user_list as $v) {
                if (date("Y-m-d", strtotime($v["reg_time"])) == date("Y-m-d", $start)) {
                    $user_num = $user_num + 1;
                }
            }
            $list[date("Y-m-d", $start)] = $user_num;
        }
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::addMemberAccount()
     */
    public function addMemberAccount($shop_id, $type, $uid, $num, $text)
    {
        $member_account = new MemberAccount();
        $retval = $member_account->addMemberAccountData($shop_id, $type, $uid, 1, $num, 10, 0, $text);
        return $retval;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getIsMemberSign()
     */
    public function getIsMemberSign($uid, $shop_id)
    {
        $member_account_records = new NsMemberAccountRecordsModel();
        $day_begin_time = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $day_end_time = mktime(23, 59, 59, date('m'), date('d'), date('Y'));
        $condition = array(
            'uid' => $uid,
            'shop_id' => $shop_id,
            'account_type' => 1,
            'from_type' => 5,
            'create_time' => array(
                'between',
                [
                    $day_begin_time,
                    $day_end_time
                ]
            )
        );
        $count = $member_account_records->getCount($condition);
        return $count;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getIsMemberShare()
     */
    public function getIsMemberShare($uid, $shop_id)
    {
        $member_account_records = new NsMemberAccountRecordsModel();
        $day_begin_time = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $day_end_time = mktime(23, 59, 59, date('m'), date('d'), date('Y'));
        $condition = array(
            'uid' => $uid,
            'shop_id' => $shop_id,
            'account_type' => 1,
            'from_type' => 6,
            'create_time' => array(
                'between',
                [
                    $day_begin_time,
                    $day_end_time
                ]
            )
        );
        $count = $member_account_records->getCount($condition);
        return $count;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getPageMemberSignList()
     */
    public function getPageMemberSignList($page_index, $page_size, $shop_id)
    {
        $member_account = new NsMemberAccountRecordsModel();
        $condition = array(
            'uid' => $this->uid,
            'account_type' => 1,
            'shop_id' => $shop_id,
            'from_type' => '5'
        );
        $list = $member_account->pageQuery($page_index, $page_size, $condition, 'create_time desc', 'sign,number,from_type,data_id,text,create_time');
        return $list;
    }

    /**
     * 用户退出
     */
    public function Logout()
    {
        parent::Logout();
        $_SESSION['order_tag'] = ""; // 清空订单
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberLevelList()
     */
    public function getMemberLevelList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $member_level = new NsMemberLevelModel();
        return $member_level->pageQuery($page_index, $page_size, $condition, $order, $field);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberLevelDetail()
     */
    public function getMemberLevelDetail($level_id)
    {
        $member_level = new NsMemberLevelModel();
        return $member_level->get($level_id);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::addMemberLevel()
     */
    public function addMemberLevel($shop_id, $level_name, $min_integral, $quota, $upgrade, $goods_discount, $desc, $relation)
    {
        $member_level = new NsMemberLevelModel();
        $data = array(
            'shop_id' => $shop_id,
            'level_name' => $level_name,
            'min_integral' => $min_integral,
            'quota' => $quota,
            'upgrade' => $upgrade,
            'goods_discount' => $goods_discount,
            'desc' => $desc,
            'relation' => $relation
        );
        $member_level->save($data);
        $data['level_id'] = $member_level->level_id;
        hook("memberLevelSaveSuccess", $data);
        return $member_level->level_id;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::updateMemberLevel()
     */
    public function updateMemberLevel($level_id, $shop_id, $level_name, $min_integral, $quota, $upgrade, $goods_discount, $desc, $relation)
    {
        $member_level = new NsMemberLevelModel();
        $data = array(
            'shop_id' => $shop_id,
            'level_name' => $level_name,
            'min_integral' => $min_integral,
            'quota' => $quota,
            'upgrade' => $upgrade,
            'goods_discount' => $goods_discount,
            'desc' => $desc,
            'relation' => $relation
        );
        /* return $member_level->save($data, ['level_id' => $level_id]); */
        $res = $member_level->save($data, [
            'level_id' => $level_id
        ]);
        $data['level_id'] = $level_id;
        hook("memberLevelSaveSuccess", $data);
        if ($res == 0) {
            return 1;
        }
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::deleteMemberLevel()
     */
    public function deleteMemberLevel($level_id)
    {
        $member_level = new NsMemberLevelModel();
        $member_count = $this->getMemberLevelUserCount($level_id);
        if ($member_count > 0) {
            return MEMBER_LEVEL_DELETE;
        } else {
            return $member_level->destroy($level_id);
        }
    }

    /**
     * 查询会员的等级下是否有会员
     *
     * @param unknown $level_id            
     */
    private function getMemberLevelUserCount($level_id)
    {
        $member_count = 0;
        $member_model = new NsMemberModel();
        $member_count = $member_model->getCount([
            'member_level' => $level_id
        ]);
        return $member_count;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::modifyMemberLevelField()
     */
    public function modifyMemberLevelField($level_id, $field_name, $field_value)
    {
        $member_level = new NsMemberLevelModel();
        return $member_level->save([
            "$field_name" => $field_value
        ], [
            'level_id' => $level_id
        ]);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::createMemberRecharge()
     */
    public function createMemberRecharge($recharge_money, $uid, $out_trade_no)
    {
        $member_recharge = new NsMemberRechargeModel();
        $pay = new UnifyPay();
        $data = array(
            'recharge_money' => $recharge_money,
            'uid' => $uid,
            'out_trade_no' => $pay->createOutTradeNo()
        );
        $res = $member_recharge->save($data);
        if ($res) {
            $pay->createPayment($this->instance_id, $out_trade_no, '余额充值', '用户通知余额', $recharge_money, 4, $member_recharge->id);
        }
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::payMemberRecharge()
     */
    public function payMemberRecharge($out_trade_no, $pay_type)
    {
        $member_recharge_model = new NsMemberRechargeModel();
        $pay = new UnifyPay();
        $pay_info = $pay->getPayInfo($out_trade_no);
        if (! empty($pay_info)) {
            $type_alis_id = $pay_info["type_alis_id"];
            $pay_status = $pay_info["pay_status"];
            if ($pay_status == 1) {
                // 支付成功
                $racharge_obj = $member_recharge_model->get($type_alis_id);
                if (! empty($racharge_obj)) {
                    $data = array(
                        "is_pay" => 1,
                        "status" => 1
                    );
                    $member_recharge_model->save($data, [
                        "id" => $racharge_obj["id"]
                    ]);
                    $member_account = new MemberAccount();
                    if ($pay_type == 1) {
                        $type_name = '微信充值';
                    } elseif ($pay_type == 2) {
                        $type_name = '支付宝充值';
                    } else {
                        $type_name = '余额充值';
                    }
                    $member_account->addMemberAccountData($pay_info["shop_id"], 2, $racharge_obj["uid"], 1, $racharge_obj["recharge_money"], 4, $racharge_obj["id"], $type_name);
                    runhook("Notify", "rechargeSuccessBusiness", [
                        "shop_id" => 0,
                        "out_trade_no" => $out_trade_no,
                        "uid" => $racharge_obj["uid"]
                    ]); // 用户余额充值成功商家提醒
                    runhook("Notify", "rechargeSuccessUser", [
                        "shop_id" => 0,
                        "out_trade_no" => $out_trade_no,
                        "uid" => $racharge_obj["uid"]
                    ]); // 用户余额充值成功用户提醒
                    hook('memberBalanceRechargeSuccess', [
                        'out_trade_no' => $out_trade_no,
                        'uid' => $racharge_obj["uid"],
                        'time' => time()
                    ]);
                }
            }
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::memberBankAccount()
     */
    public function getMemberBankAccount($is_default = 0)
    {
        $member_bank_account = new NsMemberBankAccountModel();
        $uid = $this->uid;
        $bank_account_list = '';
        if (! empty($uid)) {
            if (empty($is_default)) {
                $bank_account_list = $member_bank_account->getQuery([
                    'uid' => $uid
                ], '*', 'id desc');
            } else {
                $bank_account_list = $member_bank_account->getQuery([
                    'uid' => $uid,
                    'is_default' => 1
                ], '*', '');
            }
        }
        
        return $bank_account_list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::addMemberBankAccount()
     */
    public function addMemberBankAccount($uid, $account_type, $account_type_name, $branch_bank_name, $realname, $account_number, $mobile)
    {
        $member_bank_account = new NsMemberBankAccountModel();
        $member_bank_account->startTrans();
        try {
            $data = array(
                'uid' => $uid,
                'account_type' => $account_type,
                'account_type_name' => $account_type_name,
                'branch_bank_name' => $branch_bank_name,
                'realname' => $realname,
                'account_number' => $account_number,
                'mobile' => $mobile,
                'create_date' => time(),
                'modify_date' => time()
            );
            
            $member_bank_account->save($data);
            $account_id = $member_bank_account->id;
            $retval = $this->setMemberBankAccountDefault($uid, $account_id);
            $member_bank_account->commit();
            return $account_id;
        } catch (Exception $e) {
            $member_bank_account->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::updateMemberBankAccount()
     */
    public function updateMemberBankAccount($account_id, $account_type, $account_type_name, $branch_bank_name, $realname, $account_number, $mobile)
    {
        $member_bank_account = new NsMemberBankAccountModel();
        $member_bank_account->startTrans();
        try {
            
            $data = array(
                'account_type' => $account_type,
                'account_type_name' => $account_type_name,
                'branch_bank_name' => $branch_bank_name,
                'realname' => $realname,
                'account_number' => $account_number,
                'mobile' => $mobile,
                'modify_date' => time()
            );
            $member_bank_account->save($data, [
                'id' => $account_id
            ]);
            $retval = $this->setMemberBankAccountDefault($this->uid, $account_id);
            $member_bank_account->commit();
            return $account_id;
        } catch (Exception $e) {
            $member_bank_account->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::delMemberBankAccount()
     */
    public function delMemberBankAccount($account_id)
    {
        $member_bank_account = new NsMemberBankAccountModel();
        $uid = $this->uid;
        $retval = $member_bank_account->destroy([
            'uid' => $uid,
            'id' => $account_id
        ]);
        return $retval;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::setMemberBankAccountDefault()
     */
    public function setMemberBankAccountDefault($uid, $account_id)
    {
        $member_bank_account = new NsMemberBankAccountModel();
        $member_bank_account->update([
            'is_default' => 0
        ], [
            'uid' => $uid,
            'is_default' => 1
        ]);
        $member_bank_account->update([
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
     * @see \data\api\IMember::getMemberBankAccountDetail()
     */
    public function getMemberBankAccountDetail($id)
    {
        $member_bank_account = new NsMemberBankAccountModel();
        $bank_account_info = $member_bank_account->getInfo([
            'id' => $id,
            'uid' => $this->uid
        ], '*');
        return $bank_account_info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\niufenxiao\INfxUser::getMemberBalanceWithdraw()
     */
    public function getMemberBalanceWithdraw($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $member_balance_withdraw = new NsMemberBalanceWithdrawModel();
        $list = $member_balance_withdraw->pageQuery($page_index, $page_size, $condition, $order, '*');
        if (! empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                $user = new UserModel();
                $userinfo = $user->getInfo([
                    'uid' => $v['uid']
                ]);
                $list['data'][$k]['real_name'] = $userinfo["nick_name"];
            }
        }
        return $list;
    }

    /**
     * 获取会员提现审核数量
     * 2017年7月10日 12:05:19
     *
     * @ERROR!!!
     *
     * @see \data\api\IMember::getMemberBalanceWithdrawCount()
     */
    public function getMemberBalanceWithdrawCount($condition = '')
    {
        $member_balance_withdraw = new NsMemberBalanceWithdrawModel();
        $count = $member_balance_withdraw->getCount($condition);
        return $count;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\niufenxiao\INfxUser::addMemberBalanceWithdraw()
     */
    public function addMemberBalanceWithdraw($shop_id, $withdraw_no, $uid, $bank_account_id, $cash)
    {
        // 得到本店的提线设置
        $config = new Config();
        $withdraw_info = $config->getBalanceWithdrawConfig($shop_id);
        // 判断是否余额提现设置是否为空 是否启用
        if (empty($withdraw_info) || $withdraw_info['is_use'] == 0) {
            return USER_WITHDRAW_NO_USE;
        }
        // 提现倍数判断
        if ($withdraw_info['value']["withdraw_multiple"] != 0) {
            $mod = $cash % $withdraw_info['value']["withdraw_multiple"];
            if ($mod != 0) {
                return USER_WITHDRAW_BEISHU;
            }
        }
        // 最小提现额判断
        if ($cash < $withdraw_info['value']["withdraw_cash_min"]) {
            return USER_WITHDRAW_MIN;
        }
        // 判断会员当前余额
        $member_account = new MemberAccount();
        $balance = $member_account->getMemberBalance($uid);
        if ($balance <= 0) {
            return ORDER_CREATE_LOW_PLATFORM_MONEY;
        }
        if ($balance < $cash || $cash <= 0) {
            return ORDER_CREATE_LOW_PLATFORM_MONEY;
        }
        // 获取 提现账户
        $member_bank_account = new NsMemberBankAccountModel();
        $bank_account_info = $member_bank_account->getInfo([
            'id' => $bank_account_id
        ], '*');
        $brank_name = $bank_account_info['branch_bank_name'];
        
        // 提现方式如果不是银行卡，则显示账户类型名称
        if ($bank_account_info['account_type'] != 1) {
            $brank_name = $bank_account_info['account_type_name'];
        }
        
        // 添加提现记录
        $balance_withdraw = new NsMemberBalanceWithdrawModel();
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
        $balance_withdraw->save($data);
        // 添加账户流水
        $member_account->addMemberAccountData($shop_id, 2, $uid, 0, - $cash, 8, $balance_withdraw->id, "会员余额提现");
        if ($balance_withdraw->id) {
            $params['id'] = $balance_withdraw->id;
            $params['type'] = 'balance';
            hook("memberWithdrawApplyCreateSuccess", $params);
        }
        return $balance_withdraw->id;
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\niufenxiao\INfxUser::MemberBalanceWithdrawAudit()
     */
    public function MemberBalanceWithdrawAudit($shop_id, $id, $status, $transfer_type, $transfer_name, $transfer_money, $transfer_remark, $transfer_no, $transfer_account_no, $type_id)
    {
        
        // TODO Auto-generated method stub
        // 查询转账的信息
        $member_balance_withdraw = new NsMemberBalanceWithdrawModel();
        $member_balance_withdraw_info = $member_balance_withdraw->getInfo([
            'id' => $id
        ], '*');
        $transfer_status = 0;
        $transfer_result = "";
        if ($member_balance_withdraw_info["transfer_status"] != 1 && $member_balance_withdraw_info["status"] != 1 && $status != - 1) {
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
                        'uid' => $member_balance_withdraw_info['uid']
                    ]);
                    $openid = $userinfo["wx_openid"];
                    $unify = new UnifyPay();
                    $wechat_retval = $unify->wechatTransfers($openid, $member_balance_withdraw_info["withdraw_no"], $transfer_money * 100, $member_balance_withdraw_info["realname"], $transfer_remark);
                    $transfer_result = $wechat_retval["msg"];
                    if ($wechat_retval["is_success"] > 0) {
                        $transfer_status = 1;
                    } else {
                        $transfer_status = - 1;
                    }
                } else {
                    // 线上支付包转账
                    $unify = new UnifyPay();
                    $alipay_retval = $unify->aliayTransfers($member_balance_withdraw_info["withdraw_no"], $member_balance_withdraw_info["account_number"], $transfer_money);
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
            $member_balance_withdraw = new NsMemberBalanceWithdrawModel();
            $member_account = new MemberAccount();
            $retval = $member_balance_withdraw->where(array(
                "shop_id" => $shop_id,
                "id" => $id
            ))->update(array(
                "status" => $status,
                "transfer_type" => $transfer_type,
                "transfer_name" => $transfer_name,
                "transfer_money" => $transfer_money,
                "transfer_status" => $transfer_status,
                "transfer_remark" => $transfer_remark,
                "transfer_result" => $transfer_result,
                "transfer_account_no" => $transfer_account_no,
                "transfer_no" => $transfer_no
            ));
            if ($retval > 0 && $status == - 1) {
                $member_account->addMemberAccountData($shop_id, 2, $member_balance_withdraw_info['uid'], 1, $member_balance_withdraw_info["cash"], 9, $id, "会员余额提现退回");
            }
            if ($retval > 0 && $status == 1) {
                // 会员提现审核通过钩子
                hook('memberWithdrawAuditAgree', [
                    'id' => $id
                ]);
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
    
    /*
     * (non-PHPdoc)
     * @see \data\api\niufenxiao\INfxUser::MemberBalanceWithdrawRefuse()
     */
    public function userCommissionWithdrawRefuse($shop_id, $id, $status, $remark)
    {
        // TODO Auto-generated method stub
        $member_balance_withdraw = new NsMemberBalanceWithdrawModel();
        $member_account = new MemberAccount();
        $retval = $member_balance_withdraw->where(array(
            "shop_id" => $shop_id,
            "id" => $id
        ))->update(array(
            "status" => $status,
            "memo" => $remark
        ));
        $member_balance_withdraw = new NsMemberBalanceWithdrawModel();
        $member_balance_withdraw_info = $member_balance_withdraw->getInfo([
            'id' => $id
        ], '*');
        if ($retval > 0 && $status == - 1) {
            $member_account->addMemberAccountData($shop_id, 2, $member_balance_withdraw_info['uid'], 1, $member_balance_withdraw_info["cash"], 9, $id, "会员余额提现退回");
        }
        return $retval;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\niufenxiao\INfxUser::getMemberWithdrawalsDetails()
     */
    public function getMemberWithdrawalsDetails($id)
    {
        $member_balance_withdraw = new NsMemberBalanceWithdrawModel();
        $retval = $member_balance_withdraw->getInfo([
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
     * 会员获取优惠券
     *
     * @param unknown $uid            
     * @param unknown $coupon_type_id            
     * @param unknown $get_type            
     */
    public function memberGetCoupon($uid, $coupon_type_id, $get_type)
    {
        if ($get_type == 2) {
            $coupon = new NsCouponModel();
            $count = $coupon->getCount([
                'uid' => $uid,
                'coupon_type_id' => $coupon_type_id
            ]);
            $coupon_type = new NsCouponTypeModel();
            $coupon_type_info = $coupon_type->getInfo([
                'coupon_type_id' => $coupon_type_id
            ], 'max_fetch, start_time, end_time, term_of_validity_type');
            if (empty($coupon_type_info)) {
                return 0;
            }
            $time = time();
           /*  if($coupon_type_info['term_of_validity_type'] == 0){
                if ($time < $coupon_type_info["start_time"] || $time > $coupon_type_info["end_time"]) {
                    return 0;
                }
            } */
            if ($time > $coupon_type_info[""])
                if ($coupon_type_info['max_fetch'] != 0) {
                    if ($count >= $coupon_type_info['max_fetch']) {
                        return USER_HEAD_GET;
                        exit();
                    }
                }
        }
        
        $member_coupon = new MemberCoupon();
        $retval = $member_coupon->UserAchieveCoupon($uid, $coupon_type_id, $get_type);
        return $retval;
    }

    /**
     * 获取会员下面的优惠券列表
     *
     * @param unknown $uid            
     */
    public function getMemberCouponTypeList($shop_id, $uid)
    {
        // 查询可以发放的优惠券类型
        $coupon_type_model = new NsCouponTypeModel();
        $condition = array(
            'start_time' => array(
                'ELT',
                time()
            ),
            'end_time' => array(
                'EGT',
                time()
            ),
            'is_show' => 1,
            'shop_id' => $shop_id,
            'term_of_validity_type' => 0
        );
        $coupon_type_list = $coupon_type_model->getQuery($condition, '*', 'start_time desc');
        $coupon_type_list_two = $coupon_type_model->getQuery(['is_show'=>1, 'shop_id'=>$shop_id, 'term_of_validity_type'=> 1], '*', 'create_time desc');
        $coupon_type_list = array_merge($coupon_type_list, $coupon_type_list_two);
        
        if (! empty($uid)) {
            $list = array();
            if (! empty($coupon_type_list)) {
                foreach ($coupon_type_list as $k => $v) {
                    if ($v['max_fetch'] == 0) {
                        // 不限领
                        $v['received_num'] = 0;
                        $list[] = $v;
                    } else {
                        $coupon = new NsCouponModel();
                        $count = $coupon->getCount([
                            'uid' => $uid,
                            'coupon_type_id' => $v['coupon_type_id']
                        ]);
                        if ($count < $v['max_fetch']) {
                            $v["received_num"] = $count; //该用户已领取数量
                            $list[] = $v;
                        }
                    }
                }
            }
            return $list;
        } else {
            return $coupon_type_list;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\niushop\IMember::getMemberExtractionBalanceList()
     */
    public function getMemberExtractionBalanceList($uid)
    {
        $member_account = new NsMemberAccountRecordsModel();
        $condition = array(
            'uid' => $uid,
            'account_type' => 2
        );
        $list = $member_account->getQuery($condition, 'sign,number,from_type,data_id,text,create_time', 'create_time desc');
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::updateMemberByAdmin()
     */
    public function updateMemberByAdmin($uid, $user_name, $email, $sex, $status, $mobile, $nick_name, $member_level)
    {
        $retval = parent::updateUserInfo($uid, $user_name, $email, $sex, $status, $mobile, $nick_name);
        if ($retval < 0) {
            return $retval;
        } else {
            // 修改会员等级
            $member = new NsMemberModel();
            $retval = $member->save([
                'member_level' => $member_level
            ], [
                'uid' => $uid
            ]);
            return $retval;
        }
    }

    /**
     * 设置用户支付密码
     *
     * @ERROR!!!
     *
     * @see \data\api\IMember::setUserPaymentPassword()
     */
    public function setUserPaymentPassword($uid, $payment_password)
    {
        $user = new UserModel();
        $retval = $user->save([
            'payment_password' => md5($payment_password),
            'is_set_payment_password' => 1
        ], [
            'uid' => $uid
        ]);
        return $retval;
    }

    /**
     * 修改用户支付密码
     */
    public function updateUserPaymentPassword($uid, $old_payment_password, $new_payment_password)
    {
        // 检测原密码是否正确
        $user = new UserModel();
        $res = $user->getInfo([
            "uid" => $uid,
            "payment_password" => md5($old_payment_password),
            "is_set_payment_password" => 1
        ], "uid");
        // 修改支付密码
        if ($res['uid'] != '') {
            return $user->save([
                "payment_password" => md5($new_payment_password)
            ], [
                "uid" => $uid
            ]);
        } else {
            return - 1;
        }
    }
    
    // 验证账号密码
    private function verifyValue($user_name, $password, $reg_type = "plain")
    {
        $instanceid = 0;
        $config = new Config();
        $reg_config_info = $config->getRegisterAndVisit($this->instance_id);
        
        // 验证注册
        $reg_config = json_decode($reg_config_info["value"], true);
        if ($reg_config["is_register"] != 1) {
            return array(
                false,
                REGISTER_CONFIG_OFF
            );
        }
        if ($reg_type == "mobile") {
            if (stristr($reg_config["register_info"], "mobile") === false) {
                return array(
                    false,
                    REGISTER_MOBILE_CONFIG_OFF
                );
            }
        } else 
            if ($reg_type == "email") {
                if (stristr($reg_config["register_info"], "email") === false) {
                    return array(
                        false,
                        REGISTER_EMAIL_CONFIG_OFF
                    );
                }
            } else {
                if (stristr($reg_config["register_info"], "plain") === false) {
                    return array(
                        false,
                        REGISTER_PLAIN_CONFIG_OFF
                    );
                }
                if (trim($user_name) == "") {
                    return array(
                        false,
                        REGISTER_USERNAME_ERROR
                    );
                }
                
                if (preg_match("/^[\x{4e00}-\x{9fa5}]+$/u", $user_name)) {
                    return array(
                        false,
                        REGISTER_USERNAME_ERROR
                    );
                }
                if (preg_match("/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/", $user_name)) {
                    return array(
                        false,
                        REGISTER_USERNAME_ERROR
                    );
                }
                if (preg_match("/^1(3|4|5|7|8)\d{9}$/", $user_name)) {
                    return array(
                        false,
                        REGISTER_USERNAME_ERROR
                    );
                }
                $usernme_verify_array = array();
                if (trim($reg_config["name_keyword"]) != "") {
                    $usernme_verify_array = explode(",", $reg_config["name_keyword"]);
                }
                $usernme_verify_array[] = ",";
                foreach ($usernme_verify_array as $k => $v) {
                    if (trim($v) != "") {
                        if (stristr($user_name, $v) !== false) {
                            return array(
                                false,
                                REGISTER_USERNAME_ERROR
                            );
                        }
                    }
                }
            }
        // 密码最小长度
        $min_length = $reg_config['pwd_len'];
        $password_len = strlen(trim($password));
        if ($password_len == 0) {
            return array(
                false,
                REGISTER_PASSWORD_ERROR
            );
        }
        if ($min_length > $password_len) {
            return array(
                false,
                REGISTER_PASSWORD_ERROR
            );
        }
        if (preg_match("/^[\x{4e00}-\x{9fa5}]+$/u", $password)) {
            return array(
                false,
                REGISTER_PASSWORD_ERROR
            );
        }
        // 验证密码内容
        if (trim($reg_config['pwd_complexity']) != "") {
            if (stristr($reg_config['pwd_complexity'], "number") !== false) {
                if (! preg_match("/[0-9]/", $password)) {
                    return array(
                        false,
                        REGISTER_PASSWORD_ERROR
                    );
                }
            }
            if (stristr($reg_config['pwd_complexity'], "letter") !== false) {
                if (! preg_match("/[a-z]/", $password)) {
                    return array(
                        false,
                        REGISTER_PASSWORD_ERROR
                    );
                }
            }
            if (stristr($reg_config['pwd_complexity'], "upper_case") !== false) {
                if (! preg_match("/[A-Z]/", $password)) {
                    return array(
                        false,
                        REGISTER_PASSWORD_ERROR
                    );
                }
            }
            if (stristr($reg_config['pwd_complexity'], "symbol") !== false) {
                if (! preg_match("/[^A-Za-z0-9]/", $password)) {
                    return array(
                        false,
                        REGISTER_PASSWORD_ERROR
                    );
                }
            }
        }
        return array(
            true,
            ''
        );
    }

    /**
     * 判断用户名是否存在
     *
     * @ERROR!!!
     *
     * @see \data\api\IMember::judgeUserNameIsExistence()
     */
    public function judgeUserNameIsExistence($user_name)
    {
        $user = new UserModel();
        $res = $user->getCount([
            "user_name" => $user_name
        ]);
        if ($res > 0) {
            return true;
        } else {
            return false;
        }
    }
    
    public function getCurrUserId(){
        return $this->uid;
    }
    
    /**
     * 账号密码获取用户ID  --Applet
     */
    public function getUidWidthApplet($user_name, $password) {
        $user = new UserModel();
        $res = $user->getInfo([
            "user_name" => $user_name,
            "user_password" => md5($password)
        ],'uid');
        if (!empty($res) && !empty($res['uid'])) {
            return $res['uid'];
        } else {
            return null;
        }
    }
    
    /**
     * 查询会员提现信息各项的总和
     */
    public function getSelectCashWithdrawalSum(){
    	$member_balance_withdraw = new NsMemberBalanceWithdrawModel();
    	$money_array = [];
    	/*提现*/   	
    	//等待处理的
    	$tx_status_0_money = $member_balance_withdraw->getSum(['status'=>0], "transfer_money"); 
    	//已经同意的
    	$tx_status_1_money = $member_balance_withdraw->getSum(['status'=>1], "transfer_money");
    	//已经拒绝的
    	$tx_status_2_money = $member_balance_withdraw->getSum(['status'=>"-1"], "transfer_money");
    	//线下转账
    	$xx_status_2_money = $member_balance_withdraw->getSum(['transfer_type'=>1], "transfer_money");
    	//线上转账
    	$xs_status_2_money = $member_balance_withdraw->getSum(['transfer_type'=>2], "transfer_money");
    	//支付宝转账
    	$zf_status_2_money = $member_balance_withdraw->getSum(['bank_name'=>"支付宝"], "transfer_money");
    	//微信转账
    	$wx_status_2_money = $member_balance_withdraw->getSum(['bank_name'=>"微信"], "transfer_money");		
    	
    	$money_array = [
    			'tx_status_0_money' => $tx_status_0_money,
    			'tx_status_1_money' => $tx_status_1_money,
    			'tx_status_2_money' => $tx_status_2_money,
    			'xx_status_2_money' => $xx_status_2_money,
    			'xs_status_2_money' => $xs_status_2_money,
    			'zfb_status_money' => $zf_status_2_money,
    			'wx_status_money' => $wx_status_2_money
    	];
    	return $money_array;
    }
    
    /**
     * 查询会员余额信息各项的总和
     */
    public function getSelectBalanceSum(){
    	$member_account_records_view = new NsMemberAccountRecordsViewModel();
    	$yue_zong = [];
    	//商城订单支出
    	$shancghen_sum = $member_account_records_view->getSum(['from_type'=>1,"account_type"=>2], "number");
    	//订单退还
    	$order_sum = $member_account_records_view->getSum(['from_type'=>2,"account_type"=>2], "number");
    	//充值
    	$chongzhi_sum = $member_account_records_view->getSum(['from_type'=>4], "number");
    	//调整扣除
    	$tiaozheng_sum_jian = $member_account_records_view->getSum(['from_type'=>10,"number"=>["<",0],"account_type"=>2], "number");
    	//调整增加
    	$tiaozheng_sum_jia = $member_account_records_view->getSum(['from_type'=>10,"number"=>[">",0],"account_type"=>2], "number");
    	$yue_zong = [
    			"shancghen_sum" => -$shancghen_sum,
    			"order_sum" => $order_sum,
    			"chongzhi_sum" => $chongzhi_sum,
    			"tiaozheng_sum_jian" => -$tiaozheng_sum_jian,
    			"tiaozheng_sum_jia" => $tiaozheng_sum_jia
    	];
    	return $yue_zong;
    }
    
    /**
     * 获取平台收入
     */
    public function getShopMoneySum(){
    	
    	$order_payment_sum = new NsOrderPaymentModel();
    	$payMoneySum = [];	
    	//微信收入
    	$wx_money_sum = $order_payment_sum->getSum(['type'=>1,'pay_type'=>1,'pay_status'=>2], "pay_money");
    	//支付宝收入
    	$zfb_money_sum = $order_payment_sum->getSum(['type'=>1,'pay_type'=>2,'pay_status'=>2], "pay_money");
    	
    	$payMoneySum = [
    			'wxmoneysum' => $wx_money_sum,
    			'zfbmoneysum' => $zfb_money_sum
    	];
    	return $payMoneySum;
    }
    
    /**
     * 查询会员积分信息各项的总和
     */
    public function getSelectjifenSum(){
    	$member_account_records_view = new NsMemberAccountRecordsViewModel();
    	$yue_zong = [];
    	//商城订单支出
    	$shancghen_sum = $member_account_records_view->getSum(['from_type'=>1,"account_type"=>1], "number");
    	//订单退还
    	$order_sum = $member_account_records_view->getSum(['from_type'=>2,"account_type"=>1], "number");
    	//兑换
    	$duihuan_sum = $member_account_records_view->getSum(['from_type'=>3,"account_type"=>1], "number");
    	//签到
    	$qiandao_sum = $member_account_records_view->getSum(['from_type'=>5,"account_type"=>1], "number");
    	//分享
    	$fenxiang_sum = $member_account_records_view->getSum(['from_type'=>6,"account_type"=>1], "number");
    	//注册
    	$zhuce_sum = $member_account_records_view->getSum(['from_type'=>7,"account_type"=>1], "number");
    	//点赞
    	$dianzan_sum = $member_account_records_view->getSum(['from_type'=>19,"account_type"=>1], "number");
    	//评论
    	$pinglun_sum = $member_account_records_view->getSum(['from_type'=>20,"account_type"=>1], "number");
    	//营销游戏消耗积分
    	$xiaohao_sum = $member_account_records_view->getSum(['from_type'=>11,"account_type"=>1], "number");
    	//调整扣除
    	$tiaozheng_sum_jian = $member_account_records_view->getSum(['from_type'=>10,"number"=>["<",0],"account_type"=>1], "number");
    	//调整增加
    	$tiaozheng_sum_jia = $member_account_records_view->getSum(['from_type'=>10,"number"=>[">",0],"account_type"=>1], "number");
    	$yue_zong = [
    			"shancghen_sum" => -$shancghen_sum,
    			"order_sum" => $order_sum,
    			"duihuan_sum" => $duihuan_sum,
    			"chongzhi_sum" => $chongzhi_sum,
    			"qiandao_sum" => $qiandao_sum,
    			"fenxiang_sum" => $fenxiang_sum,
    			"zhuce_sum" => $zhuce_sum,
    			"tiaozheng_sum_jian" => -$tiaozheng_sum_jian,
    			"tiaozheng_sum_jia" => $tiaozheng_sum_jia,
    			"dianzan_sum" => $dianzan_sum,
    			"pinglun_sum" => $pinglun_sum,
    			"xiaohao_sum" => $xiaohao_sum
    	];
    	return $yue_zong;
    }
}