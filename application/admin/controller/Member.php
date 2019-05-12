<?php
/**
 * Member.php
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
namespace app\admin\controller;

use data\service\Member as MemberService;
use data\service\UserCard as CardService;
use data\service\User;
use data\service\Weixin;
use data\service\Supplier;
use data\service\Config as WebConfig;
use data\service\Goods as GoodsService;
use data\service\Member\MemberAccount;
use data\service\Config;


/**
 * 会员管理
 *
 * @author Administrator
 *        
 */
class Member extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 会员卡批量生成
     *
     */
    public function batchUserCard(){

        $Card = new CardService();
        $Goods = new NsGoodsModel();
        $code = request()->post('productcode', '');
        $numcount = request()->post('numcount', 0);
        $goods = $Goods->getInfo("code='".$code."'", 'goods_id');
        $res = $Card ->batch_add($goods['goods_id'],$numcount);
        if(!empty($res))
            return AjaxReturn('SUCCESS',$res);
        else
            return AjaxReturn('ADD_FAIL',$res);
    }
    /**
     * 会员卡管理
     *
     */
    public function memberCard(){
        $card = new CardService();
        if (request()->isAjax()) {
            $page_index = request()->post("page_index", 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
            $search_mobile = request()->post('search_mobile', '');
            $level_id = request()->post('levelid', - 1);
            $condition = [
                //'su.is_member' => 1,
                'number' => array(
                    'like',
                    '%' . $search_text . '%'
                ),
                //'su.is_system' => 0
            ];
            if($search_mobile){
                $userInfo = db('sys_user')->where(['user_tel'=>$search_mobile])->find();
                $condition['leader'] = $userInfo['uid'];
            }

            $list = $card->getList($page_index, $page_size, $condition, '');

            return $list;
        }else{
            return view($this->style . 'Member/cardList');

        }


    }
    /**
     * 会员卡分配
     */
    public function memberCardAllot(){
        $Card = new CardService();
        $Goods = new NsGoodsModel();
        $code = request()->post('allotProduct', '');
        $allotMobile = request()->post('allotMobile', '');
        $allotNumCount = request()->post('allotNumCount', '');

        $goods = $Goods->getInfo("code='".$code."'", 'goods_id');
        $userInfo = db('sys_user')->where(['user_tel'=>$allotMobile])->find();

        $res = $Card ->allot_card($goods['goods_id'],$allotNumCount,$userInfo['uid']);
        if(!empty($res))
            return AjaxReturn('SUCCESS',$res);
        else
            return AjaxReturn('ADD_FAIL',$res);
    }
    /**
     * 会员卡数据导出
     */
    public function cardDataExcel(){
        $card = new CardService();
        $xlsName  = "会员卡数据列表";
        $xlsCell  = array(
            array('card_id','编号'),
            array('number','会员卡号'),
            array('goods_code','商品编码'),
            array('leader_nick_name','分配代理商'),
            array('user_nick_name','使用会员'),
            array('add_time','添加时间'),
            array('used_time','使用时间'),
        );
        $search_text = request()->get('search_text', '');
        $search_mobile = request()->get('search_mobile', "");

        $condition = [
            //'su.is_member' => 1,
            'number' => array(
                'like',
                '%' . $search_text . '%'
            ),
            //'su.is_system' => 0
        ];
        if($search_mobile){
            $userInfo = db('sys_user')->where(['user_tel'=>$search_mobile])->find();
            $condition['leader'] = $userInfo['uid'];
        }

        $list = $card->getList(1, 0, $condition, '');

        $list = $list['data'];
        //print_r($list);return;
        foreach($list as $k=>$v){
            $list[$k]['card_id'] = sprintf("%07d\n",$list[$k]['card_id']);
            $list[$k]["add_time"] = date("Y-m-d h:i:s", $v["add_time"]);
            if($v["used_time"]){
                $list[$k]["used_time"] = date("Y-m-d h:i:s", $v["used_time"]);
            }else{
                $list[$k]["used_time"] = "";
            }
            if(empty($v['user_nick_name'])){
                $list[$k]["user_nick_name"] = "";
            }
            if(empty($v['leader_nick_name'])){
                $list[$k]["leader_nick_name"] = "";
            }
        }
//        print_r($list);return;
        dataExcel($xlsName,$xlsCell,$list);
    }
    /**
     * 会员列表主页
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function memberList()
    {
        $member = new MemberService();
        if (request()->isAjax()) {
            $page_index = request()->post("page_index", 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
            $level_id = request()->post('levelid', - 1);

            $start_date = request()->post('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
            $end_date = request()->post('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_date'));
            $status = request()->post('status', -1);
            $condition = [
                'su.is_member' => 1,
                'su.nick_name|su.user_tel|su.user_email' => array(
                    'like',
                    '%' . $search_text . '%'
                ),
                
                'su.is_system' => 0
            ];
            if ($start_date != 0 && $end_date != 0) {
                $condition["su.reg_time"] = [
                    [
                        ">",
                        $start_date
                    ],
                    [
                        "<",
                        $end_date
                    ]
                ];
            } elseif ($start_date != 0 && $end_date == 0) {
                $condition["su.reg_time"] = [
                    [
                        ">",
                        $start_date
                    ]
                ];
            } elseif ($start_date == 0 && $end_date != 0) {
                $condition["su.reg_time"] = [
                    [
                        "<",
                        $end_date
                    ]
                ];
            }
            if ($level_id != - 1) {
                $condition['nml.level_id'] = $level_id;
            }
            if ($status != - 1) {
                $condition['su.user_status'] = $status;
            }
            $list = $member->getMemberList($page_index, $page_size, $condition, 'su.reg_time desc');
            
            return $list;
        } else {
            // 查询会员等级
            $list = $member->getMemberLevelList(1, 0);
            $this->assign('level_list', $list);
            // 会员默认头像
            $config = new Config();
            $defaultImages = $config->getDefaultImages($this->instance_id);
            $this->assign("default_headimg", $defaultImages["value"]["default_headimg"]);//默认用户头像
            
            $version = NS_VERSION; 
            if($version == NS_VER_B2C){
                return view($this->style . 'Member/memberList');
            }else if($version == NS_VER_B2C_FX){
                return view($this->style . 'Member/fx_memberList');
            }
        }
    }

    /**
     * 查询单个 会员
     *
     * @return multitype:unknown
     */
    public function getMemberDetail()
    {
        $user = new User();
        $uid = request()->post("uid", 0);
        $info = $user->getUserInfoDetail($uid);
        return $info;
    }

    /**
     * 修改会员
     */
    public function updateMember()
    {
        if (request()->isAjax()) {
            $member = new MemberService();
            $uid = request()->post('uid', '');
            $user_name = request()->post('user_name', '');
            $member_level = request()->post('level_name', '');
            $password = request()->post('user_password', '');
            $mobile = request()->post('tel', '');
            $email = request()->post('email', '');
            $nick_name = request()->post('nick_name', '');
            $sex = request()->post('sex', '');
            $status = request()->post('status', '');
            $res = $member->updateMemberByAdmin($uid, $user_name, $email, $sex, $status, $mobile, $nick_name, $member_level);
            return AjaxReturn($res);
        }
    }

    /**
     * 修改密码
     */
    public function updateMemberPassword()
    {
        $user = new User();
        $userid = request()->post('uid', '');
        $password = request()->post('user_password', '');
        $result = $user->updateUserInfoByUserid($userid, $password);
        return AjaxReturn($result);
    }

    /**
     * 删除 会员
     *
     * @return multitype:unknown
     */
    public function deleteMember()
    {
        $member = new MemberService();
        $uid = request()->post("uid", 0);
        $res = $member->deleteMember($uid);
        return AjaxReturn($res);
    }

    /**
     * 获取数据库中用户列表
     */
    public function check_username()
    {
        if (request()->isAjax()) {
            // 获取数据库中的用户列表
            $username = request()->get('username','');
            $exist = false;
            $member = new MemberService();
            $exist = $member -> judgeUserNameIsExistence($username);
            return $exist;
        }
    }
    
    /**
     * 判断用户信息是否存在
     * @return boolean
     */
    public function checkUserInfoIsExist(){
        if (request()->isAjax()) {
            $info = request()->post('info', '');
            $type = request()->post('type', '');
            //是否存在
            $exist = false;
            $member = new MemberService();
            
            switch ($type) {
                case "email":
                    $exist = $member -> memberIsEmail($info);
                break;
                case "mobile":
                    $exist = $member -> memberIsMobile($info);
                break;
            }
            return $exist;
        }
    }
    
    /**
     * 添加会员信息
     */
    public function addMember()
    {
        $member = new MemberService();
        $user_name = request()->post('username', '');
        $nick_name = request()->post('nickname', '');
        $password = request()->post('password', '');
        $member_level = request()->post('level_name', '');
        $mobile = request()->post('tel', '');
        $email = request()->post('email', '');
        $sex = request()->post('sex', '');
        $status = request()->post('status', '');
        $retval = $member->addMember($user_name, $password, $email, $sex, $status, $mobile, $member_level);
        $result = $member->updateNickNameByUid($retval, $nick_name);
        return AjaxReturn($retval);
    }

    /**
     * 会员积分明细
     */
    public function pointdetail()
    {
        if (request()->isAjax()) {
            $page_index = request()->post("page_index", 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $member_id = request()->post('member_id', '');
            $search_text = request()->post('search_text');
            $start_date = request()->post('start_date') == "" ? 0 : request()->post('start_date');
            $end_date = request()->post('end_date') == "" ? 0 : request()->post('end_date');
            $condition['nmar.uid'] = $member_id;
            $condition['nmar.account_type'] = 1;
            if($start_date != 0 && $end_date != 0){
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ],
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            }elseif($start_date != 0 && $end_date == 0){
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ]
                ];
            }elseif($start_date == 0 && $end_date != 0){
                $condition["nmar.create_time"] = [
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            }
            $condition['su.nick_name'] = [
                'like',
                '%' . $search_text . '%'
            ];
            
            $member = new MemberService();
            $list = $member->getPointList($page_index, $page_size, $condition, $order = '', $field = '*');
            return $list;
        }
        $member_id = request()->get('member_id','');
        $this->assign('member_id', $member_id);
        return view($this->style . 'Member/pointDetailList');
    }
    
    /**
     * 会员余额明细
     */
    public function balanceDetail()
    {
        $member = new MemberService();
        if (request()->isAjax()) {
    
            $page_index = request()->post("page_index", 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $member_id = request()->post('member_id');
            $search_text = request()->post('search_text');
            $start_date = request()->post('start_date') == "" ? 0 : request()->post('start_date');
            $end_date = request()->post('end_date') == "" ? 0 : request()->post('end_date');
            $from_type = request()->post('from_type');
    
            $condition['nmar.uid'] = $member_id;
            $condition['nmar.account_type'] = 2;
    
            if($start_date != 0 && $end_date != 0){
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ],
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            }elseif($start_date != 0 && $end_date == 0){
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ]
                ];
            }elseif($start_date == 0 && $end_date != 0){
                $condition["nmar.create_time"] = [
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            }
            $condition['nmar.text'] = [
                'like',
                '%' . $search_text . '%'
            ];
    
            if($from_type != ""){
                $condition["nmar.from_type"] = $from_type;
            }
    
            $list = $member->getAccountList($page_index, $page_size, $condition, $order = '', $field = '*');
            return $list;
        }
        $member_id = request()->get('member_id','');
        $this->assign('member_id', $member_id);
    
        // 查询会员详情
        $member_detail = $member->getMemberDetail('', $member_id);
        $this->assign('member_detail', $member_detail);
    
        //会员账户的账户类型列表和来源方式列表
        $from_type_list = MemberAccount::getMemberAccountRecordsNameList();
        $this->assign('from_type_list',$from_type_list);
    
        return view($this->style . 'Member/balanceDetailList');
    }
    
    /**
     * 会员账户明细
     */
    public function accountdetail()
    {
        $member = new MemberService();
        if (request()->isAjax()) {
    
            $page_index = request()->post("page_index", 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $member_id = request()->post('member_id');
            $search_text = request()->post('search_text');
            $start_date = request()->post('start_date') == "" ? 0 : request()->post('start_date');
            $end_date = request()->post('end_date') == "" ? 0 : request()->post('end_date');
            $account_type = request()->post('account_type');
            $from_type = request()->post('from_type');
    
            $condition['nmar.uid'] = $member_id;
    
            if($start_date != 0 && $end_date != 0){
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ],
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            }elseif($start_date != 0 && $end_date == 0){
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ]
                ];
            }elseif($start_date == 0 && $end_date != 0){
                $condition["nmar.create_time"] = [
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            }
            $condition['nmar.text'] = [
                'like',
                '%' . $search_text . '%'
            ];
    
            if($account_type != ""){
                $condition["nmar.account_type"] = $account_type;
            }
    
            if($from_type != ""){
                $condition["nmar.from_type"] = $from_type;
            }
    
            $list = $member->getAccountList($page_index, $page_size, $condition, $order = '', $field = '*');
            return $list;
        }
        $member_id = request()->get('member_id','');
        $this->assign('member_id', $member_id);
    
        // 查询会员详情
        $member_detail = $member->getMemberDetail('', $member_id);
        $this->assign('member_detail', $member_detail);
    
        //会员账户的账户类型列表和来源方式列表
        $account_type_list = MemberAccount::getMemberAccountRecordsTypeNameList();
        $from_type_list = MemberAccount::getMemberAccountRecordsNameList();
        $this->assign('account_type_list',$account_type_list);
        $this->assign('from_type_list',$from_type_list);
    
        return view($this->style . 'Member/accountDetailList');
    }
    /**
     * 会员积分管理
     */
    public function pointlist()
    {
        if (request()->isAjax()) {
            $page_index = request()->post("page_index", 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
            $form_type = request()->post('form_type');
            $start_date = request()->post('start_date') == "" ? 0 : request()->post('start_date');
            $end_date = request()->post('end_date') == "" ? 0 : request()->post('end_date');
            $condition['nmar.account_type'] = 1;
            if ($form_type != '') {
                $condition['nmar.from_type'] = $form_type;
            }
            if($start_date != 0 && $end_date != 0){
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ],
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            }elseif($start_date != 0 && $end_date == 0){
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ]
                ];
            }elseif($start_date == 0 && $end_date != 0){
                $condition["nmar.create_time"] = [
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            }
            $condition['su.nick_name'] = [
                'like',
                '%' . $search_text . '%'
            ];
            
            $member = new MemberService();
            $list = $member->getPointList($page_index, $page_size, $condition, $order = '', $field = '*');
            return $list;
        }else{
            //会员账户的账户类型列表和来源方式列表
            $from_type_list = MemberAccount::getMemberAccountRecordsNameList();
            $member = new MemberService();
            $money_sum = $member->getSelectjifenSum();
            $this->assign("money_sum", $money_sum); 
            $this->assign('from_type_list',$from_type_list);
            return view($this->style . 'Member/pointList');
        }   
    }

    

    /**
     * 会员余额管理
     */
    public function accountlist()
    {
        if (request()->isAjax()) {
            $member = new MemberService();
            $page_index = request()->post("page_index", 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
            $form_type = request()->post('form_type');
            $start_date = request()->post('start_date') == "" ? 0 : request()->post('start_date');
            $end_date = request()->post('end_date') == "" ? 0 : request()->post('end_date');
            
            $condition['nmar.account_type'] = 2;
            $condition['su.nick_name'] = [
                'like',
                '%' . $search_text . '%'
            ];
        if($start_date != 0 && $end_date != 0){
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ],
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            }elseif($start_date != 0 && $end_date == 0){
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ]
                ];
            }elseif($start_date == 0 && $end_date != 0){
                $condition["nmar.create_time"] = [
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            }
            if ($form_type != '') {
                $condition['nmar.from_type'] = $form_type;
            }
            $list = $member->getAccountList($page_index, $page_size, $condition, $order = '', $field = '*');
            return $list;
        }else{
            //会员账户的账户类型列表和来源方式列表
            $from_type_list = MemberAccount::getMemberAccountRecordsNameList();
            $this->assign('from_type_list',$from_type_list);
            $member = new MemberService();
            $money_sum = $member->getSelectBalanceSum();
            $this->assign("money_sum",$money_sum);
            return view($this->style . 'Member/accountList');
        } 
    }

    /**
     * 用户锁定
     */
    public function memberLock()
    {
        $uid = request()->post('id','');
        $retval = 0;
        if (! empty($uid)) {
            $member = new MemberService();
            $retval = $member->userLock($uid);
        }
        return AjaxReturn($retval);
    }

    /**
     * 用户解锁
     */
    public function memberUnlock()
    {
        $uid = request()->post('id','');
        $retval = 0;
        if (! empty($uid)) {
            $member = new MemberService();
            $retval = $member->userUnlock($uid);
        }
        return AjaxReturn($retval);
    }

    /**
     * 粉丝列表
     *
     * @return multitype:number unknown |Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function weixinFansList()
    {
        if (request()->isAjax()) {
            $page_index = request()->post("page_index", 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text','');
            $condition = array(
                'nickname_decode' => array(
                    'like',
                    '%' . $search_text . '%'
                )
            );
            $weixin = new Weixin();
            $list = $weixin->getWeixinFansList($page_index, $page_size, $condition);
            return $list;
        } else {
            return view($this->style . 'Member/weixinFansList');
        }
    }

    /**
     * 积分、余额调整
     */
    public function addMemberAccount()
    {
        $member = new MemberService();
        $uid = request()->post('id','');
        $type = request()->post('type','');
        $num = request()->post('num','');
        $text = request()->post('text','');
        $retval = $member->addMemberAccount(0, $type, $uid, $num, $text);
        return AjaxReturn($retval);
    }

    /**
     * 会员等级列表
     */
    public function memberLevelList()
    {
        $member = new MemberService();
        if (request()->isAjax()) {
            $page_index = request()->post("page_index", 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $list = $member->getMemberLevelList($page_index, $page_size);
            return $list;
        }
        return view($this->style . 'Member/memberLevelList');
    }

    /**
     * 添加会员等级
     */
    public function addMemberLevel()
    {
        $member = new MemberService();
        if (request()->isAjax()) {
            $level_name = request()->post("level_name", '');
            $min_integral = request()->post("min_integral", '');
            $quota = request()->post("quota", '');
            $upgrade = request()->post("upgrade", '');
            $goods_discount = request()->post("goods_discount", '');
            $goods_discount = $goods_discount / 100;
            $desc = request()->post("desc", '');
            $relation = request()->post("relation", '');
            $res = $member->addMemberLevel($this->instance_id, $level_name, $min_integral, $quota, $upgrade, $goods_discount, $desc, $relation);
            return AjaxReturn($res);
        }
        return view($this->style . 'Member/addMemberLevel');
    }

    /**
     * 修改会员等级
     */
    public function updateMemberLevel()
    {
        $member = new MemberService();
        if (request()->isAjax()) {
            $level_id = request()->post("level_id", 0);
            $level_name = request()->post("level_name", '');
            $min_integral = request()->post("min_integral", '');
            $quota = request()->post("quota", '');
            $upgrade = request()->post("upgrade", '');
            $goods_discount = request()->post("goods_discount", '');
            $goods_discount = $goods_discount / 100;
            $desc = request()->post("desc", '');
            $relation = request()->post("relation", '');
            $res = $member->updateMemberLevel($level_id, $this->instance_id, $level_name, $min_integral, $quota, $upgrade, $goods_discount, $desc, $relation);
            return AjaxReturn($res);
        }
        $level_id = request()->get("level_id", 0);
        $info = $member->getMemberLevelDetail($level_id);
        $info['goods_discount'] = $info['goods_discount'] * 100;
        $this->assign('info', $info);
        return view($this->style . 'Member/updateMemberLevel');
    }

    /**
     * 删除 会员等级
     *
     * @return multitype:unknown
     */
    public function deleteMemberLevel()
    {
        $member = new MemberService();
        $level_id = request()->post("level_id", 0);
        $res = $member->deleteMemberLevel($level_id);
        return AjaxReturn($res);
    }

    /**
     * 修改 会员等级 单个字段
     *
     * @return multitype:unknown
     */
    public function modityMemberLevelField()
    {
        $member = new MemberService();
        $level_id = request()->post("level_id", 0);
        $field_name = request()->post("field_name", '');
        $field_value = request()->post("field_value", '');
        $res = $member->modifyMemberLevelField($level_id, $field_name, $field_value);
        return AjaxReturn($res);
    }

    /**
     * 会员提现列表
     */
    public function userCommissionWithdrawList()
    {
        if (request()->isAjax()) {
            $member = new MemberService();
            $pageindex = request()->post('pageIndex','');
            $user_phone = request()->post('user_phone','');
            $user_name = request()->post('user_name','');
            $start_date = request()->post('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
            $end_date = request()->post('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_date'));
            $txclass = request()->post("txclass","");
            //通过会员昵称获取会员id 组装id条件
            	$uid_string = "";
            	$where = array();
            	if ($user_name != "") {
            		$where["nick_name"] = array(
            				"like",
            				"%" . $user_name . "%"
            		);
            	}
            	if (! empty($where)) {
            		$uid_string = $this->getUserUids($where);
            		if ($uid_string != "") {
            			$condition["uid"] = array(
            					"in",
            					$uid_string
            			);
            		} else {
            			$condition["uid"] = 0;
            		}
            	}
            	
            	//手机号搜索条件
            	if ($user_phone != "") {
            		$condition["mobile"] = array(
            				"like",
            				"" . $user_phone . "%"
            		);
            	}
            	
            	//时间搜索条件
            	if ($start_date != 0 && $end_date != 0) {
            		$condition["ask_for_date"] = [
            				[
            						">",
            						$start_date
            				],
            				[
            						"<",
            						$end_date
            				]
            		];
            	} elseif ($start_date != 0 && $end_date == 0) {
            		$condition["ask_for_date"] = [
            				[
            						">",
            						$start_date
            				]
            		];
            	} elseif ($start_date == 0 && $end_date != 0) {
            		$condition["ask_for_date"] = [
            				[
            						"<",
            						$end_date
            				]
            		];
            	}
            	if($txclass == 9){
            		$condition['transfer_type'] =2;
            	}else if($txclass == 8){
            		$condition['transfer_type'] =1;
            	}else if($txclass == 6){
    				$condition['bank_name'] = "支付宝";
	    		}else if($txclass == 7){
	    			$condition['bank_name'] = "微信";
	    		}else{
	    			$condition['status'] = $txclass;
	    		}  
            $condition["shop_id"] = $this->instance_id;
            $list = $member->getMemberBalanceWithdraw($pageindex, PAGESIZE, $condition, 'ask_for_date desc');
            return $list;
        } else {
            $config_service = new WebConfig();
            $data1 = $config_service->getTransferAccountsSetting($this->instance_id, 'wechat');
            $data2 = $config_service->getTransferAccountsSetting($this->instance_id, 'alipay');
            if (! empty($data1)) {
                $wechat = json_decode($data1['value'], true);
            }
            if (! empty($data2)) {
                $alipay = json_decode($data2['value'], true);
            }
            $this->assign("wechat", $wechat);
            $this->assign("alipay", $alipay);
            //获取各项总值
            $member = new MemberService();
			$zong_money = $member->getSelectCashWithdrawalSum();
			$this->assign("zong_money",$zong_money);
            return view($this->style . "Member/userCommissionWithdrawList");
        }
    }

    /**
     * 用户提现审核
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function userCommissionWithdrawAudit()
    {
        $id = request()->post('id','');
        $status = request()->post('status','');
        $transfer_type = request()->post('transfer_type','');
        $transfer_name = request()->post('transfer_name','');
        $transfer_money = request()->post('transfer_money','');
        $transfer_remark = request()->post('transfer_remark','');
        $transfer_no = request()->post('transfer_no','');
        $transfer_account_no = request()->post('transfer_account_no','');
        $type_id = request()->post('type_id','');
        $member = new MemberService();
        $retval = $member->MemberBalanceWithdrawAudit($this->instance_id, $id, $status, $transfer_type, $transfer_name, $transfer_money, $transfer_remark, $transfer_no, $transfer_account_no, $type_id);
        return $retval;
    }

    /**
     * 拒绝提现请求
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function userCommissionWithdrawRefuse()
    {
        $id = request()->post('id','');
        $status = request()->post('status','');
        $remark = request()->post('remark','');
        $member = new MemberService();
        $retval = $member->userCommissionWithdrawRefuse($this->instance_id, $id, $status, $remark);
        return AjaxReturn($retval);
    }

    /**
     * 查寻符合条件的数据并返回id （多个以“,”隔开）
     *
     * @param unknown $condition            
     * @return string
     */
    public function getUserUids($condition)
    {
        $member = new MemberService();
        $list = $member->getMemberAll($condition);
        $uid_string = "";
        foreach ($list as $k => $v) {
            $uid_string = $uid_string . "," . $v["uid"];
        }
        if ($uid_string != "") {
            $uid_string = substr($uid_string, 1);
        }
        return $uid_string;
    }

    /**
     * 获取提现详情
     *
     * @return unknown
     */
    public function getWithdrawalsInfo()
    {
        $id = request()->post('id','');
        $member = new MemberService();
        $retval = $member->getMemberWithdrawalsDetails($id);
        return $retval;
    }
    /**
     * 供货商列表
     */
    public function supplierList(){
        $supplier = new Supplier();
        if(request()->isAjax()){
            $page_index = request()->post("page_index", 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
            $condition['supplier_name'] = array('like', '%'.$search_text.'%');
            $list = $supplier->getSupplierList($page_index, $page_size, $condition);
            return $list;
        }
        return view($this->style.'Member/supplierList');
    }
    /**
     * 添加供货商
     * @return multitype:unknown
     */
    public function addSupplier(){
        $supplier = new Supplier();
        if(request()->isAjax()){
            $uid = request()->post('uid', 0);
            $supplier_name = request()->post('supplier_name', '');
            $linkman_name = request()->post('linkman_name', '');
            $linkman_tel = request()->post('linkman_tel', '');
            $linkman_address = request()->post('linkman_address', '');
            $desc = request()->post('desc', '');
            $res = $supplier->addSupplier($uid, $supplier_name, $linkman_name, $linkman_tel, $linkman_address, $desc);
            return AjaxReturn($res);
        }
        return view($this->style.'Member/addSupplier');
    }
    /**
     * 修改代理商
     * @return multitype:unknown
     */
    public function updateSupplier(){
        $supplier = new Supplier();
        if(request()->isAjax()){
            $supplier_id = request()->post('supplier_id', 0);
            $supplier_name = request()->post('supplier_name', '');
            $linkman_name = request()->post('linkman_name', '');
            $linkman_tel = request()->post('linkman_tel', '');
            $linkman_address = request()->post('linkman_address', '');
            $desc = request()->post('desc', '');
            $res = $supplier->updateSupplier($supplier_id, $supplier_name, $linkman_name, $linkman_tel, $linkman_address,  $desc);
            return AjaxReturn($res);
        }
        $supplier_id = request()->get('supplier_id', 0);
        $info = $supplier->getSupplierInfo($supplier_id);
        $this->assign('supplier_id', $supplier_id);
        $this->assign('info', $info);
        return view($this->style.'Member/updateSupplier');
    }
    /**
     * 删除代理商
     * @return multitype:unknown
     */
    public function deleteSupplier(){
        $supplier = new Supplier();
        $supplier_id_array = request()->post('supplier_id', 0);
        $res = $supplier->deleteSupplier($supplier_id_array);
        return AjaxReturn($res);
    }
    
    /**
     * 订单数据excel导出
     */
    public function memberDataExcel(){
        $xlsName  = "会员数据列表";
        $xlsCell  = array(
            array('user_name','用户名'),
            array('nick_name','昵称'),
            array('user_tel','手机'),
            array('user_email','邮箱'),
            array('level_name','会员等级'),
            array('point','积分'),
            array('balance','账户余额'),
            array('reg_time','注册时间'),
            array('current_login_time','最后登录时间'),
        );
        $search_text = request()->get('search_text', '');
        $level_id = request()->get('levelid', -1);
        $condition = [
            'su.is_member' => 1,
            'su.nick_name|su.user_tel|su.user_email' => array(
                'like',
                '%' . $search_text . '%'
            ),
        
            'su.is_system' => 0
        ];
        if ($level_id != - 1) {
            $condition['nml.level_id'] = $level_id;
        }
        $member = new MemberService();
        $list = $member->getMemberList(1, 0, $condition, 'su.reg_time desc');
        $list = $list["data"];
        foreach($list as $k=>$v){
            $list[$k]["reg_time"] = getTimeStampTurnTime($v["reg_time"]);
            $list[$k]["current_login_time"] = getTimeStampTurnTime($v["current_login_time"]);
        }
        dataExcel($xlsName,$xlsCell,$list);
    }
    
    public function memberExcellist(){
    	$search_text = request()->post('search_text', '');
    	$level_id = request()->post('levelid', -1);
    	
    	$condition = [
    			'su.is_member' => 1,
    			'su.nick_name|su.user_tel|su.user_email' => array(
    					'like',
    					'%' . $search_text . '%'
    			),
    	
    			'su.is_system' => 0
    	];
    	if ($level_id != - 1) {
    		$condition['nml.level_id'] = $level_id;
    	}
    	
    	$member = new MemberService();
    	$list = $member->getMemberList(1, 0, $condition, 'su.reg_time desc');
    	return $list;
    }
    
    /**
     * 更新粉丝信息
     */
    public function updateWchatFansList(){
        $page_index = request()->post("page_index", 0);
        $page_size = 50;
        if($page_index == 0)
        {
            //建立连接，同时获取所有用户openid
            $weixin = new Weixin();
            $openid_list = $weixin -> getWeixinOpenidList();
            
            if(!empty($openid_list['total']))
            {
                $_SESSION['wchat_openid_list'] = $openid_list['openid_list'];
                if ($openid_list['total'] % $page_size == 0) {
                    $page_count = $openid_list['total'] / $page_size;
                } else {
                    $page_count = (int) ($openid_list['total'] / $page_size) + 1;
                }
                return array(
                    'total' => $openid_list['total'],
                    'page_count' => $page_count,
                    'errcode'  => '0',
                    'errorMsg' => ''
                );
            }else{
                return $openid_list;
            }
        }else{
            //对应页数更新用户粉丝信息
           $get_fans_openid_list = $_SESSION['wchat_openid_list'];
           if(!empty($get_fans_openid_list))
           {
               $start = ($page_index -1) * $page_size;
               $page_fans_openid_list = array_slice($get_fans_openid_list, $start, $page_size);
               if(!empty($page_fans_openid_list))
               {
                    $str = '{ "user_list" : [';
                    foreach ($page_fans_openid_list as $key => $value) {
                   	    $str .= ' {"openid" : "'.$value.'"},';
               	    }
                   	$openidlist = substr($str,0,strlen($str)-1);
                   	$openidlist .= ']}';
                    $weixin = new Weixin();
                    $result = $weixin -> UpdateWchatFansList($openidlist);
                    return $result;
               }else{
                   return array(
                        'errcode'  => '0',
                        'errorMsg' => 'success'
                    );
               }
            
           }
        }
    }
    
    /**
     * 获取用户日志列表
     */
    public function userOperationLogList(){
        if(request()->isAjax()){
            $member = new MemberService();
            $start_date = request()->post('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
            $end_date = request()->post('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_date'));
            $page_index = request()->post("page_index", 1);
            $page_size = request()->post("page_size", PAGESIZE);
            $user_name = request()->post("search_text", "");
            
            if ($start_date != 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ],
                    [
                        "<",
                        $end_date
                    ]
                ];
            } elseif ($start_date != 0 && $end_date == 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ]
                ];
            } elseif ($start_date == 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        "<",
                        $end_date
                    ]
                ];
            }
            if(!empty($user_name)){
                $condition["user_name"] = [
                    [
                        "like",
                        "%$user_name%"
                    ]
                ];
            }
            
            $list = $member -> getUserOperationLogList($page_index, $page_size, $condition, "create_time desc");
            return $list;
        }
        return view($this->style. "Member/userOperationLogList");
    }
    
    /**
     * 查看足迹
     */    
    public function newPath()
    {
        $uid = request()->get('member_id','');

        if (request()->post()) {
            $good = new GoodsService();
            $page_index = request()->post("page_index", 1);
            $page_size = request()->post('page_size', PAGESIZE);
            
            $data = request()->post();
            $condition = [];
            $condition["uid"] = $data['uid'];
            if (! empty($data['category_id']))
                $condition['category_id'] = $data['category_id'];
            
            $order = 'create_time desc';

            $list = $good->getGoodsBrowseList($page_index, $page_size, $condition, $order, $field = "*");
            foreach ($list['data'] as $key => $val) {
                $month = ltrim(date('m', $val['create_time']), '0');
                $day = ltrim(date('d', $val['create_time']), '0');
                $val['month'] = $month;
                $val['day'] = $day;
            }
            return $list;
        }
        $this->assign('uid', $uid);
        return view($this->style . "Member/newPath");
    }

    /**
     * 提现记录导出
     */
    public function txDaochu(){
    	
    	$member = new MemberService();
    	$txclass = request()->get("txclass","");
    	$user_name = request()->get('user_name','');
    	$user_phone = request()->get("user_phone","");  	
    	$start_date = request()->get("start_date") == "" ? 0 : getTimeTurnTimeStamp(request()->get("start_date"));
    	$end_date = request()->get("end_date") == "" ? 0 : getTimeTurnTimeStamp(request()->get("end_date"));
    	
    	//通过会员昵称获取会员id 组装id条件
    		$uid_string = "";
    		$where = array();
    		if ($user_name != "") {
    			$where["nick_name"] = array(
    					"like",
    					"%" . $user_name . "%"
    			);
    		}
    		if (! empty($where)) {
    			$uid_string = $this->getUserUids($where);
    			if ($uid_string != "") {
    				$condition["uid"] = array(
    						"in",
    						$uid_string
    				);
    			} else {
    				$condition["uid"] = 0;
    			}
    		}
    		 
    		//手机号搜索条件
    		if ($user_phone != "") {
    			$condition["mobile"] = array(
    					"like",
    					"" . $user_phone . "%"
    			);
    		}
    		 
    		//时间搜索条件
    		if ($start_date != 0 && $end_date != 0) {
    			$condition["ask_for_date"] = [
    					[
    							">",
    							$start_date
    					],
    					[
    							"<",
    							$end_date
    					]
    			];
    		} elseif ($start_date != 0 && $end_date == 0) {
    			$condition["ask_for_date"] = [
    					[
    							">",
    							$start_date
    					]
    			];
    		} elseif ($start_date == 0 && $end_date != 0) {
    			$condition["ask_for_date"] = [
    					[
    							"<",
    							$end_date
    					]
    			];
    		}
    		if($txclass == 9){
    			$condition['transfer_type'] =2;
    		}else if($txclass == 8){
    			$condition['transfer_type'] =1;
    		}else if($txclass == 6){
    			$condition['bank_name'] = "支付宝";
    		}else if($txclass == 7){
    			$condition['bank_name'] = "微信";
    		}else{
    			$condition['status'] = $txclass;
    		}
    	
    	
    	$list = $member->getMemberBalanceWithdraw(1, 0, $condition, 'ask_for_date desc');
    	
    	$xlsName  = "提现数据记录表";
    	$xlsCell  = array(
    			array('real_name','会员账号'),
    			array('mobile','手机号'),
    			array('withdraw_no','提现流水号'),
    			array('bank_name','提现银行'),
    			array('account_number','提现账户'),
    			array('realname','账户姓名'),
    			array('cash','提现金额'),
    			array('status','状态'),
    			array('ask_for_date','提现日期'),
    			array('transfer_type','转账方式')
    	);
    	$list = $list["data"];
    	foreach($list as $k=>$v){
    		if($v['account_number'] == ""){
    			$list[$k]['account_number'] = "";
    		}
    		if($v['status'] == 0){
    			$list[$k]['status'] = "待处理";
    		}else if($v['status'] == 1){
    			$list[$k]['status'] = "已通过";
    		}else{
    			$list[$k]['status'] = "未通过";
    		}
    		$list[$k]['ask_for_date'] = getTimeStampTurnTime($v['ask_for_date']);
    		if($v['transfer_type'] == 1){
    			$list[$k]['transfer_type'] = "线下转账";
    		}else{
    			$list[$k]['transfer_type'] = "线上转账";
    		}
    	}
    	dataExcel($xlsName,$xlsCell,$list);    	    	
    }
    
    /**
     * 余额记录导出
     */
    public function yuedaochu(){
    	
    	$member = new MemberService();
    	$search_text = request()->get('search_text', '');
    	$form_type = request()->get('form_type');
    	$start_date = request()->get('start_date') == "" ? 0 : request()->get('start_date');
    	$end_date = request()->get('end_date') == "" ? 0 : request()->get('end_date');
    	
    	$condition['nmar.account_type'] = 2;
    	$condition['su.nick_name'] = [
    			'like',
    			'%' . $search_text . '%'
    	];
    	if($start_date != 0 && $end_date != 0){
    		$condition["nmar.create_time"] = [
    				[
    						">",
    						getTimeTurnTimeStamp($start_date)
    				],
    				[
    						"<",
    						getTimeTurnTimeStamp($end_date)
    				]
    		];
    	}elseif($start_date != 0 && $end_date == 0){
    		$condition["nmar.create_time"] = [
    				[
    						">",
    						getTimeTurnTimeStamp($start_date)
    				]
    		];
    	}elseif($start_date == 0 && $end_date != 0){
    		$condition["nmar.create_time"] = [
    				[
    						"<",
    						getTimeTurnTimeStamp($end_date)
    				]
    		];
    	}
    	if ($form_type != '') {
    		$condition['nmar.from_type'] = $form_type;
    	}  	
    	$list = $member->getAccountList(1, 0, $condition, $order = '', $field = '*');
    	$xlsName  = "余额数据记录表";
    	$xlsCell  = array(
    			array('nick_name','会员昵称'),
    			array('type_name','类型'),
    			array('text','描述'),
    			array('number','金额'),
    			array('create_time','时间')
    	);
    	$list = $list["data"];
    	foreach($list as $k=>$v){   		
    		$list[$k]['create_time'] = getTimeStampTurnTime($v['create_time']);    		
    	}
    	dataExcel($xlsName,$xlsCell,$list);
    }
    
    /**
     * 会员积分信息导出
     */
    public function jifendaochu(){
    	$search_text = request()->get('search_text', '');
    	$form_type = request()->get('form_type');
    	$start_date = request()->get('start_date') == "" ? 0 : request()->get('start_date');
    	$end_date = request()->get('end_date') == "" ? 0 : request()->get('end_date');
    	$condition['nmar.account_type'] = 1;
    	if ($form_type != '') {
    		$condition['nmar.from_type'] = $form_type;
    	}
    	if($start_date != 0 && $end_date != 0){
    		$condition["nmar.create_time"] = [
    				[
    						">",
    						getTimeTurnTimeStamp($start_date)
    				],
    				[
    						"<",
    						getTimeTurnTimeStamp($end_date)
    				]
    		];
    	}elseif($start_date != 0 && $end_date == 0){
    		$condition["nmar.create_time"] = [
    				[
    						">",
    						getTimeTurnTimeStamp($start_date)
    				]
    		];
    	}elseif($start_date == 0 && $end_date != 0){
    		$condition["nmar.create_time"] = [
    				[
    						"<",
    						getTimeTurnTimeStamp($end_date)
    				]
    		];
    	}
    	$condition['su.nick_name'] = [
    			'like',
    			'%' . $search_text . '%'
    	];
    	
    	$member = new MemberService();
    	$list = $member->getPointList($page_index, $page_size, $condition, $order = '', $field = '*');
    	$xlsName  = "余额数据记录表";
    	$xlsCell  = array(
    			array('nick_name','会员昵称'),
    			array('type_name','类别'),
    			array('text','描述'),
    			array('number','金额'),
    			array('create_time','时间')
    	);
    	$list = $list["data"];
    	foreach($list as $k=>$v){
    		$list[$k]['create_time'] = getTimeStampTurnTime($v['create_time']);
    	}
    	dataExcel($xlsName,$xlsCell,$list);
    }
}