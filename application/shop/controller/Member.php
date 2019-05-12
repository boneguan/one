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
namespace app\shop\controller;

use data\model\AlbumPictureModel;
use data\model\NsCartModel;
use data\model\NsGoodsModel;
use data\model\NsGoodsSkuModel;
use data\model\NsMemberBankAccountModel;
use data\service\Address;
use data\service\Config;
use data\service\Express;
use data\service\Goods as Goods;
use data\service\Member\MemberAccount as MemberAccount;
use data\service\Member as MemberService;
use data\service\Order\Order;
use data\service\Order\OrderGoods;
use data\service\Order as OrderService;
use data\service\promotion\GoodsExpress as GoodsExpressService;
use data\service\promotion\GoodsMansong;
use data\service\Promotion;
use data\service\promotion\GoodsPreference;
use data\service\Shop;
use data\service\UnifyPay;
use data\service\VirtualGoods as VirtualGoodsService;
use think\Session;
use data\service\GroupBuy;
use data\service\Order\OrderGroupBuy;

/**
 * 会员控制器
 * 创建人：李吉
 * 创建时间：2017-02-06 10:59:23
 */
class Member extends BaseController
{

    public $notice;

    public function __construct()
    {
        parent::__construct();
        // 如果没有登录的话让其先登录
        $this->checkLogin();
        // 是否开启验证码
        $web_config = new Config();
        // 是否开启通知
        $instance_id = 0;
        $noticeMobile = $web_config->getNoticeMobileConfig($instance_id);
        $noticeEmail = $web_config->getNoticeEmailConfig($instance_id);
        $this->notice['noticeEmail'] = $noticeEmail[0]['is_use'];
        $this->notice['noticeMobile'] = $noticeMobile[0]['is_use'];
        $this->assign("notice", $this->notice);
        $is_open_virtual_goods = $this->getIsOpenVirtualGoodsConfig($this->instance_id);
        $this->assign("is_open_virtual_goods", $is_open_virtual_goods);
    }

    public function _empty($name)
    {}

    /**
     * 统一调用获取会员详情的方法
     * 创建时间：2018年1月23日11:38:44
     */
    public function getMemberDetail()
    {
        $member_detail = $this->user->getMemberDetail($this->instance_id);
        if ($member_detail['user_info']['birthday'] == 0 || $member_detail['user_info']['birthday'] == "") {
            $member_detail['user_info']['birthday'] = "";
        } else {
            $member_detail['user_info']['birthday'] = date('Y-m-d', $member_detail['user_info']['birthday']);
        }
        if (! empty($member_detail['user_info']['user_headimg'])) {
            $member_img = $member_detail['user_info']['user_headimg'];
        } elseif (! empty($member_detail['user_info']['qq_openid'])) {
            $member_img = $member_detail['user_info']['qq_info_array']['figureurl_qq_1'];
        } elseif (! empty($member_detail['user_info']['wx_openid'])) {
            $member_img = '0';
        } else {
            $member_img = '0';
        }
        // 处理状态信息
        if ($member_detail["user_info"]["user_status"] == 0) {
            $member_detail["user_info"]["user_status"] = "锁定";
        } else {
            $member_detail["user_info"]["user_status"] = "正常";
        }
        return $member_detail;
    }

    /**
     * 检测用户
     */
    private function checkLogin()
    {
        $uid = $this->user->getSessionUid();
        if (empty($uid)) {
            
            $_SESSION['login_pre_url'] = __URL(__URL__ . $_SERVER['PATH_INFO']);
            $redirect = __URL(__URL__ . "/login");
            $this->redirect($redirect);
        }
        $is_member = $this->user->getSessionUserIsMember();
        if (empty($is_member)) {
            $redirect = __URL(__URL__ . "/login");
            $this->redirect($redirect);
        }
    }

    /**
     * 收货地址列表
     * 创建人：任鹏强
     * 创建时间：2017年2月7日12:26:53
     */
    public function addressList()
    {
        $member = new MemberService();
        $page_index = request()->get('page', '1');
        $addresslist = $member->getMemberExpressAddressList(1, 5, '', '');
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        
        $this->assign('page_count', $addresslist['page_count']);
        $this->assign('total_count', $addresslist['total_count']);
        $this->assign('page', $page_index);
        $this->assign('list', $addresslist);
        return view($this->style . "Member/addressList");
    }

    /**
     * 会员地址管理
     * 添加地址
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function addressInsert()
    {
        if (request()->isAjax()) {
            $member = new MemberService();
            $consigner = request()->post('consigner', '');
            $mobile = request()->post('mobile', '');
            $phone = request()->post('phone', '');
            $province = request()->post('province', '');
            $city = request()->post('city', '');
            $district = request()->post('district', '');
            $address = request()->post('address', '');
            $zip_code = request()->post('zip_code', '');
            $alias = request()->post('alias', '');
            $retval = $member->addMemberExpressAddress($consigner, $mobile, $phone, $province, $city, $district, $address, $zip_code, $alias);
            return AjaxReturn($retval);
        } else {
            $member_detail = $this->getMemberDetail();
            $this->assign("member_detail", $member_detail);
            $address_id = request()->get('addressid', 0);
            $this->assign("address_id", $address_id);
            
            return view($this->style . "Member/addressInsert");
        }
    }

    /**
     * 编辑收货地址：
     */
    public function operationAddress()
    {
        $id = request()->post('id', '');
        $consigner = request()->post('consigner', ''); // 收件人
        $mobile = request()->post('mobile', ''); // 电话
        $phone = request()->post('phone', ''); // 固定电话
        $province = request()->post('province', ''); // 省
        $city = request()->post('city', ''); // 市
        $district = request()->post('district', ''); // 区县
        $address = request()->post('address', ''); // 详细地址
        $zip_code = request()->post('zipcode', ''); // 邮编
        $alias = ""; // 城市别名
        $member = new MemberService();
        $res = null;
        if ($id == 0) {
            // 添加
            $res = $member->addMemberExpressAddress($consigner, $mobile, $phone, $province, $city, $district, $address, $zip_code, $alias);
        } else {
            // 修改
            $res = $member->updateMemberExpressAddress($id, $consigner, $mobile, $phone, $province, $city, $district, $address, $zip_code, $alias);
        }
        return AjaxReturn($res);
    }

    /**
     * 获取地址
     */
    public function getMemberExpressAddress()
    {
        $id = request()->post('id', '');
        $member = new MemberService();
        $info = $member->getMemberExpressAddressDetail($id);
        return $info;
    }

    /**
     * 修改会员地址
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >|Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function updateMemberAddress()
    {
        $member = new MemberService();
        if (request()->isAjax()) {
            $id = request()->post('id', '');
            $consigner = request()->post('consigner', '');
            $mobile = request()->post('mobile', '');
            $phone = request()->post('phone', '');
            $province = request()->post('province', '');
            $city = request()->post('city', '');
            $district = request()->post('district', '');
            $address = request()->post('address', '');
            $zip_code = request()->post('zip_code', '');
            $alias = request()->post('alias', '');
            $retval = $member->updateMemberExpressAddress($id, $consigner, $mobile, $phone, $province, $city, $district, $address, $zip_code, $alias);
            return AjaxReturn($retval);
        } else {
            $id = request()->get('id', '');
            $info = $member->getMemberExpressAddressDetail($id);
            if (empty($info)) {
                $this->error("当前地址不存在或者当前会员无权查看");
            }
            $member_detail = $this->getMemberDetail();
            $this->assign("member_detail", $member_detail);
            $this->assign("address_info", $info);
            return view($this->style . "Member/updateMemberAddress");
        }
    }

    /**
     * 获取用户地址详情
     *
     * @return Ambigous <\think\static, multitype:, \think\db\false, PDOStatement, string, \think\Model, \PDOStatement, \think\db\mixed, multitype:a r y s t i n g Q u e \ C l o , \think\db\Query, NULL>
     */
    public function getMemberAddressDetail()
    {
        $address_id = request()->post('id', 0);
        $member = new MemberService();
        $info = $member->getMemberExpressAddressDetail($address_id);
        return $info;
    }

    /**
     * 会员地址删除
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function memberAddressDelete()
    {
        $id = request()->post('id', '');
        $member = new MemberService();
        $res = $member->memberAddressDelete($id);
        return AjaxReturn($res);
    }

    /**
     * 修改会员默认地址
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function updateAddressDefault()
    {
        $id = request()->post('id', '');
        $member = new MemberService();
        $res = $member->updateAddressDefault($id);
        return AjaxReturn($res);
    }

    /**
     * 获取省列表
     */
    public function getProvince()
    {
        $address = new Address();
        $province_list = $address->getProvinceList();
        return $province_list;
    }

    /**
     * 获取城市列表
     *
     * @return Ambigous <multitype:\think\static , \think\false, \think\Collection, \think\db\false, PDOStatement, string, \PDOStatement, \think\db\mixed, boolean, unknown, \think\mixed, multitype:, array>
     */
    public function getCity()
    {
        $address = new Address();
        $province_id = request()->post('province_id', 0);
        $city_list = $address->getCityList($province_id);
        return $city_list;
    }

    /**
     * 获取区域地址
     */
    public function getDistrict()
    {
        $address = new Address();
        $city_id = request()->post('city_id', 0);
        $district_list = $address->getDistrictList($city_id);
        return $district_list;
    }

    /**
     * 获取选择地址
     *
     * @return unknown
     */
    public function getSelectAddress()
    {
        $address = new Address();
        $province_list = $address->getProvinceList();
        $province_id = request()->post('province_id', 0);
        $city_id = request()->post('city_id', 0);
        $city_list = $address->getCityList($province_id);
        $district_list = $address->getDistrictList($city_id);
        $data["province_list"] = $province_list;
        $data["city_list"] = $city_list;
        $data["district_list"] = $district_list;
        return $data;
    }

    /**
     * 我的订单
     * 创建人：任鹏强
     * 创建时间：2017年2月7日12:26:55
     */
    public function orderList($page = 1, $page_size = 10)
    {
        $status = request()->get('status', 'all');
        $condition['order_type'] = array(
            "in",
            "1,3,5"
        ); // 订单类型
        $condition['buyer_id'] = $this->uid;
        $condition["is_deleted"] = 0; // 未删除的订单
        // 查询售后信息
        $condition['is_need_select_customer_info'] == 1;
        
        $orderService = new OrderService();
        // 查询个人用户的订单数量
        $orderStatusNum = $orderService->getOrderStatusNum($condition);
        $this->assign("statusNum", $orderStatusNum);
        // 查询订单状态的数量
        if ($status != 'all') {
            switch ($status) {
                case 0:
                    $condition['order_status'] = 0;
                    break;
                case 1:
                    $condition['order_status'] = 1;
                    // 订单状态为待发货实际为已经支付未完成还未发货的订单
                    $condition['shipping_status'] = 0; // 0 待发货
                    $condition['pay_status'] = 2; // 2 已支付
                    $condition['order_status'] = array(
                        'neq',
                        4
                    ); // 4 已完成
                    $condition['order_status'] = array(
                        'neq',
                        5
                    ); // 5 关闭订单
                    break;
                case 2:
                    $condition['order_status'] = 2;
                    break;
                case 3:
                    $condition['order_status'] = 3;
                    break;
                case 4:
                    $condition['order_status'] = array(
                        'in',
                        '-1,-2'
                    );
                    break;
                case 5:
                    $condition['order_status'] = array(
                        'in',
                        '3,4'
                    );
                    $condition['is_evaluate'] = 0;
                    break;
                default:
                    break;
            }
            if ($condition['order_status'] == array(
                'in',
                '-1,-2'
            )) {
                $orderList = $orderService->getOrderList($page, $page_size, $condition, 'create_time desc');
                foreach ($orderList['data'] as $key => $item) {
                    $order_item_list = array();
                    $order_item_list = $orderList['data'][$key]['order_item_list'];
                    foreach ($order_item_list as $k => $value) {
                        if ($value['refund_status'] == 0 || $value['refund_status'] == - 2) {
                            unset($order_item_list[$k]);
                        }
                    }
                    $orderList['data'][$key]['order_item_list'] = $order_item_list;
                }
            } else {
                $orderList = $orderService->getOrderList($page, $page_size, $condition, 'create_time desc');
            }
        } else {
            
            $orderList = $orderService->getOrderList($page, $page_size, $condition, 'create_time desc');
        }
        $Config = new Config();
        $shop_id = $this->instance_id;
        $shopSet = $Config->getShopConfig($shop_id);
        $shou_array = [];
        $shou_array['shouhou'] = $shopSet['shouhou_day_number'];
        $shou_array['shou_time'] = $shopSet['shouhou_day_number']*24*3600;
        $shou_array['time'] = time();
        $this->assign("shou_array", $shou_array);
        $this->assign("orderList", $orderList['data']);
        $this->assign("page_count", $orderList['page_count']);
        $this->assign("total_count", $orderList['total_count']);
        $this->assign("page", $page);
        $this->assign("status", $status);
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        return view($this->style . 'Member/orderList');
    }
    
    /**
     * 我的预售订单
     * @param number $page
     * @param number $page_size
     */
    public function presellOrderList($page = 1, $page_size = 10){
        $status = request()->get('status', 'all');
        $condition['order_type'] = 6; //预售订单
        
        $condition['buyer_id'] = $this->uid;
        $condition["is_deleted"] = 0; // 未删除的订单
        // 查询售后信息
        $condition['is_need_select_customer_info'] == 1;
        $orderService = new OrderService();
        // 查询个人用户的订单数量
        $orderStatusNum = $orderService->getOrderStatusNum($condition);
        $this->assign("statusNum", $orderStatusNum);
        // 查询订单状态的数量
        if ($status != 'all') {
            switch ($status) {
                case 0:
                    $condition['order_status'] = array('in', '0,6,7');
                    break;
                case 1:
                    $condition['order_status'] = 1;
                    // 订单状态为待发货实际为已经支付未完成还未发货的订单
                    $condition['shipping_status'] = 0; // 0 待发货
                    $condition['pay_status'] = 2; // 2 已支付
                    $condition['order_status'] = array(
                        'neq',
                        4
                    ); // 4 已完成
                    $condition['order_status'] = array(
                        'neq',
                        5
                    ); // 5 关闭订单
                    break;
                case 2:
                    $condition['order_status'] = 2;
                    break;
                case 3:
                    $condition['order_status'] = 3;
                    break;
                case 4:
                    $condition['order_status'] = array(
                    'in',
                    '-1,-2'
                        );
                        break;
                case 5:
                    $condition['order_status'] = array(
                    'in',
                    '3,4'
                        );
                        $condition['is_evaluate'] = 0;
                        break;
                        
                case 6:
                    $condition['order_status'] = array(
                    'in',
                    '6'
                        );
                     break;
                case 7:
                    $condition['order_status'] = array(
                    'in',
                    '7'
                        );
                    break;
                
                default:
                    break;
            }
            if ($condition['order_status'] == array(
                'in',
                '-1,-2'
            )) {
                $orderList = $orderService->getOrderList($page, $page_size, $condition, 'create_time desc');
                foreach ($orderList['data'] as $key => $item) {
                    $order_item_list = array();
                    $order_item_list = $orderList['data'][$key]['order_item_list'];
                    foreach ($order_item_list as $k => $value) {
                        if ($value['refund_status'] == 0 || $value['refund_status'] == - 2) {
                            unset($order_item_list[$k]);
                        }
                    }
                    $orderList['data'][$key]['order_item_list'] = $order_item_list;
                }
            } else {
                $orderList = $orderService->getOrderList($page, $page_size, $condition, 'create_time desc');
            }
        } else {
        
            $orderList = $orderService->getOrderList($page, $page_size, $condition, 'create_time desc');
        }
        $this->assign("orderList", $orderList['data']);
        $this->assign("page_count", $orderList['page_count']);
        $this->assign("total_count", $orderList['total_count']);
        $this->assign("page", $page);
        $this->assign("status", $status);
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        return view($this->style . 'Member/presellOrderList');
    }

    /**
     * 我的虚拟订单
     * 创建人：王永杰
     * 创建时间：2017年11月23日 19:59:21 王永杰
     */
    public function virtualOrderList($page = 1, $page_size = 10)
    {
        if ($this->getIsOpenVirtualGoodsConfig() == 0) {
            $this->error("未开启虚拟商品功能");
        }
        $status = request()->get('status', 'all');
        $condition['order_type'] = 2; // 订单类型（虚拟订单）
        $condition['buyer_id'] = $this->uid;
        $condition["is_deleted"] = 0; // 未删除的订单
        $orderService = new OrderService();
        // 查询个人用户的订单数量
        $orderStatusNum = $orderService->getOrderStatusNum($condition);
        $this->assign("statusNum", $orderStatusNum);
        // 查询订单状态的数量
        
        if ($status == 5) {
            $condition['order_status'] = array(
                'in',
                '3,4'
            );
            $condition['is_evaluate'] = 0;
        }
        if ($status != 'all' && $status != 5) {
            $condition['order_status'] = $status;
            $orderList = $orderService->getOrderList($page, $page_size, $condition, 'create_time desc');
        } else {
            
            $orderList = $orderService->getOrderList($page, $page_size, $condition, 'create_time desc');
        }
        $this->assign("orderList", $orderList['data']);
        $this->assign("page_count", $orderList['page_count']);
        $this->assign("total_count", $orderList['total_count']);
        $this->assign("page", $page);
        $this->assign("status", $status);
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        return view($this->style . 'Member/virtualOrderList');
    }

    /**
     * 我的收藏-->商品收藏
     * 创建人：任鹏强
     * 创建时间：2017年2月7日12:26:58
     */
    public function goodsCollectionList()
    {
        $member = new MemberService();
        $page = request()->get('page', '1');
        $data = array(
            "nmf.fav_type" => 'goods',
            "nmf.uid" => $this->uid
        );
        $goods_collection_list = $member->getMemberGoodsFavoritesList($page, 12, $data);
        $this->assign("goods_collection_list", $goods_collection_list["data"]);
        $this->assign('page', $page);
        $this->assign("goods_list", $goods_collection_list);
        $this->assign('page_count', $goods_collection_list['page_count']);
        $this->assign('total_count', $goods_collection_list['total_count']);
        
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        return view($this->style . 'Member/goodsCollectionList');
    }

    /**
     * 查询右侧边栏的店铺收藏
     * 创建人：王永杰
     * 创建时间：2017年2月27日 10:18:14
     *
     * @return unknown
     */
    public function queryShopOrGoodsCollections()
    {
        $member = new MemberService();
        $type = request()->post("type","");
        $data = array(
            "nmf.fav_type" => $type,
            "nmf.uid" => $this->uid
        );
        $list = null;
        if ($type == "shop") {
            $list = $member->getMemberShopsFavoritesList(1, 50, $data);
        } else {
            $list = $member->getMemberGoodsFavoritesList(1, 50, $data);
        }
        return $list["data"];
    }

    /**
     * 订单详情
     * 创建人：任鹏强
     * 创建时间:2017年2月7日14:49:01
     */
    public function orderDetail()
    {
        $order_id = request()->get('orderid', 0);
        if ($order_id == 0) {
            $this->error("没有获取到订单信息");
        }
        $order_service = new OrderService();
        $order_count = 0;
        $order_count = $order_service->getUserOrderDetailCount($this->uid, $order_id);
        if ($order_count == 0) {
            $this->error("没有获取到订单信息");
        }
        $detail = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            $this->error("没有获取到订单信息");
        }
        $this->assign("order", $detail);
        
        $config = new Config();
        $shopSet = $config->getShopConfig($this->instance_id);
        $this->assign("order_buy_close_time", $shopSet['order_buy_close_time']);
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        
        return view($this->style . 'Member/orderDetail');
    }

    /**
     * 虚拟订单详情
     * 创建人：王永杰
     * 创建时间:2017年11月24日 11:16:15
     */
    public function virtualOrderDetail()
    {
        if ($this->getIsOpenVirtualGoodsConfig() == 0) {
            $this->error("未开启虚拟商品功能");
        }
        $order_id = request()->get('orderid', 0);
        if ($order_id == 0) {
            $this->error("没有获取到订单信息");
        }
        $order_service = new OrderService();
        $order_count = 0;
        $order_count = $order_service->getUserOrderDetailCount($this->uid, $order_id);
        if ($order_count == 0) {
            $this->error("没有获取到订单信息");
        }
        $detail = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            $this->error("没有获取到订单信息");
        }
        $this->assign("order", $detail);
        
        $config = new Config();
        $shopSet = $config->getShopConfig($this->instance_id);
        $this->assign("order_buy_close_time", $shopSet['order_buy_close_time']);
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        
        return view($this->style . 'Member/virtualOrderDetail');
    }

    /**
     * 查询包裹物流信息
     * 2017年6月24日 10:42:34 王永杰
     */
    public function getOrderGoodsExpressMessage()
    {
        $express_id = request()->post("express_id", 0); // 物流包裹id
        $res = - 1;
        if ($express_id) {
            $order_service = new OrderService();
            $res = $order_service->getOrderGoodsExpressMessage($express_id);
        }
        return $res;
    }

    public function index()
    {
        // 可用积分和余额,显示的是用户在店铺中的积分和余额
        $point = 0;
        $member_detail = $this->getMemberDetail();
        $balance = 0;
        $this->assign("member_detail", $member_detail);
        if (! empty($member_detail)) {
            $point = $member_detail['point'];
            $balance = $member_detail['balance'];
        }
        if (isset($point)) {
            $this->assign(array(
                'point' => $point,
                'balance' => $balance
            ));
        } else {
            $this->assign(array(
                'point' => '0',
                'balance' => '0.00'
            ));
        }
        // 优惠券
        $vouchersCount = $this->user->getUserCouponCount(1, $this->instance_id);
        
        $this->assign("vouchersCount", $vouchersCount);
        
        $member = new MemberService();
        // 商品收藏
        $data_goods = array(
            "nmf.fav_type" => "goods",
            "nmf.uid" => $this->uid
        );
        $goods_collection_list = $member->getMemberGoodsFavoritesList(1, 6, $data_goods);
        $this->assign("goods_collection_list", $goods_collection_list["data"]);
        $this->assign("goods_collection_list_count", count($goods_collection_list["data"]));
        
        // 交易提醒 商品列表 商品数量
        $orderService = new OrderService();
        $condition = null;
        $condition['buyer_id'] = $this->uid;
        $condition['order_type'] = 1;
        $order_status_num = $orderService->getOrderStatusNum($condition);
        $condition = null;
        $condition['order_status'] = 0;
        $condition['buyer_id'] = $this->uid;
        $orderList = $orderService->getOrderList(1, 4, $condition, 'create_time desc');
        
        // 用户公告！
        $config = new Config();
        $user_notice = $config->getUserNotice($this->instance_id);
        $this->assign('user_notice', $user_notice);
        
        $this->assign("order_status_num", $order_status_num);
        
        $cart_list = $this->getShoppingCart(); // 购物车列表
        $this->assign("cart_list", $cart_list);
        $this->assign("orderList", $orderList['data']);
        return view($this->style . 'Member/index');
    }

    /**
     * 取消订单
     * 创建人：任鹏强
     * 创建时间：2017年3月3日09:18:35
     */
    public function orderClose()
    {
        $orderService = new OrderService();
        $order_id = request()->post('order_id', '');
        $order = $orderService->orderClose($order_id);
        return AjaxReturn($order);
    }

    /**
     * 获取购物车信息
     * 创建人：王永杰
     * 创建时间：2017年2月15日 14:34:54
     *
     * @ERROR!!!
     *
     * @see \app\shop\controller\BaseController::getShoppingCart()
     */
    public function getShoppingCart()
    {
        $goods = new Goods();
        $cart_list = $goods->getCart($this->uid);
        return $cart_list;
    }

    /**
     * 立即购买
     */
    public function buyNowSession()
    {
        $order_sku_list = isset($_SESSION["order_sku_list"]) ? $_SESSION["order_sku_list"] : "";
        if (empty($order_sku_list)) {
            $redirect = __URL(__URL__ . "/index");
            $this->redirect($redirect);
        }
        
        $cart_list = array();
        $order_sku_list = explode(":", $_SESSION["order_sku_list"]);
        $sku_id = $order_sku_list[0];
        $num = $order_sku_list[1];
        
        // 获取商品sku信息
        $goods_sku = new NsGoodsSkuModel();
        $sku_info = $goods_sku->getInfo([
            'sku_id' => $sku_id
        ], '*');
        
        // 查询当前商品是否有SKU主图
        $order_goods_service = new OrderGoods();
        $picture = $order_goods_service->getSkuPictureBySkuId($sku_info);
        
        // 清除非法错误数据
        $cart = new NsCartModel();
        if (empty($sku_info)) {
            $cart->destroy([
                'buyer_id' => $this->uid,
                'sku_id' => $sku_id
            ]);
            $redirect = __URL(__URL__ . "/index");
            $this->redirect($redirect);
        }
        $goods = new NsGoodsModel();
        $goods_info = $goods->getInfo([
            'goods_id' => $sku_info["goods_id"]
        ], 'max_buy,state,point_exchange_type,point_exchange,picture,goods_id,goods_name,max_use_point,is_open_presell');
        
        $cart_list["stock"] = $sku_info['stock']; // 库存
        $cart_list["sku_name"] = $sku_info["sku_name"];
        
        $goods_preference = new GoodsPreference();
        $member_price = $goods_preference->getGoodsSkuMemberPrice($sku_info['sku_id'], $this->uid);
        $goods_service = new Goods();
        $member_price = $goods_service -> handleMemberPrice($goods_info["goods_id"], $member_price);
        $cart_list["price"] = $member_price < $sku_info['promote_price'] ? $member_price : $sku_info['promote_price'];
        $cart_list["goods_id"] = $goods_info["goods_id"];
        $cart_list["goods_name"] = $goods_info["goods_name"];
        $cart_list["max_buy"] = $goods_info['max_buy']; // 限购数量
        $cart_list['point_exchange_type'] = $goods_info['point_exchange_type']; // 积分兑换类型 0 非积分兑换 1 只能积分兑换
        $cart_list['point_exchange'] = $goods_info['point_exchange']; // 积分兑换
        $cart_list['max_use_point'] = $goods_info['max_use_point'] * $num; //商品最大可用积分
        $cart_list['is_open_presell'] = $goods_info['is_open_presell'];//是否为预售商品
        if ($goods_info['state'] != 1) {
            $redirect = __URL(__URL__ . "/index");
            $this->redirect($redirect);
        }
        $cart_list["num"] = $num;
        // 如果购买的数量超过限购，则取限购数量
        if ($goods_info['max_buy'] != 0 && $goods_info['max_buy'] < $num) {
            $num = $goods_info['max_buy'];
        }
        // 如果购买的数量超过库存，则取库存数量
        if ($sku_info['stock'] < $num) {
            $num = $sku_info['stock'];
        }
        
        // 获取商品阶梯优惠信息
        $goods_service = new Goods();
        $cart_list["price"] = $goods_service->getGoodsLadderPreferentialInfo($goods_info["goods_id"], $num, $cart_list["price"]);
        
        // 获取图片信息，如果该商品有SKU主图，就用。否则用商品主图
        $album_picture_model = new AlbumPictureModel();
        $picture_info = $album_picture_model->get($picture == 0 ? $goods_info['picture'] : $picture);
        $cart_list['picture_info'] = $picture_info;
        
        if (count($cart_list) == 0) {
            $redirect = __URL(__URL__ . "/index");
            $this->redirect($redirect);
        }
        $list[] = $cart_list;
        $goods_sku_list = $sku_id . ":" . $num; // 商品skuid集合
        $res["list"] = $list;
        $res["goods_sku_list"] = $goods_sku_list;
        return $res;
    }

    /**
     * 组合套餐
     */
    public function combination_packagesSession()
    {
        $order_sku = isset($_SESSION["order_sku"]) ? $_SESSION["order_sku"] : "";
        if (empty($order_sku)) {
            $redirect = __URL(__URL__ . "/index");
            $this->redirect($redirect);
        }
        
        $order_sku_array = explode(",", $order_sku);
        foreach ($order_sku_array as $k => $v) {
            
            $cart_list = array();
            $order_sku_list = explode(":", $v);
            $sku_id = $order_sku_list[0];
            $num = $order_sku_list[1];
            
            // 获取商品sku信息
            $goods_sku = new NsGoodsSkuModel();
            $sku_info = $goods_sku->getInfo([
                'sku_id' => $sku_id
            ], '*');
            
            // 查询当前商品是否有SKU主图
            $order_goods_service = new OrderGoods();
            $picture = $order_goods_service->getSkuPictureBySkuId($sku_info);
            
            // 清除非法错误数据
            $cart = new NsCartModel();
            if (empty($sku_info)) {
                $cart->destroy([
                    'buyer_id' => $this->uid,
                    'sku_id' => $sku_id
                ]);
                $redirect = __URL(__URL__ . "/index");
                $this->redirect($redirect);
            }
            
            $goods = new NsGoodsModel();
            $goods_info = $goods->getInfo([
                'goods_id' => $sku_info["goods_id"]
            ], 'max_buy,state,point_exchange_type,point_exchange,picture,goods_id,goods_name,max_use_point');
            
            $cart_list["stock"] = $sku_info['stock']; // 库存
            $cart_list["sku_name"] = $sku_info["sku_name"];
            
            $goods_preference = new GoodsPreference();
            $member_price = $goods_preference->getGoodsSkuMemberPrice($sku_info['sku_id'], $this->uid);
            $cart_list["price"] = $member_price < $sku_info['price'] ? $member_price : $sku_info['price'];
            $cart_list["goods_id"] = $goods_info["goods_id"];
            $cart_list["goods_name"] = $goods_info["goods_name"];
            $cart_list["max_buy"] = $goods_info['max_buy']; // 限购数量
            $cart_list['point_exchange_type'] = $goods_info['point_exchange_type']; // 积分兑换类型 0 非积分兑换 1 只能积分兑换
            $cart_list['point_exchange'] = $goods_info['point_exchange']; // 积分兑换
           
            if ($goods_info['state'] != 1) {
                $redirect = __URL(__URL__ . "/index");
                $this->redirect($redirect);
            }
            $cart_list["num"] = $num;
            // 如果购买的数量超过限购，则取限购数量
            if ($goods_info['max_buy'] != 0 && $goods_info['max_buy'] < $num) {
                $num = $goods_info['max_buy'];
            }
            // 如果购买的数量超过库存，则取库存数量
            if ($sku_info['stock'] < $num) {
                $num = $sku_info['stock'];
            }
            // 获取图片信息，如果该商品有SKU主图，就用。否则用商品主图
            $album_picture_model = new AlbumPictureModel();
            $picture_info = $album_picture_model->get($picture == 0 ? $goods_info['picture'] : $picture);
            $cart_list['picture_info'] = $picture_info;
            
            $cart_list['max_use_point'] = $goods_info['max_use_point'] * $num;
                
            if (count($cart_list) == 0) {
                $redirect = __URL(__URL__ . "/index");
                $this->redirect($redirect);
            }
            $list[] = $cart_list;
            $goods_sku_list = $sku_id . ":" . $num; // 商品skuid集合
            $res["list"] = $list;
        }
        $res["goods_sku_list"] = $order_sku;
        $res["combo_id"] = isset($_SESSION["combo_id"]) ? $_SESSION["combo_id"] : "";
        $res["combo_buy_num"] = isset($_SESSION["combo_buy_num"]) ? $_SESSION["combo_buy_num"] : "";
        return $res;
    }

    /**
     * 加入购物车
     *
     * @return unknown
     */
    public function addShoppingCartSession()
    {
        // 加入购物车
        $cart_list = isset($_SESSION["cart_list"]) ? $_SESSION["cart_list"] : ""; // 用户所选择的商品
        if (empty($cart_list)) {
            $redirect = __URL(__URL__ . "/index");
            $this->redirect($redirect);
        }
        
        $cart_id_arr = explode(",", $cart_list);
        $goods = new Goods();
        $cart_list = $goods->getCartList($cart_list);
        if (count($cart_list) == 0) {
            $redirect = __URL(__URL__ . "/index");
            $this->redirect($redirect);
        }
        $list = Array();
        $str_cart_id = ""; // 购物车id
        $goods_sku_list = ''; // 商品skuid集合
        $goods_preference = new GoodsPreference();
        for ($i = 0; $i < count($cart_list); $i ++) {
            if ($cart_id_arr[$i] == $cart_list[$i]["cart_id"]) {
                $list[] = $cart_list[$i];
                $str_cart_id .= "," . $cart_list[$i]["cart_id"];
                $goods_sku_list .= "," . $cart_list[$i]['sku_id'] . ':' . $cart_list[$i]['num'];
                $member_price = $goods_preference->getGoodsSkuMemberPrice($cart_list[$i]['sku_id'], $this->uid);
                $member_price = $goods -> handleMemberPrice($cart_list[$i]['goods_id'], $member_price);
                $cart_list[$i]["price"] = $member_price < $cart_list[$i]["price"] ? $member_price : $cart_list[$i]["price"];
            }
        }
        $goods_sku_list = substr($goods_sku_list, 1); // 商品sku列表
        $res["list"] = $list;
        $res["goods_sku_list"] = $goods_sku_list;
        return $res;
    }

    /**
     * 购买流程：查看购物车，待付款订单 第一步
     * 创建人：王永杰
     * 创建时间：2017年2月10日 08:49:34
     *
     * @return \think\response\View
     */
    public function paymentOrder()
    { 
        // 判断实物类型：实物商品，虚拟商品
        $order_tag = isset($_SESSION['order_tag']) ? $_SESSION['order_tag'] : "";
        if (empty($order_tag)) {
            $redirect = __URL(__URL__ . "/index");
            $this->redirect($redirect);
        }
        $order_goods_type = isset($_SESSION['order_goods_type']) ? $_SESSION['order_goods_type'] : "";
        $this->assign("order_goods_type", $order_goods_type);
        	
        if ($order_tag == "buy_now" && $order_goods_type === "0") {
            // 虚拟商品
            $order_tag = "virtual_goods";
            $this->virtualOrderInfo();
        } else if ($order_tag == "combination_packages") {
            // 组合套餐
            $this->comboPackageorderInfo();
        } else if ($order_tag == "group_buy") {
            // 团购
            $this->groupBuyOrderInfo();
        } else if ($order_tag == "js_point_exchange") {
            // 积分兑换
            $this->pointExchangeOrderInfo();
        } else {
            // 实物商品
            $this->orderInfo();
        }
        $this->assign("order_tag", $order_tag); // 标识：立即购买还是购物车中进来的
        // 配送时间段
        $config = new Config();
        $distribution_time_out = $config -> getConfig(0, "DISTRIBUTION_TIME_SLOT");
        if(!empty($distribution_time_out["value"])){
            $this->assign("distribution_time_out", json_decode($distribution_time_out["value"], true));
        }else{
            $this->assign("distribution_time_out", "");
        }
        $time = time();
        $this->assign("time", $time);
        
        return view($this->style . 'Member/paymentOrder');
    }
    
    /**
     * 组装本地配送时间说明
     * @return string
     */
    public function getDistributionTime(){
        $config_service = new Config();
        $distribution_time = $config_service->getDistributionTimeConfig($this->instance_id);
        if($distribution_time == 0){
            $time_desc = '';
        }else{
            $time_obj = json_decode($distribution_time['value'],true);
            if($time_obj['morning_start'] != '' && $time_obj['morning_end'] != ''){
                $morning_time_desc = '上午' . $time_obj['morning_start'] . '&nbsp;至&nbsp;' . $time_obj['morning_end'] . '&nbsp;&nbsp;';
            }else{
                $morning_time_desc = '';
            }
            
            if($time_obj['afternoon_start'] != '' && $time_obj['afternoon_end'] != ''){
                $afternoon_time_desc = '下午' . $time_obj['afternoon_start'] . '&nbsp;至&nbsp;' . $time_obj['afternoon_end'];
            }else{
                $afternoon_time_desc = '';
            }
            $time_desc = $morning_time_desc . $afternoon_time_desc;
        }
        return $time_desc;
    }
    
    /**
     * 待付款订单需要的数据
     * 2017年6月28日 15:00:54 王永杰 
     */
    public function orderInfo()
    {
        $member = new MemberService();
        $order = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config = new Config();
        $promotion = new Promotion();
        $shop_service = new Shop();
        $goods_express_service = new GoodsExpressService();
        $goods_preference = new GoodsPreference(); //商品优惠价格操作类
        
        $order_tag = $_SESSION['order_tag'];
        switch ($order_tag) {
            // 立即购买
            case "buy_now":
                $res = $this->buyNowSession();
                break;
            case "cart":
                // 加入购物车
                $res = $this->addShoppingCartSession();
                break;
            case "presell_buy": //预售
                $res = $this->buyNowSession();
                $presell_money = $goods_preference->getGoodsPresell($res["goods_sku_list"]);
                $this->assign('presell_money', $presell_money);
                break;
        }
        $goods_sku_list = $res["goods_sku_list"];
        $list = $res["list"];
       
        $goods_sku_list = trim($goods_sku_list);
        if (empty($goods_sku_list)) {
            $this->error("待支付订单中商品不可为空");
        }
        $this->assign('goods_sku_list', $goods_sku_list); // 商品sku列表
        
        $discount_money = $goods_mansong->getGoodsMansongMoney($goods_sku_list); // 计算优惠金额
        $this->assign("discount_money", sprintf("%.2f", $discount_money)); // 总优惠
        
        $count_money = $order->getGoodsSkuListPrice($goods_sku_list); // 商品金额
        $this->assign("count_money", sprintf("%.2f", $count_money)); // 商品金额
        
        $addresslist = $member->getMemberExpressAddressList(1, 0, '', ' is_default DESC'); // 地址查询
        if (empty($addresslist["data"])) {
            $this->assign("address_list", 0);
        } else {
            $this->assign("address_list", $addresslist["data"]); // 选择收货地址
        }
    
        $max_use_point = $goods_preference->getMaxUsePoint($goods_sku_list);  //最大可使用积分数
        
        $address = $member->getDefaultExpressAddress(); // 查询默认收货地址
        $express = 0;
        $express_company_list = array();
        if (! empty($address)) {
            // 物流公司
            $express_company_list = $goods_express_service->getExpressCompany($this->instance_id, $goods_sku_list, $address['province'], $address['city'], $address['district']);
            if (! empty($express_company_list)) {
                foreach ($express_company_list as $v) {
                    $express = $v['express_fee']; // 取第一个运费，初始化加载运费
                    break;
                }
            }
            $this->assign("address_is_have", 1);
            //本地配送
            $o2o_distribution = $goods_express_service->getGoodsO2oPrice($count_money - $discount_money, 0, $address['province'], $address['city'], $address['district'], 0);

            if($o2o_distribution >= 0)
            {
                $this->assign("o2o_distribution", $o2o_distribution);
                $this->assign("is_open_o2o_distribution", 1);
            
            }else{
                $this->assign("is_open_o2o_distribution", 0);
            }
        } else {
            $this->assign("is_open_o2o_distribution", 0);
            $this->assign("address_is_have", 0);
        }
        $count = $goods_express_service->getExpressCompanyCount($this->instance_id);
        $this->assign("express_company_count", $count); // 物流公司数量
        $this->assign("express", sprintf("%.2f", $express)); // 运费
        $this->assign("express_company_list", $express_company_list); // 物流公司
        
                                                                     // 计算自提点运费
        $pick_up_money = $order->getPickupMoney($count_money);
        if (empty($pick_up_money)) {
            $pick_up_money = 0;
        }
        $this->assign("pick_up_money", $pick_up_money);
        $count_point_exchange = 0; 
        
        
        foreach ($list as $k => $v) {
            $list[$k]['price'] = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
        }
        $this->assign("list", $list); // 格式化后的列表
        $this->assign("count_point_exchange", $count_point_exchange); // 总积分
        
        $shop_id = $this->instance_id;
        $shop_config = $Config->getShopConfig($shop_id);
        $order_invoice_content = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (! empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        
        $this->assign("shop_config", $shop_config); // 后台配置
        
        if($order_tag != "presell_buy"){
            $coupon_list = $order->getMemberCouponList($goods_sku_list); // 获取优惠券
            foreach ($coupon_list as $k => $v) {
            	$coupon_list[$k]['kaishi_time'] = $v['start_time'];
            	$coupon_list[$k]['jieshu_time'] = $v['end_time'];
                $coupon_list[$k]['start_time'] = substr($v['start_time'], 0, stripos($v['start_time'], " ") + 1);
                $coupon_list[$k]['end_time'] = substr($v['end_time'], 0, stripos($v['end_time'], " ") + 1);             
            }
            $this->assign("coupon_list", $coupon_list); // 优惠卷
        }else{
            $this->assign("coupon_list", array()); // 优惠卷
        }
        
        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        if (! empty($address)) {
            $no_mail = checkIdIsinIdArr($address['city'], $promotion_full_mail['no_mail_city_id_array']);
            if ($no_mail) {
                $promotion_full_mail['is_open'] = 0;
            }
        }
        $this->assign("promotion_full_mail", $promotion_full_mail); // 满额包邮
        
        $pickup_point_list = $shop_service->getPickupPointList();
        $pickup_point_list["data"] = $this->pickupPointListSort($address, $pickup_point_list["data"]);
        $this->assign("pickup_point_list", $pickup_point_list); // 自提地址列表
        
        $goods_mansong_gifts = $this->getOrderGoodsMansongGifts($goods_sku_list);
        $this->assign("goods_mansong_gifts", $goods_mansong_gifts); // 赠品列表
        
        $member_account = $this->getMemberAccount($this->instance_id); // 用户余额
        $this->assign("member_account", $member_account); // 用户余额、积分    
        
        $default_use_point = 0; // 默认使用积分数
        if($member_account["point"] >= $max_use_point && $max_use_point != 0){
            $default_use_point = $max_use_point;
        }else{
            $default_use_point = $member_account["point"];
        }
        // 积分配置
        $point_config = $promotion -> getPointConfig();
        if($max_use_point == 0){
            $point_config["is_open"] = 0;
        }
        $this->assign("point_config", $point_config);
        $this->assign("max_use_point", $max_use_point);
        $this->assign("default_use_point", $default_use_point);
        
        //本地配送时间
        $distribution_time = $this->getDistributionTime();
        $this->assign('distribution_time',$distribution_time);
    }

    /**
     * 待付款订单需要的数据 虚拟商品
     * 2017年11月22日 10:07:26 王永杰
     */
    public function virtualOrderInfo()
    {
        if ($this->getIsOpenVirtualGoodsConfig() == 0) {
            $this->error("未开启虚拟商品功能");
        }
        $member = new MemberService();
        $order = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config = new Config();
        $promotion = new Promotion();
        $shop_service = new Shop();
        $goods_preference = new GoodsPreference();
        $shop_id = $this->instance_id;
        $order_tag = $_SESSION['order_tag'];
        $res = $this->buyNowSession();
        $goods_sku_list = $res["goods_sku_list"];
        $list = $res["list"];
        $goods_sku_list = trim($goods_sku_list);
        if (empty($goods_sku_list)) {
            $this->error("待支付订单中商品不可为空");
        }
        $this->assign('goods_sku_list', $goods_sku_list); // 商品sku列表
        
        $discount_money = $goods_mansong->getGoodsMansongMoney($goods_sku_list); // 计算优惠金额
        $this->assign("discount_money", sprintf("%.2f", $discount_money)); // 总优惠
        
        $count_money = $order->getGoodsSkuListPrice($goods_sku_list); // 商品金额
        $this->assign("count_money", sprintf("%.2f", $count_money)); // 商品金额
        $count_point_exchange = 0;
        
        $max_use_point = $goods_preference->getMaxUsePoint($goods_sku_list);  //最大可使用积分数
        
        foreach ($list as $k => $v) {
            $list[$k]['price'] = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
        }
        $this->assign("list", $list); // 格式化后的列表
        $this->assign("count_point_exchange", $count_point_exchange); // 总积分
        
        $shop_config = $Config->getShopConfig($shop_id);
        $order_invoice_content = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (! empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        
        $this->assign("shop_config", $shop_config); // 后台配置
        
        $member_account = $this->getMemberAccount($shop_id); // 用户余额
        $this->assign("member_account", $member_account); // 用户余额、积分
        
        $coupon_list = $order->getMemberCouponList($goods_sku_list); // 获取优惠券
        foreach ($coupon_list as $k => $v) {
        	$coupon_list[$k]['kaishi_time'] = $v['start_time'];
        	$coupon_list[$k]['jieshu_time'] = $v['end_time'];
            $coupon_list[$k]['start_time'] = substr($v['start_time'], 0, stripos($v['start_time'], " ") + 1);
            $coupon_list[$k]['end_time'] = substr($v['end_time'], 0, stripos($v['end_time'], " ") + 1);
        }
        $this->assign("coupon_list", $coupon_list); // 优惠卷
        
        $user_telephone = $this->user->getUserTelephone();
        $this->assign("user_telephone", $user_telephone);
      
        $default_use_point = 0; // 默认使用积分数
        if($member_account["point"] >= $max_use_point && $max_use_point != 0){
            $default_use_point = $max_use_point;
        }else{
            $default_use_point = $member_account["point"];
        }
        // 积分配置
        $point_config = $promotion -> getPointConfig();
        if($max_use_point == 0){
            $point_config["is_open"] = 0;
        }
        $this->assign("point_config", $point_config);
        $this->assign("max_use_point", $max_use_point);
        $this->assign("default_use_point", $default_use_point);
    }

    /**
     * 待付款订单需要的数据 组合套餐
     * 2017年11月22日 10:07:26 王永杰
     */
    public function comboPackageorderInfo()
    {
        $member = new MemberService();
        $order = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config = new Config();
        $promotion = new Promotion();
        $shop_service = new Shop();
        $goods_express_service = new GoodsExpressService();
        $goods_preference = new GoodsPreference();
        $order_tag = $_SESSION['order_tag'];
        $res = $this->combination_packagesSession(); // 获取组合套餐session
                                                     
        // 套餐信息
        $combo_id = $res["combo_id"];
        $combo_detail = $promotion->getComboPackageDetail($combo_id);
        $this->assign("combo_detail", $combo_detail);
        $this->assign("combo_buy_num", $res["combo_buy_num"]);
        
        $goods_sku_list = $res["goods_sku_list"];
        $list = $res["list"];
        
        $goods_sku_list = trim($goods_sku_list);
        if (empty($goods_sku_list)) {
            $this->error("待支付订单中商品不可为空");
        }
        $this->assign('goods_sku_list', $goods_sku_list); // 商品sku列表
        
        // 组合套餐金额
        $combo_package_price = $combo_detail["combo_package_price"] * $res["combo_buy_num"];
        $this->assign("combo_package_price", $combo_package_price);
        
        $addresslist = $member->getMemberExpressAddressList(1, 0, '', ' is_default DESC'); // 地址查询
        if (empty($addresslist["data"])) {
            $this->assign("address_list", 0);
        } else {
            $this->assign("address_list", $addresslist["data"]); // 选择收货地址
        }
        
        $address = $member->getDefaultExpressAddress(); // 查询默认收货地址
        $express = 0;
        $express_company_list = array();
        if (! empty($address)) {
            // 物流公司
            $express_company_list = $goods_express_service->getExpressCompany($this->instance_id, $goods_sku_list, $address['province'], $address['city'], $address['district']);
            if (! empty($express_company_list)) {
                foreach ($express_company_list as $v) {
                    $express = $v['express_fee']; // 取第一个运费，初始化加载运费
                    break;
                }
            }
            $this->assign("address_is_have", 1);
            //本地配送
            $o2o_distribution = $goods_express_service->getGoodsO2oPrice($combo_package_price, 0, $address['province'], $address['city'], $address['district'], 0);
            if($o2o_distribution >= 0)
            {
                $this->assign("o2o_distribution", $o2o_distribution);
                $this->assign("is_open_o2o_distribution", 1);
            }else{
                $this->assign("is_open_o2o_distribution", 0);
            }
        } else {
            $this->assign("is_open_o2o_distribution", 0);
            $this->assign("address_is_have", 0);
        }
        $count = $goods_express_service->getExpressCompanyCount($this->instance_id);
        $this->assign("express_company_count", $count); // 物流公司数量
        $this->assign("express", sprintf("%.2f", $express)); // 运费
        $this->assign("express_company_list", $express_company_list); // 物流公司
        
        $count_money = $order->getComboPackageGoodsSkuListPrice($goods_sku_list); // 商品金额仅作显示
        $this->assign("count_money", sprintf("%.2f", $count_money));
        
        
        $discount_money = $count_money - ($combo_detail["combo_package_price"] * $res["combo_buy_num"]); // 套餐优惠仅作显示不参与计算
        $discount_money = $discount_money < 0 ? 0 : $discount_money;
        $this->assign("discount_money", sprintf("%.2f", $discount_money)); // 总优惠
                                                                           
        // 计算自提点运费
        $pick_up_money = $order->getPickupMoney($combo_package_price);
        if (empty($pick_up_money)) {
            $pick_up_money = 0;
        }
        $this->assign("pick_up_money", $pick_up_money);
        
        $count_point_exchange = 0;
        
        $max_use_point = $goods_preference->getMaxUsePoint($goods_sku_list);  //最大可使用积分数
         
        foreach ($list as $k => $v) {
            $list[$k]['price'] = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
        }
        $this->assign("list", $list); // 格式化后的列表
        
        $shop_id = $this->instance_id;
        $shop_config = $Config->getShopConfig($shop_id);
        $order_invoice_content = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (! empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        
        $this->assign("shop_config", $shop_config); // 后台配置
        
        $member_account = $this->getMemberAccount($this->instance_id); // 用户余额
        $this->assign("member_account", $member_account); // 用户余额、积分
        
        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        if (! empty($address)) {
            $no_mail = checkIdIsinIdArr($address['city'], $promotion_full_mail['no_mail_city_id_array']);
            if ($no_mail) {
                $promotion_full_mail['is_open'] = 0;
            }
        }
        $this->assign("promotion_full_mail", $promotion_full_mail); // 满额包邮
        
        $pickup_point_list = $shop_service->getPickupPointList();
        $pickup_point_list["data"] = $this->pickupPointListSort($address, $pickup_point_list["data"]);
        $this->assign("pickup_point_list", $pickup_point_list); // 自提地址列表
        
        $coupon_list = array();
        $this->assign("coupon_list", $coupon_list); // 优惠卷
        
        //本地配送时间
        $distribution_time = $this->getDistributionTime();
        $this->assign('distribution_time',$distribution_time);
        
        $default_use_point = 0; // 默认使用积分数
        if($member_account["point"] >= $max_use_point && $max_use_point != 0){
            $default_use_point = $max_use_point;
        }else{
            $default_use_point = $member_account["point"];
        }
        // 积分配置
        $point_config = $promotion -> getPointConfig();
        if($max_use_point == 0){
            $point_config["is_open"] = 0;
        }
        $this->assign("point_config", $point_config);
        $this->assign("max_use_point", $max_use_point);
        $this->assign("default_use_point", $default_use_point);
    }
    
    /**
     * 积分兑换商品信息
     */
    public function pointExchangeOrderInfo(){
        $member = new MemberService();
        $order = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config = new Config();
        $promotion = new Promotion();
        $shop_service = new Shop();
        $goods_express_service = new GoodsExpressService();
        $goods_preference = new GoodsPreference(); //商品优惠价格操作类
        
        $order_tag = $_SESSION['order_tag'];
        $res = $this->buyNowSession();
        $goods_sku_list = $res["goods_sku_list"];
        $list = $res["list"];
       
        $goods_sku_list = trim($goods_sku_list);
        if (empty($goods_sku_list)) {
            $this->error("待支付订单中商品不可为空");
        }
        $this->assign('goods_sku_list', $goods_sku_list); // 商品sku列表
        
        // 积分兑换只会有一种商品 只有商品兑换类型为 积分与金额同时购买时才计算优惠和商品金额
        $discount_money = 0;
        $count_money = $order->getGoodsSkuListPrice($goods_sku_list); // 商品金额
        if($list[0]["point_exchange_type"] == 1){
            $discount_money = $goods_mansong->getGoodsMansongMoney($goods_sku_list); // 计算优惠金额 
        }
        $this->assign("discount_money", sprintf("%.2f", $discount_money)); // 总优惠
        $this->assign("count_money", sprintf("%.2f", $count_money)); // 商品金额
        $this->assign("point_exchange_type", $list[0]["point_exchange_type"]); // 积分兑换类型
        
        $addresslist = $member->getMemberExpressAddressList(1, 0, '', ' is_default DESC'); // 地址查询
        if (empty($addresslist["data"])) {
            $this->assign("address_list", 0);
        } else {
            $this->assign("address_list", $addresslist["data"]); // 选择收货地址
        }
        
        $address = $member->getDefaultExpressAddress(); // 查询默认收货地址
        $express = 0;
        $express_company_list = array();
        if (! empty($address)) {
            // 物流公司
            $express_company_list = $goods_express_service->getExpressCompany($this->instance_id, $goods_sku_list, $address['province'], $address['city'], $address['district']);
            if (! empty($express_company_list)) {
                foreach ($express_company_list as $v) {
                    $express = $v['express_fee']; // 取第一个运费，初始化加载运费
                    break;
                }
            }
            $this->assign("address_is_have", 1);
            //本地配送
            $o2o_distribution = $goods_express_service->getGoodsO2oPrice($count_money - $discount_money, 0, $address['province'], $address['city'], $address['district'], 0);
            if($o2o_distribution >= 0)
            {
                $this->assign("o2o_distribution", $o2o_distribution);
                $this->assign("is_open_o2o_distribution", 1);
        
            }else{
                $this->assign("is_open_o2o_distribution", 0);
            }
        } else {
            $this->assign("is_open_o2o_distribution", 0);
            $this->assign("address_is_have", 0);
        }
        $count = $goods_express_service->getExpressCompanyCount($this->instance_id);
        $this->assign("express_company_count", $count); // 物流公司数量
        $this->assign("express", sprintf("%.2f", $express)); // 运费
        $this->assign("express_company_list", $express_company_list); // 物流公司
        
        // 计算自提点运费
        $pick_up_money = $order->getPickupMoney($count_money);
        if (empty($pick_up_money)) {
            $pick_up_money = 0;
        }
        $this->assign("pick_up_money", $pick_up_money);
        
        $count_point_exchange = 0;
        foreach ($list as $k => $v) {
            if($v['point_exchange_type'] == 1){
                $list[$k]['price'] = sprintf("%.2f", $list[$k]['price']);
                $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
            }else{
                $list[$k]['price'] = 0.00;
                $list[$k]['subtotal'] = 0.00;
            }
            $list[$k]['total_point'] = $list[$k]['point_exchange'] * $list[$k]['num'];
            $count_point_exchange += $v["point_exchange"] * $v["num"];
        }
        $this->assign("list", $list); // 格式化后的列表
        $this->assign("count_point_exchange", $count_point_exchange); // 总积分
        
        $shop_id = $this->instance_id;
        $shop_config = $Config->getShopConfig($shop_id);
        $order_invoice_content = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (! empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        $this->assign("shop_config", $shop_config); // 后台配置
        
        $coupon_list = array();
        if($list[0]["point_exchange_type"] == 1){
            $coupon_list = $order->getMemberCouponList($goods_sku_list); // 获取优惠券
            foreach ($coupon_list as $k => $v) {
                $coupon_list[$k]['start_time'] = substr($v['start_time'], 0, stripos($v['start_time'], " ") + 1);
                $coupon_list[$k]['end_time'] = substr($v['end_time'], 0, stripos($v['end_time'], " ") + 1);
            }
        }
        $this->assign("coupon_list", $coupon_list); // 优惠卷
        
        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        if (! empty($address)) {
            $no_mail = checkIdIsinIdArr($address['city'], $promotion_full_mail['no_mail_city_id_array']);
            if ($no_mail) {
                $promotion_full_mail['is_open'] = 0;
            }
        }
        $this->assign("promotion_full_mail", $promotion_full_mail); // 满额包邮
        
        $pickup_point_list = $shop_service->getPickupPointList();
        $pickup_point_list["data"] = $this->pickupPointListSort($address, $pickup_point_list["data"]);
        $this->assign("pickup_point_list", $pickup_point_list); // 自提地址列表
        
        $goods_mansong_gifts = $this->getOrderGoodsMansongGifts($goods_sku_list);
        $this->assign("goods_mansong_gifts", $goods_mansong_gifts); // 赠品列表
        
        $member_account = $this->getMemberAccount($this->instance_id); // 用户余额
        $this->assign("member_account", $member_account); // 用户余额、积分
        
        //本地配送时间
        $distribution_time = $this->getDistributionTime();
        $this->assign('distribution_time',$distribution_time);
    }
    
    /**
     * 立即购买、加入购物车都存入session中，
     *
     * @return number
     */
    public function orderCreateSession()
    {
        $tag = request()->post('tag', '');
        if (empty($tag)) {
            return - 1;
        }
        $_SESSION['order_tag'] = $tag;
        switch ($tag) {
            case 'buy_now':
                
                // 立即购买
                $_SESSION['order_sku_list'] = request()->post('sku_id') . ':' . request()->post('num');
                $_SESSION['order_goods_type'] = request()->post("goods_type", 1); // 商品类型标识
                break;
            case 'cart':
                
                // 加入购物车
                $_SESSION['cart_list'] = request()->post('cart_id');
                $_SESSION['order_goods_type'] = 1; // 商品类型标识
                break;
            case "combination_packages":
                
                // 组合套餐
                $_SESSION['order_sku'] = request()->post("data");
                $_SESSION['combo_id'] = request()->post("combo_id", "");
                $_SESSION['combo_buy_num'] = request()->post("buy_num", "");
                $_SESSION['order_goods_type'] = request()->post("goods_type"); // 商品类型标识
                break;
            case "group_buy":
                // 团购
                $_SESSION['order_sku_list'] = request()->post('sku_id') . ':' . request()->post('num');
                $_SESSION['order_goods_type'] = request()->post("goods_type"); // 商品类型标识
            case "js_point_exchange":
                // 积分兑换
                $_SESSION['order_sku_list'] = request()->post('sku_id') . ':' . request()->post('num');
                $_SESSION['order_goods_type'] = request()->post("goods_type"); // 商品类型标识
                break;
            case "presell_buy":
                //预售
                $_SESSION['order_sku_list'] = request()->post('sku_id') . ':' . request()->post('num');
                $_SESSION['order_goods_type'] = request()->post("goods_type"); /// 商品类型标识
                $_SESSION['goods_id'] = request()->post("goods_id"); 
                break;
        }
        return 1;
    }

    /**
     * 获取用户余额
     * 2017年3月1日 10:50:45
     *
     * @param unknown $shop_id            
     * @return unknown
     */
    public function getMemberAccount($shop_id)
    {
        $member = new MemberService();
        $member_account = $member->getMemberAccount($this->uid, $shop_id);
        return $member_account;
    }

    /**
     * 退款/退货/维修订单列表
     * 创建人：周学勇
     * 创建时间：2017年2月7日 16:13:04
     *
     * @return \think\response\View
     */
    public function backList()
    {
        $orderService = new OrderService();
        $page = request()->get('page', '1');
        // 查询订单状态的数量
        $condition['buyer_id'] = $this->uid;
        $condition['order_type'] = 1;
        $condition['order_status'] = array(
            'in',
            '-1,-2'
        );
        $orderList = $orderService->getOrderList($page, 10, $condition, 'create_time desc');
        
        foreach ($orderList['data'] as $key => $item) {
            $order_item_list = array();
            $order_item_list = $orderList['data'][$key]['order_item_list'];
            foreach ($order_item_list as $k => $value) {
                if ($value['refund_status'] == 0 || $value['refund_status'] == - 2) {
                    unset($order_item_list[$k]);
                }
            }
            $orderList['data'][$key]['order_item_list'] = $order_item_list;
        }
        $this->assign("orderList", $orderList['data']);
        $this->assign("page_count", $orderList['page_count']);
        $this->assign("total_count", $orderList['total_count']);
        $this->assign("page", $page);
        
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        return view($this->style . 'Member/backList');
    }

    /**
     * 取消退款
     * 任鹏强
     * 2017年3月1日15:30:51
     */
    public function cancleOrder()
    {
        if (request()->isAjax()) {
            $orderService = new OrderService();
            $order_id = request()->post('order_id', '');
            $order_goods_id = request()->post('order_goods_id', '');
            $cancle_order = $orderService->orderGoodsCancel($order_id, $order_goods_id);
            return AjaxReturn($cancle_order);
        }
    }

    /**
     * 商品评价/晒单
     * 创建人：周学勇
     * 创建时间：2017年2月7日 16:14:00
     *
     * @return \think\response\View
     */
    public function goodsEvaluationList($page = 1, $page_size = 10)
    {
        $order = new OrderService();
        $condition['uid'] = $this->uid;
        $goodsEvaluationList = $order->getOrderEvaluateDataList($page, $page_size, $condition, 'addtime desc');
        foreach ($goodsEvaluationList['data'] as $k => $v) {
            $goodsEvaluationList['data'][$k]['evaluationImg'] = (empty($v['image'])) ? '' : explode(',', $v['image']);
            
            $goodsEvaluationList['data'][$k]['againEvaluationImg'] = (empty($v['again_image'])) ? '' : explode(',', $v['again_image']);
        }
        
        $this->assign("goodsEvaluationList", $goodsEvaluationList['data']);
        $this->assign("page_count", $goodsEvaluationList['page_count']);
        $this->assign("total_count", $goodsEvaluationList['total_count']);
        $this->assign("page", $page);
        
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        return view($this->style . 'Member/goodsEvaluationList');
    }

    /**
     * 用户信息
     * 创建人:吴奇
     * 创建时间： 2017年2月7日 16:36：00
     */
    public function person()
    {
        $update_info_status = ""; // 修改信息状态 2017年7月10日 10:50:03
        if (request()->post('submit')) {
            $user_name = request()->post('user_name', '');
            $user_qq = request()->post('user_qq', '');
            $real_name = request()->post('real_name', '');
            $sex = request()->post('sex', '');
            $birthday = request()->post('birthday', '');
            $location = request()->post('location', '');
            $birthday = date('Y-m-d', strtotime($birthday));
            // 把从前台显示的内容转变为可以存储到数据库中的数据
            $update_info_status = $this->user->updateMemberInformation($user_name, $user_qq, $real_name, $sex, $birthday, $location, "");
        } elseif (request()->isAjax()) {
            $user_headimg = request()->post("user_headimg", "");
            $res = $this->user->updateMemberInformation("", "", "", "", "", "", $user_headimg);
            return AjaxReturn($res);
        }      
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        return view($this->style . 'Member/personInformation');
    }

    /**
     * 优惠券
     * 创建人:吴奇
     * 创建时间： 2017年2月7日 16:36：00
     */
    public function vouchers()
    {
        // 获取该用户的所有已领取未使用的优惠券列表
        $list = $this->user->getMemberCounponList(1);
        foreach ($list as $list2) {
            $list2["shop_id"] = $this->user->getShopNameByShopId($list2["shop_id"]);
            $list2["state"] = "未使用";
        }
        
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        $this->assign("list", $list);
        return view($this->style . 'Member/vouchers');
    }

    /**
     * 会员积分流水
     * 创建人:吴奇
     * 创建时间：2017年3月1日 17:00
     */
    public function integrallist()
    {
        $shop_id = $this->instance_id;
        $conponAccount = new MemberAccount();
        $start_time = request()->post('start_time', '2016-01-01');
        $end_time = request()->post('end_time', '2099-01-01');
        $page_index = request()->get('page', '1');
        // 每页显示几个
        $page_size = 10;
        $condition['nmar.uid'] = $this->uid;
        $condition['nmar.shop_id'] = $shop_id;
        $condition['nmar.account_type'] = 1;
        // 查看用户在该商铺下的积分消费流水
        $list = $this->user->getAccountList($page_index, $page_size, $condition);
        // $list = $this->user->getPageMemberPointList($start_time, $end_time, $page_index, $page_count, $shop_id);
        foreach ($list["data"] as $list2) {
            // if ($list2["number"] < 0) {
            // $list2["number"] = 0 - $list2["number"];
            // }
            $list2["number"] = (int) $list2["number"];
            $list2["data_id"] = $this->user->getOrderNumber($list2["data_id"])["out_trade_no"];
        }
        // 获取兑换比例
        $account = new MemberAccount();
        $accounts = $account->getConvertRate($shop_id);
        // 查看积分总数
        $account_type = 1;
        
        $conponSum = $conponAccount->getMemberAccount($shop_id, $this->uid, $account_type);
        // 店铺名称
        $shop_name = $this->user->getWebSiteInfo();
        $this->assign([
            'account' => $accounts['convert_rate'],
            "sum" => (int) $conponSum,
            "shopname" => $shop_name['title'],
            "shop_id" => $shop_id,
            'page_count' => $list['page_count'],
            'total_count' => $list['total_count'],
            "balances" => $list,
            'page' => $page_index
        ]);
        
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        return view($this->style . 'Member/integral');
    }

    /**
     * 会员余额流水
     * 创建人:吴奇
     * 创建时间： 2017年3月1日 17:00
     */
    public function balancelist()
    {
        $start_time = request()->post('start_time', '2016-01-01');
        $end_time = request()->post('end_time', '2099-01-01');
        $page_index = request()->get('page', '1');
        $shop_id = $this->instance_id;
        $page_size = 10;
        $condition['nmar.uid'] = $this->uid;
        $condition['nmar.shop_id'] = $shop_id;
        $condition['nmar.account_type'] = 2;
        // 该店铺下的余额流水
        $list = $this->user->getAccountList($page_index, $page_size, $condition);
        // $list = $this->user->getPageMemberBalanceList($start_time, $end_time, $page_index, $page_count, $shop_id);
        // 对获取的数据进行处理
        foreach ($list["data"] as $list2) {
            // if ($list2["number"] < 0) {
            // $list2["number"] = number_format(0 - $list2["number"], 2);
            // }
            $list2["data_id"] = $this->user->getOrderNumber($list2["data_id"])["out_trade_no"];
        }
        // 用户在该店铺的账户余额总数
        $account_type = 2;
        $accountAccount = new MemberAccount();
        $accountSum = $accountAccount->getMemberAccount($shop_id, $this->uid, $account_type);
        $this->assign("sum", number_format($accountSum, 2));
        // 店铺名称
        // $shop_name = $this->user->getShopNameByShopId($shop_id);
        $shop_name = $this->user->getWebSiteInfo();
        // 余额充值
        $pay = new UnifyPay();
        $pay_no = $pay->createOutTradeNo();
        $this->assign("pay_no", $pay_no);
        
        $this->assign("shopname", $shop_name['title']);
        $this->assign('page_count', $list['page_count']);
        $this->assign('total_count', $list['total_count']);
        $this->assign("balances", $list);
        $this->assign('page', $page_index);
        
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        return view($this->style . 'Member/balance');
    }

    /**
     * 提现记录
     */
    public function balanceWithdrawList()
    {
        $page_index = request()->get('page', '1');
        $shop_id = $this->instance_id;
        $page_size = 10;
        $condition['uid'] = $this->uid;
        $condition['shop_id'] = $shop_id;
        /* $condition['status'] = 1; */
        // 该店铺下的余额流水
        $list = $this->user->getMemberBalanceWithdraw($page_index, $page_size, $condition, 'ask_for_date desc');
        foreach ($list['data'] as $k => $v) {
            if ($v['status'] == 1) {
                $list['data'][$k]['status'] = '已同意';
            } elseif ($v['status'] == 0) {
                $list['data'][$k]['status'] = '已申请';
            } else {
                $list['data'][$k]['status'] = '已拒绝';
            }
        }
        // 用户在该店铺的账户余额总数
        $account_type = 2;
        $accountAccount = new MemberAccount();
        $accountSum = $accountAccount->getMemberAccount($shop_id, $this->uid, $account_type);
        $this->assign("sum", number_format($accountSum, 2));
        // 店铺名称
        $shop_name = $this->user->getWebSiteInfo();
        // 余额充值
        $pay = new UnifyPay();
        $pay_no = $pay->createOutTradeNo();
        $this->assign("pay_no", $pay_no);
        
        $this->assign("shopname", $shop_name['title']);
        $this->assign('page_count', $list['page_count']);
        $this->assign('total_count', $list['total_count']);
        $this->assign("balances", $list);
        $this->assign('page', $page_index);
        
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        return view($this->style . 'Member/balanceWithdrawList');
    }

    /**
     * 余额提现
     */
    public function balanceWithdrawals()
    {
        $member = new MemberService();
        if (request()->isAjax()) {
            // 提现
            $uid = $this->uid;
            $withdraw_no = time() . rand(111, 999);
            $bank_account_id = request()->post('bank_id', '');
            $cash = request()->post('cash', '');
            $shop_id = $this->instance_id;
            $retval = $member->addMemberBalanceWithdraw($shop_id, $withdraw_no, $uid, $bank_account_id, $cash);
            return AjaxReturn($retval);
        } else {
            $members = new MemberAccount();
            $config = new Config();
            $account_list = $member->getMemberBankAccount();
            // 获取会员余额
            $uid = $this->uid;
            $shop_id = $this->instance_id;
            $account = $members->getMemberBalance($uid);
            $this->assign('shop_id', $shop_id);
            $this->assign('account', $account);
            $balanceConfig = $config->getBalanceWithdrawConfig($shop_id);
            $this->assign("withdraw_account", $balanceConfig['value']['withdraw_account']);
            $cash = $balanceConfig['value']["withdraw_cash_min"];
            $this->assign('cash', $cash);
            $poundage = $balanceConfig['value']["withdraw_multiple"];
            $this->assign('poundage', $poundage);
            $withdraw_message = $balanceConfig['value']["withdraw_message"];
            $this->assign('withdraw_message', $withdraw_message);
            $this->assign('account_list', $account_list);
            
            $member_detail = $this->getMemberDetail();
            $this->assign("member_detail", $member_detail);
            return view($this->style . "Member/balanceWithdrawals");
        }
    }

    /**
     * 添加银行账户
     */
    public function addAccount()
    {
        $member = new MemberService();
        $uid = $this->uid;
        $realname = request()->post('realname', '');
        $mobile = request()->post('mobile', '');
        $account_type = request()->post('account_type', '1');
        $account_type_name = request()->post('account_type_name', '银行卡');
        $account_number = request()->post('account_number', '');
        $branch_bank_name = request()->post('branch_bank_name', '');
        $retval = $member->addMemberBankAccount($uid, $account_type, $account_type_name, $branch_bank_name, $realname, $account_number, $mobile);
        return AjaxReturn($retval);
    }

    /**
     * 删除账户信息
     */
    public function delAccount()
    {
        if (request()->isAjax()) {
            $member = new MemberService();
            $uid = $this->uid;
            $account_id = request()->post('id', '');
            $retval = $member->delMemberBankAccount($account_id);
            return AjaxReturn($retval);
        }
    }

    /**
     * 获取要修改的银行账户信息
     */
    public function getbankinfo()
    {
        $member = new MemberService();
        $id = request()->post('id', '');
        $result = $member->getMemberBankAccountDetail($id);
        return $result;
    }

    /**
     * 修改会员提现银行账户信息
     */
    public function updateBanckAccount()
    {
        if (request()->isAjax()) {
            $member = new MemberService();
            $account_id = request()->post('id', '');
            $member_bank_account = new NsMemberBankAccountModel();
            $result = $member_bank_account->getCount([
                'uid' => $this->uid,
                'id' => $account_id
            ]);
            if ($result == 0) {
                $retval = - 1;
            }
            
            $account_type = request()->post("account_type", 1);
            $account_type_name = request()->post("account_type_name", "银行卡");
            $realname = request()->post('realname', '');
            $mobile = request()->post('mobile', '');
            $account_number = request()->post('account_number', '');
            $branch_bank_name = request()->post('branch_bank_name', '');
            $retval = $member->updateMemberBankAccount($account_id, $account_type, $account_type_name, $branch_bank_name, $realname, $account_number, $mobile);
            return AjaxReturn($retval);
        }
    }

    /**
     * 创建充值订单
     */
    public function createRechargeOrder()
    {
        $recharge_money = request()->post('recharge_money', 0);
        $out_trade_no = request()->post('out_trade_no', '');
        if (empty($recharge_money) || empty($out_trade_no)) {
            return AjaxReturn(0);
        } else {
            $member = new MemberService();
            $retval = $member->createMemberRecharge($recharge_money, $this->uid, $out_trade_no);
            return AjaxReturn($retval);
        }
    }

    /**
     * 余额积分相互兑换
     * 吴奇
     * 2017/3/1 17:57
     */
    public function exchange()
    {
        $point = request()->post('amount', '');
        $point = (float) $point;
        $shop_id = request()->post('shopid', '');
        $shop_id = intval($shop_id);
        $result = $this->user->memberPointToBalance($this->uid, $shop_id, $point);
        if ($result == 1) {
            $this->assign("shop_id", $shop_id);
            return view($this->style . 'Member/exchangeSuccess');
        }
    }

    /**
     * 退出登录
     * 吴奇
     * 2017/2/15 16:08
     */
    public function logOut()
    {
        $member = new MemberService();
        $member->Logout();
        return AjaxReturn(1);
    }

    /**
     * 账号安全
     */
    public function userSecurity()
    {
        if (request()->isGet()) {
            $atc = request()->get('atc', '');
            $this->assign('atc', $atc);
        }
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        return view($this->style . "Member/userSecurity");
    }

    /**
     * 吴奇
     * 商品评价
     * 2017/2/16 16:08
     */
    public function reviewCommodity()
    {
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        // 先考虑显示的样式
        if (request()->isGet()) {
            $order_id = request()->get('orderid', '');
            // 判断该订单是否是属于该用户的
            $order_service = new OrderService();
            $condition['order_id'] = $order_id;
            $condition['buyer_id'] = $this->uid;
            $condition['review_status'] = 0;
            $condition['order_status'] = array(
                'in',
                '3,4'
            );
            $order_count = $order_service->getUserOrderCountByCondition($condition);
            if ($order_count == 0) {
                $this->error("对不起,您无权进行此操作");
            }
            
            $order = new Order();
            $list = $order->getOrderGoods($order_id);
            $orderDetail = $order->getDetail($order_id);
            $this->assign("order_no", $orderDetail['order_no']);
            $this->assign("order_id", $order_id);
            $this->assign("list", $list);
            return view($this->style . 'Member/reviewCommodity');
            if (($orderDetail['order_status'] == 3 || $orderDetail['order_status'] == 4) && $orderDetail['is_evaluate'] == 0) {} else {
                $redirect = __URL(__URL__ . "/member/index");
                $this->redirect($redirect);
            }
        } else {
            return view($this->style . "Member/orderList");
        }
    }

    /**
     * 追评
     * 李吉
     * 2017-02-17 14:12:15
     */
    public function reviewAgain()
    {
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        // 先考虑显示的样式
        if (request()->isGet()) {
            $order_id = request()->get('orderid', '');
            // 判断该订单是否是属于该用户的
            $order_service = new OrderService();
            $condition['order_id'] = $order_id;
            $condition['buyer_id'] = $this->uid;
            $condition['is_evaluate'] = 1;
            $order_count = $order_service->getUserOrderCountByCondition($condition);
            if ($order_count == 0) {
                $this->error("对不起,您无权进行此操作");
            }
            
            $order = new Order();
            $list = $order->getOrderGoods($order_id);
            $orderDetail = $order->getDetail($order_id);
            $this->assign("order_no", $orderDetail['order_no']);
            $this->assign("order_id", $order_id);
            $this->assign("list", $list);
            if (($orderDetail['order_status'] == 3 || $orderDetail['order_status'] == 4) && $orderDetail['is_evaluate'] == 1) {
                return view($this->style . 'Member/reviewAgain');
            } else {
                
                $redirect = __URL(__URL__ . "/member/index");
                $this->redirect($redirect);
            }
        } else {
            return view($this->style . "Member/orderList");
        }
    }

    /**
     * 增加商品评价
     */
    public function modityCommodity()
    {
        return 1;
    }

    /**
     * 功能：绑定手机
     * 创建人：李志伟
     * 创建时间：2017年2月16日17:17:43
     */
    public function modifyMobile()
    {
        $member = new MemberService();
        if (request()->isAjax()) {
            $uid = $this->user->getSessionUid();
            $mobile = request()->post('mobile', '');
            $mobile_code = request()->post('mobile_code', '');
            if ($this->notice['noticeMobile'] == 1) {
                $verification_code = Session::get('mobileVerificationCode');
                if ($mobile_code == $verification_code && ! empty($verification_code)) {
                    $retval = $member->modifyMobile($uid, $mobile);
                    if ($retval == 1)
                        Session::delete('mobileVerificationCode');
                    return AjaxReturn($retval);
                } else {
                    return array(
                        'code' => 0,
                        'message' => '手机验证码输入错误'
                    );
                }
            } else {
                // 获取手机是否被绑定
                $is_bin_mobile = $member->memberIsMobile($mobile);
                if ($is_bin_mobile) {
                    return array(
                        'code' => 0,
                        'message' => '该手机号已存在'
                    );
                } else {
                    $retval = $member->modifyMobile($uid, $mobile);
                    return AjaxReturn($retval);
                }
            }
        }
    }

    /**
     * 功能：绑定邮箱
     * 创建人：李志伟
     * 创建时间：2017年2月16日17:17:43
     */
    public function modifyEmail()
    {
        $member = new MemberService();
        $uid = $this->user->getSessionUid();
        $email = request()->post('email', '');
        $email_code = request()->post('email_code', '');
        if ($this->notice['noticeEmail'] == 1) {
            $verification_code = Session::get('emailVerificationCode');
            if ($email_code == $verification_code && ! empty($verification_code)) {
                $retval = $member->modifyEmail($uid, $email);
                if ($retval == 1)
                    Session::delete('emailVerificationCode');
                return AjaxReturn($retval);
            } else {
                return array(
                    'code' => 0,
                    'message' => '邮箱验证码输入错误'
                );
            }
        } else {
            // 获取邮箱是否被绑定
            $is_bin_email = $member->memberIsEmail($email);
            if ($is_bin_email) {
                return array(
                    'code' => 0,
                    'message' => '该邮箱已存在'
                );
            } else {
                $retval = $member->modifyEmail($uid, $email);
                return AjaxReturn($retval);
            }
        }
    }

    /**
     * 功能：修改密码
     * 创建人：李志伟
     * 创建时间：2017年2月16日17:58:06
     */
    public function modifyPassword()
    {
        $member = new MemberService();
        $uid = $this->user->getSessionUid();
        $old_password = request()->post('old_password', '');
        $new_password = request()->post('new_password', '');
        $retval = $member->ModifyUserPassword($uid, $old_password, $new_password);
        return AjaxReturn($retval);
    }

    /**
     * 申请退款
     *
     * @return \think\response\View
     */
    public function refundDetail()
    {
        $order_goods_id = request()->get('order_goods_id', 0);
        if (! is_numeric($order_goods_id) || $order_goods_id == 0) {
            $this->error("没有获取到退款信息");
        }
        $order_service = new OrderService();
        $detail = $order_service->getOrderGoodsRefundInfo($order_goods_id);
        $this->assign("detail", $detail);
        
        $condition['order_goods_id'] = $order_goods_id;
        $condition['buyer_id'] = $this->uid;
        $count = $order_service->getUserOrderGoodsCountByCondition($condition);
        if ($count == 0) {
            $this->error("对不起,您无权进行此操作");
        }
        
        // 实际可退款金额
        $refund_money = $order_service->orderGoodsRefundMoney($order_goods_id);

        $this->assign('refund_money', sprintf("%.2f", $refund_money));
        
        // 余额退款
        $order_goods_service = new OrderGoods();
        $refund_balance = $order_goods_service->orderGoodsRefundBalance($order_goods_id);
        $this->assign("refund_balance", sprintf("%.2f", $refund_balance));
        
        // 查询店铺默认物流地址
        $express = new Express();
        $address = $express->getDefaultShopExpressAddress($this->instance_id);
        $this->assign("address_info", $address);
        // 查询商家地址
        $shop_info = $order_service->getShopReturnSet($this->instance_id);
        $this->assign("shop_info", $shop_info);
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        // 查询订单所退运费
        $freight = $order_service -> getOrderRefundFreight($order_goods_id);
        $this->assign("freight", $freight);
        
        return view($this->style . "Member/refundDetail");
    }

    /**
     * 申请退款
     */
    public function orderGoodsRefundAskfor()
    {
        $order_id = request()->post('order_id', 0);
        $order_goods_id = request()->post('order_goods_id', 0);
        $refund_type = request()->post('refund_type', 1);
        $refund_require_money = request()->post('refund_require_money', 0);
        $refund_reason = request()->post('refund_reason', '');
        $order_service = new OrderService();
        $retval = $order_service->orderGoodsRefundAskfor($order_id, $order_goods_id, $refund_type, $refund_require_money, $refund_reason);
        return AjaxReturn($retval);
    }

    /**
     * 买家退货
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function orderGoodsRefundExpress()
    {
        $order_id = request()->post('order_id', 0);
        $order_goods_id = request()->post('order_goods_id', 0);
        $refund_express_company = request()->post('refund_express_company', '');
        $refund_shipping_no = request()->post('refund_shipping_no', 0);
        $refund_reason = request()->post('refund_reason', '');
        $order_service = new OrderService();
        $retval = $order_service->orderGoodsReturnGoods($order_id, $order_goods_id, $refund_express_company, $refund_shipping_no);
        return AjaxReturn($retval);
    }

    /**
     * 设置用户支付密码
     */
    public function setUserPaymentPassword()
    {
        if (request()->isAjax()) {
            $uid = $this->uid;
            $payment_password = request()->post("payment_password", '');
            $member = new MemberService();
            $res = $member->setUserPaymentPassword($uid, $payment_password);
            return AjaxReturn($res);
        }
    }

    /**
     * 修改用户支付密码
     */
    public function updateUserPaymentPassword()
    {
        if (request()->isAjax()) {
            $uid = $this->uid;
            $old_payment_password = request()->post("old_payment_password", '');
            $new_payment_password = request()->post("new_payment_password", '');
            $member = new MemberService();
            $res = $member->updateUserPaymentPassword($uid, $old_payment_password, $new_payment_password);
            return AjaxReturn($res);
        }
    }

    /**
     * 验证码
     *
     * @return multitype:number string
     */
    public function vertify()
    {
        $vertification = request()->post('vertification', '');
        if (! captcha_check($vertification)) {
            $retval = [
                'code' => 0,
                'message' => "验证码错误"
            ];
        } else {
            $retval = [
                'code' => 1,
                'message' => "验证码正确"
            ];
        }
        return $retval;
    }

    /**
     * 我的足迹
     */
    public function newMyPath()
    {
        if (request()->post()) {
            
            $good = new Goods();
            $data = request()->post();
            
            $condition = [];
            $condition["uid"] = $this->uid;
            if (! empty($data['category_id']))
                $condition['category_id'] = $data['category_id'];
            
            $order = 'create_time desc';
            $list = $good->getGoodsBrowseList(1, 0, $condition, $order, $field = "*");
            foreach ($list['data'] as $key => $val) {
                $month = ltrim(date('m', $val['create_time']), '0');
                $day = ltrim(date('d', $val['create_time']), '0');
                $val['month'] = $month;
                $val['day'] = $day;
            }
            return $list;
        }
        
        return view($this->style . "Member/newMyPath");
    }

    /**
     * 删除我的足迹
     */
    public function delMyPath()
    {
        $type = request()->post('type');
        $value = request()->post('value');
        
        if ($type == 'browse_id')
            $condition['browse_id'] = $value;
        
        $good = new Goods();
        $res = $good->deleteGoodsBrowse($condition);
        
        return AjaxReturn($res);
    }

    /**
     * 获取虚拟商品列表
     */
    public function virtualGoodsList()
    {
        $virtualGoods = new VirtualGoodsService();
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $condition['nvg.buyer_id'] = $this->uid;
        $order = "";
        $virtual_list = $virtualGoods->getVirtualGoodsList($page_index, $page_size, $condition, $order);
        $this->assign("virtualList", $virtual_list['data']);
        $this->assign("total_count", $virtual_list['total_count']);
        
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        return view($this->style . "Member/virtualGoodsList");
    }

    /**
     * 获取订单商品满就送赠品，重复赠品累加数量
     * 赠品必须有库存
     * 创建时间：2018年1月24日12:34:10 王永杰
     */
    public function getOrderGoodsMansongGifts($goods_sku_list)
    {
        $res = array();
        $gift_id_arr = array();
        $goods_mansong = new GoodsMansong();
        $mansong_array = $goods_mansong->getGoodsSkuListMansong($goods_sku_list);
        if (! empty($mansong_array)) {
            foreach ($mansong_array as $k => $v) {
                foreach ($v['discount_detail'] as $discount_k => $discount_v) {
                    $v = $discount_v[0]['gift_id'];
                    if ($v > 0) {
                        array_push($gift_id_arr, $v);
                        break;
                    }
                }
            }
        }
        // 统计每个赠品的数量
        $statistical = array_count_values($gift_id_arr);
        $promotion = new Promotion();
        foreach ($statistical as $k => $v) {
            $detail = $promotion->getPromotionGiftDetail($k);
            if ($detail['gift_goods']['stock'] > 0) {
                $detail['count'] = $v;
                array_push($res, $detail);
            }
        }
        return $res;
    }
    
    
    
    /**
     * 待付款订单需要的数据 团购
     * 2017年11月22日 10:07:26 王永杰
     */
    public function groupBuyOrderInfo()
    {
        $member = new MemberService();
        $order = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config = new Config();
        $promotion = new Promotion();
        $shop_service = new Shop();
        $goods_express_service = new GoodsExpressService();
        $order_tag = $_SESSION['order_tag'];
        $res = $this->groupBuySession(); // 获取团购session
        $goods_sku_list = $res["goods_sku_list"];
        $list = $res["list"];
        
        $goods_sku_list = trim($goods_sku_list);
        if (empty($goods_sku_list)) {
            $this->error("待支付订单中商品不可为空");
        }
        $this->assign('goods_sku_list', $goods_sku_list); // 商品sku列表
        
        //团购总计算
        $group_buy_order = new OrderGroupBuy();
        $count_money = $group_buy_order->getGoodsSkuGroupBuyPrice($goods_sku_list); // 商品金额
        
        if($count_money === false){
            $this->error("待支付订单中商品不可为空");
        }
        $this->assign("count_money", sprintf("%.2f", $count_money));
        
        $addresslist = $member->getMemberExpressAddressList(1, 0, '', ' is_default DESC'); // 地址查询
        if (empty($addresslist["data"])) {
            $this->assign("address_list", 0);
        } else {
            $this->assign("address_list", $addresslist["data"]); // 选择收货地址
        }
        
        $address = $member->getDefaultExpressAddress(); // 查询默认收货地址
        $express = 0;
        $express_company_list = array();
        if (! empty($address)) {
            // 物流公司
            $express_company_list = $goods_express_service->getExpressCompany($this->instance_id, $goods_sku_list, $address['province'], $address['city'], $address['district']);
            if (! empty($express_company_list)) {
                foreach ($express_company_list as $v) {
                    $express = $v['express_fee']; // 取第一个运费，初始化加载运费
                    break;
                }
            }
            $this->assign("address_is_have", 1);
            //本地配送
            $o2o_distribution = $goods_express_service->getGoodsO2oPrice($count_money, 0, $address['province'], $address['city'], $address['district'], 0);
            if($o2o_distribution >= 0)
            {
                $this->assign("o2o_distribution", $o2o_distribution);
                $this->assign("is_open_o2o_distribution", 1);
            }else{
                $this->assign("is_open_o2o_distribution", 0);
            }
        } else {
            $this->assign("address_is_have", 0);
            $this->assign("is_open_o2o_distribution", 0);
        }

        $count = $goods_express_service->getExpressCompanyCount($this->instance_id);
        $this->assign("express_company_count", $count); // 物流公司数量
        $this->assign("express", sprintf("%.2f", $express)); // 运费
        $this->assign("express_company_list", $express_company_list); // 物流公司
        
        $this->assign("discount_money", sprintf("%.2f", 0)); // 总优惠
        
        $pick_up_money = $order->getPickupMoney($count_money);
        if (empty($pick_up_money)) {
            $pick_up_money = 0;
        }
        $this->assign("pick_up_money", $pick_up_money);
        $count_point_exchange = 0;
        $max_use_point = 0;
        foreach ($list as $k => $v) {
            $list[$k]['price'] = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
            $max_use_point += $v["max_use_point"];
        }
        $this->assign("list", $list); // 格式化后的列表
        $this->assign("count_point_exchange", $count_point_exchange); // 总积分
        
        $shop_id = $this->instance_id;
        $shop_config = $Config->getShopConfig($shop_id);
        $order_invoice_content = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (! empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        
        $this->assign("shop_config", $shop_config); // 后台配置
        
        $member_account = $this->getMemberAccount($this->instance_id); // 用户余额
        $this->assign("member_account", $member_account); // 用户余额、积分
        
        $coupon_list = array(); // 获取优惠券 团购不可使用优惠券
        $this->assign("coupon_list", $coupon_list); // 优惠卷
        
        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        if (! empty($address)) {
            $no_mail = checkIdIsinIdArr($address['city'], $promotion_full_mail['no_mail_city_id_array']);
            if ($no_mail) {
                $promotion_full_mail['is_open'] = 0;
            }
        }
        $this->assign("promotion_full_mail", $promotion_full_mail); // 满额包邮
        
        $pickup_point_list = $shop_service->getPickupPointList();
        $pickup_point_list["data"] = $this->pickupPointListSort($address, $pickup_point_list["data"]);
        $this->assign("pickup_point_list", $pickup_point_list); // 自提地址列表
        
        $goods_mansong_gifts = array();
        $this->assign("goods_mansong_gifts", $goods_mansong_gifts); // 赠品列表
        
        //本地配送时间
        $distribution_time = $this->getDistributionTime();
        $this->assign('distribution_time',$distribution_time);
        
        $default_use_point = 0; // 默认使用积分数
        if($member_account["point"] >= $max_use_point && $max_use_point != 0){
            $default_use_point = $max_use_point;
        }else{
            $default_use_point = $member_account["point"];
        }
        // 积分配置
        $point_config = $promotion -> getPointConfig();
        if($max_use_point == 0){
            $point_config["is_open"] = 0;
        }
        $this->assign("point_config", $point_config);
        $this->assign("max_use_point", $max_use_point);
        $this->assign("default_use_point", $default_use_point);
    }
    /**
     * 团购商品信息
     */
    public function groupBuySession()
    {
        $order_sku_list = isset($_SESSION["order_sku_list"]) ? $_SESSION["order_sku_list"] : "";
        if (empty($order_sku_list)) {
            $redirect = __URL(__URL__ . "/index");
            $this->redirect($redirect);
        }
        
        $cart_list = array();
        $order_sku_list = explode(":", $_SESSION["order_sku_list"]);
        $sku_id = $order_sku_list[0];
        $num = $order_sku_list[1];
        
        // 获取商品sku信息
        $goods_sku = new NsGoodsSkuModel();
        $sku_info = $goods_sku->getInfo([
            'sku_id' => $sku_id
        ], '*');
        
        // 查询当前商品是否有SKU主图
        $order_goods_service = new OrderGoods();
        $picture = $order_goods_service->getSkuPictureBySkuId($sku_info);
        
        // 清除非法错误数据
        $cart = new NsCartModel();
        if (empty($sku_info)) {
            $cart->destroy([
                'buyer_id' => $this->uid,
                'sku_id' => $sku_id
            ]);
            $redirect = __URL(__URL__ . "/index");
            $this->redirect($redirect);
        }
        
        $goods = new NsGoodsModel();
        $goods_info = $goods->getInfo([
            'goods_id' => $sku_info["goods_id"]
        ], 'max_buy,state,point_exchange_type,point_exchange,picture,goods_id,goods_name,max_use_point');
        
        $cart_list["stock"] = $sku_info['stock']; // 库存
        $cart_list["sku_name"] = $sku_info["sku_name"];
        
        $goods_preference = new GoodsPreference();
//         $member_price = $goods_preference->getGoodsSkuMemberPrice($sku_info['sku_id'], $this->uid);
//         $cart_list["price"] = $member_price < $sku_info['promote_price'] ? $member_price : $sku_info['promote_price'];
        
        $cart_list["goods_id"] = $goods_info["goods_id"];
        $cart_list["goods_name"] = $goods_info["goods_name"];
        $cart_list["max_buy"] = $goods_info['max_buy']; // 限购数量
        $cart_list['point_exchange_type'] = $goods_info['point_exchange_type']; // 积分兑换类型 0 非积分兑换 1 只能积分兑换
        $cart_list['point_exchange'] = $goods_info['point_exchange']; // 积分兑换
        if ($goods_info['state'] != 1) {
            $redirect = __URL(__URL__ . "/index");
            $this->redirect($redirect);
        }
        $cart_list["num"] = $num;
        $cart_list["max_use_point"] = $goods_info["max_use_point"] * $num;
        
        //团购活动信息
        $group_num = 0;
        $group_price = 0;
        $group_buy_service = new GroupBuy();
        $group_buy_info = $group_buy_service -> getGoodsFirstPromotionGroupBuy($sku_info["goods_id"]);
        // 如果购买的数量超过限购，则取限购数量
        if ($goods_info['max_num'] != 0 && $goods_info['max_num'] < $num) {
            $num = $goods_info['max_buy'];
        }
        // 如果购买的数量超过库存，则取库存数量
        if ($sku_info['stock'] < $num) {
            $num = $sku_info['stock'];
        }
        if(($group_buy_info["max_num"] >=  $num && $group_buy_info["max_num"] != 0) && ($group_buy_info["min_num"] <=  $num && $group_buy_info["min_num"] != 0)){
            if(!empty($group_buy_info["price_array"])){
                foreach($group_buy_info["price_array"] as $price_key => $price_val){
                    if($num >= $price_val["num"]){
                        $group_num = $price_val["num"];
                        $group_price = $price_val["group_price"];
                    }
                }
            }
        }else{
            $redirect = __URL(__URL__ . "/index");
            $this->redirect($redirect);
        }
        //赋予商品团购价
        $cart_list["price"] = $group_price;
        
        $goods_service = new Goods();
        
        // 获取图片信息，如果该商品有SKU主图，就用。否则用商品主图
        $album_picture_model = new AlbumPictureModel();
        $picture_info = $album_picture_model->get($picture == 0 ? $goods_info['picture'] : $picture);
        $cart_list['picture_info'] = $picture_info;
        
        if (count($cart_list) == 0) {
            $redirect = __URL(__URL__ . "/index");
            $this->redirect($redirect);
        }
        $list[] = $cart_list;
        $goods_sku_list = $sku_id . ":" . $num; // 商品skuid集合
        $res["list"] = $list;
        $res["goods_sku_list"] = $goods_sku_list;
        return $res;
    }
    
    
    /**
     * 申请售后
     *
     * @return \think\response\View
     */
    public function customerService()
    {
        $order_goods_id = request()->get('order_goods_id', 0);
        if (! is_numeric($order_goods_id) || $order_goods_id == 0) {
            $this->error("没有获取到退款信息");
        }
        $id = 0;
        $order_service = new OrderService();
        $detail = $order_service->getCustomerServiceInfo($id, $order_goods_id);
        $this->assign("detail", $detail);
        
        $condition['order_goods_id'] = $order_goods_id;
        $condition['buyer_id'] = $this->uid;
        $count = $order_service->getUserOrderGoodsCountByCondition($condition);
        if ($count == 0) {
            $this->error("对不起,您无权进行此操作");
        }
    
        // 实际可退款金额
        $refund_money = $order_service->orderGoodsRefundMoney($order_goods_id);
        $this->assign('refund_money', sprintf("%.2f", $refund_money));
    
        // 余额退款
        $order_goods_service = new OrderGoods();
        $refund_balance = $order_goods_service->orderGoodsRefundBalance($order_goods_id);
        $this->assign("refund_balance", sprintf("%.2f", $refund_balance));
    
        // 查询店铺默认物流地址
        $express = new Express();
        $address = $express->getDefaultShopExpressAddress($this->instance_id);
        $this->assign("address_info", $address);
        // 查询商家地址
        $shop_info = $order_service->getShopReturnSet($this->instance_id);
        $this->assign("shop_info", $shop_info);
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        
        $this->assign("order_goods_id", $order_goods_id);
      //  dump($detail);
        if(empty($detail)){
            return view($this->style . "Member/customerServiceFirst");
        }else{
            return view($this->style . "Member/customerService");
        }
        
    }
    
    /**
     * 预售订单详情
     * @return \think\response\View
     */
    public function presellOrderDetail(){
        
        $order_id = request()->get('orderid', 0);
        if ($order_id == 0) {
            $this->error("没有获取到订单信息");
        }
        $order_service = new OrderService();
        $order_count = 0;
        $order_count = $order_service->getUserOrderDetailCount($this->uid, $order_id);
        if ($order_count == 0) {
            $this->error("没有获取到订单信息");
        }
        $detail = $order_service->getOrderDetail($order_id);
        
        $presell_detail = $order_service->getOrderPresellInfo(0, ['relate_id'=> $order_id]);
        $this->assign('presell_order', $presell_detail);
        if (empty($detail)) {
            $this->error("没有获取到订单信息");
        }
        $user_platform_money = sprintf("%.2f",($detail["user_platform_money"] + $presell_detail['platform_money']));
        $detail["user_platform_money"] = $user_platform_money;
        $this->assign("order", $detail);
    
        $config = new Config();
        $shopSet = $config->getShopConfig($this->instance_id);
        $this->assign("order_buy_close_time", $shopSet['order_buy_close_time']);
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
    
        return view($this->style . 'Member/presellOrderDetail');
    }
    
    /**
     * 处理自提地址的排序
     * @param unknown $address
     * @param unknown $pickup_point_list
     */
    public function pickupPointListSort($address, $pickup_point_list){
        $arr = array();
        if(!empty($address) && !empty($pickup_point_list)){
            $district_arr = array();
            $city_arr = array();
            $province_arr = array();
            foreach ($pickup_point_list as $key => $pickup_point){
                if($pickup_point["district_id"] == $address["district"]){
                    array_push($district_arr, $pickup_point_list[$key]);
                    unset($pickup_point_list[$key]);
                }elseif($pickup_point["city_id"] == $address["city"]){
                    array_push($city_arr, $pickup_point_list[$key]);
                    unset($pickup_point_list[$key]);
                }elseif($pickup_point["province_id"] == $address["province"]){
                    array_push($province_arr, $pickup_point_list[$key]);
                    unset($pickup_point_list[$key]);
                }
            }
            $arr = array_merge($district_arr, $city_arr, $province_arr, $pickup_point_list);
        }
        return $arr;
    }
    
    /**
     * 查看物流
     */
    public function seeLogistics(){
        $order_id = request()->get('orderid', 0);
        if ($order_id == 0) {
            $this->error("没有获取到订单信息");
        }
        $order_service = new OrderService();
        $order_count = 0;
        $order_count = $order_service->getUserOrderDetailCount($this->uid, $order_id);
        if ($order_count == 0) {
            $this->error("没有获取到订单信息");
        }
        $detail = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            $this->error("没有获取到订单信息");
        }
        $this->assign("order", $detail);
        // 会员信息
        $member_detail = $this->getMemberDetail();
        $this->assign("member_detail", $member_detail);
        return view($this->style . 'Member/seeLogistics');
    }
}