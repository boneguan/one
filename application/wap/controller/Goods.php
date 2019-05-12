<?php
/**
 * Goods.php
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

use data\service\Config as WebConfig;
use data\service\Goods as GoodsService;
use data\service\GoodsBrand as GoodsBrand;
use data\service\GoodsCategory;
use data\service\GoodsGroup;
use data\service\Member;
use data\service\Order as OrderService;
use data\service\Platform;
use data\service\promotion\GoodsExpress;
use data\service\Address;
use data\service\WebSite;
use data\service\Promotion;
use data\service\promotion\PromoteRewardRule;
use data\service\Pintuan;
use data\service\GroupBuy;
use data\service\Bargain;
use data\service\User;
use data\service\Config;
use data\service\Shop;
use think\session;

/**
 * 商品相关
 *
 * @author Administrator
 *        
 */
class Goods extends BaseController
{

    /**
     * 商品详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function goodsDetail()
    {
        $goods_id = request()->get('id', 0);
        $bargain_id = request()->get('bargain_id', 0);
        if ($goods_id == 0) {
            $this->error("没有获取到商品信息");
        }
        
        $liebiao = request()->get('liebiao', 0);
        if($liebiao == 1){
        	session::set('liebiao', $liebiao);
        }
        
        $this->web_site = new WebSite();
        $goods = new GoodsService();
        $config_service = new WebConfig();
        $member = new Member();
        $shop_service = new Shop();
        $shop_id = $this->instance_id;
        $uid = $this->uid;
        
        $web_info = $this->web_site->getWebSiteInfo();
        $group_id = request()->get("group_id", 0);
        
        // 切换到PC端
        if (! request()->isMobile() && $web_info['web_status'] == 1) {
            $redirect = __URL(__URL__ . "/goods/goodsinfo?goodsid=" . $goods_id);
            $this->redirect($redirect);
            exit();
        }
        
        // 清空待付款订单返回订单详情的标识
        $_SESSION['unpaid_goback'] = "";
        $_SESSION['order_create_flag'] = "";
        
        $goods_detail = $goods->getBasisGoodsDetail($goods_id);
        if (empty($goods_detail)) {
            $this->error("没有获取到商品信息");
        }
        if ($this->getIsOpenVirtualGoodsConfig() == 0 && $goods_detail['goods_type'] == 0) {
            $this->error("未开启虚拟商品功能");
        }
        // 商品点击量
        $goods->updateGoodsClicks($goods_id);
        
        // 是否是微信浏览器
        $this->assign("isWeixin", isWeixin());
        
        // 把属性值相同的合并
        $goods_attribute_list = $goods_detail['goods_attribute_list'];
        $goods_attribute_list_new = array();
        foreach ($goods_attribute_list as $item) {
            $attr_value_name = '';
            foreach ($goods_attribute_list as $key => $item_v) {
                if ($item_v['attr_value_id'] == $item['attr_value_id']) {
                    $attr_value_name .= $item_v['attr_value_name'] . ',';
                    unset($goods_attribute_list[$key]);
                }
            }
            if (! empty($attr_value_name)) {
                array_push($goods_attribute_list_new, array(
                    'attr_value_id' => $item['attr_value_id'],
                    'attr_value' => $item['attr_value'],
                    'attr_value_name' => rtrim($attr_value_name, ',')
                ));
            }
        }
        $goods_detail['goods_attribute_list'] = $goods_attribute_list_new;

        if($goods_detail['integral_give_type'] == 1 && $goods_detail['give_point'] > 0){
            $price = $goods_detail['promotion_price'] < $goods_detail['member_price'] ? $goods_detail['promotion_price'] : $goods_detail['member_price'];
            $goods_detail['give_point'] = round($price * $goods_detail['give_point'] * 0.01);
        }
        
        // 获取当前时间
        $current_time = $this->getCurrentTime();
        $this->assign('ms_time', $current_time);
        $this->assign("goods_detail", $goods_detail);
        $this->assign("shopname", $this->shop_name);
        $this->assign("price", intval($goods_detail["promotion_price"]));
        $this->assign("goods_id", $goods_id);
        $this->assign("title_before", $goods_detail['goods_name']);
        
        // 返回商品数量和当前商品的限购
        $this->getCartInfo($goods_id);
        
        // 分享
        $ticket = $this->getShareTicket();
        $this->assign("signPackage", $ticket);
        
        // 评价数量
        $evaluates_count = $goods->getGoodsEvaluateCount($goods_id);
        $this->assign('evaluates_count', $evaluates_count);
        
        // 评价
        $goodsEvaluation = "";
        $order = new OrderService();
        $goodsEvaluation = $order->getOrderEvaluateDataList(1, 1, [
            "goods_id" => $goods_id
        ], 'addtime desc');
        if (! empty($goodsEvaluation)) {
            $memberService = new Member();
            $goodsEvaluation["data"][0]["user_img"] = $memberService->getMemberImage($goodsEvaluation["data"][0]["uid"]);
            $this->assign("goodsEvaluation", $goodsEvaluation["data"][0]);
        } else {
            $this->assign("goodsEvaluation", $goodsEvaluation);
        }
        
        // 客服
        $customservice_config = $config_service->getcustomserviceConfig($shop_id);
        if (empty($customservice_config)) {
            $list['id'] = '';
            $list['value']['service_addr'] = '';
        }
        
        $this->assign("customservice_config", $customservice_config);
        // $this->assign('service_addr',$list['value']['service_addr']);
        // 查询点赞记录表，获取详情再判断当天该店铺下该商品该会员是否已点赞
        $click_detail = $goods->getGoodsSpotFabulous($shop_id, $uid, $goods_id);
        $this->assign('click_detail', $click_detail);
        
        // 当前用户是否收藏了该商品
        if (isset($uid)) {
            $is_member_fav_goods = $member->getIsMemberFavorites($uid, $goods_id, 'goods');
        }
        $this->assign("is_member_fav_goods", $is_member_fav_goods);
        
        // 获取商品的优惠劵
        $goods_coupon_list = $goods->getGoodsCoupon($goods_id, $this->uid);
        $this->assign("goods_coupon_list", $goods_coupon_list);
        
        // 组合商品
        $promotion = new Promotion();
        $comboPackageGoodsArray = $promotion->getComboPackageGoodsArray($goods_id);
        $this->assign("comboPackageGoodsArray", $comboPackageGoodsArray[0]);
        
        // 商品阶梯优惠
        $goodsLadderPreferentialList = $goods->getGoodsLadderPreferential([
            "goods_id" => $goods_id
        ], "quantity desc", "quantity,price");
        $this->assign("goodsLadderPreferentialList", array_reverse($goodsLadderPreferentialList));
        
        // 添加足迹
        if ($this->uid > 0) {
            $goods->addGoodsBrowse($goods_id, $this->uid);
        }
        // 商品标签
        $goods_group = new GoodsGroup();
        $goods_group_list = $goods_group->getGoodsGroupList(1, 0, [
            "group_id" => array(
                "in",
                $goods_detail["group_id_array"]
            )
        ], "", "group_name");
        $this->assign("goods_group_list", $goods_group_list["data"]);
        
        // 店铺服务
        $existingMerchant = $config_service->getExistingMerchantService($this->instance_id);
        $this->assign("existingMerchant", $existingMerchant);
        
        // 积分抵现比率
        $integral_balance = 0; // 积分可抵金额
        $point_config = $promotion->getPointConfig();
        if ($point_config["is_open"] == 1) {
            if ($goods_detail['max_use_point'] > 0 && $point_config['convert_rate'] > 0) {
                $integral_balance = $goods_detail['max_use_point'] * $point_config['convert_rate'];
            }
        }
        $this->assign("integral_balance", $integral_balance);
        
        // 判断当前商品是否有拼团
        if (empty($goods_detail["wap_custom_template"])) {
        	$is_support_pintuan = IS_SUPPORT_PINTUAN;
        	$is_support_bargin = IS_SUPPORT_BARGAIN;
        	
        	if($is_support_bargin == 1){
            	$bargain = new Bargain();
              	$bargain_config = $bargain->getConfig();
        	}else{
        	    $bargain_config['is_use'] = 0;
        	}
        	
        	//判断是否是砍价
        	if($bargain_config['is_use'] == 1 && !empty($bargain_id)){
    			//砍价商品详情
        	    $goods_express_service = new GoodsExpress();
        	    $express_count = $goods_express_service->getExpressCompanyCount($this->instance_id);
        	    $this->assign("express_company_count", $express_count); // 物流公司数量
        	    
        	    $pickup_point_list = $shop_service->getPickupPointList();
        	    $this->assign("pickup_point_list", $pickup_point_list); // 自提地址列表
        	    
        	    // 查询店铺配送设置
        	    $Config = new Config();
        	    $shop_id = $this->instance_id;
        	    $seller_dispatching = $Config->getConfig($shop_id, "ORDER_SELLER_DISPATCHING");
        	    $buyer_self_lifting = $Config->getConfig($shop_id, "BUYER_SELF_LIFTING");
        	    $this->assign("buyer_self_lifting", $buyer_self_lifting["value"]);
        	    $this->assign("seller_dispatching", $seller_dispatching["value"]);
        	    
    			$goods_bargain = $bargain->getBargainGoodsInfo($bargain_id, $goods_id);
    			if($goods_bargain['status'] == 1){
    				$addresslist = $member->getMemberExpressAddressList();
    				$this->assign("addresslist", $addresslist);
    				$this->assign('bargain_id', $bargain_id);
    				$this->assign('goods_bargain' , $goods_bargain);
    				return view($this->style . 'Goods/goodsBargainDetail');
    			}
        	} elseif ($is_support_pintuan == 1) {
                $pintuan = new Pintuan();
                $goods_pintuan = $pintuan->getGoodsPintuanDetail($goods_id);
                if ($goods_pintuan['is_open']) {
                    // 商品拼团详情
                    $goods_pintuan['tuangou_content_json'] = json_decode($goods_pintuan['tuangou_content_json'], true);
                    $this->assign("goods_pintuan", $goods_pintuan);
                    // 检查当前拼团是否存在
                    $tuangou_group_count = 0;
                    if ($group_id > 0) {
                        $tuangou_group_count = $pintuan->getTuangouGroupCount($group_id, $goods_id);
                    }
                    $this->assign("group_id", $group_id);
                    $this->assign("tuangou_group_count", $tuangou_group_count);
                    return view($this->style . 'Goods/goodsPinTuanDetail');
                } else {
                    // 默认商品详情模板
                    if ($goods_detail["point_exchange_type"] == 0 || $goods_detail["point_exchange_type"] == 2 || $goods_detail["is_open_presell"] == 1) {
                        return view($this->style . 'Goods/goodsDetail');
                    } else {
                        return view($this->style . 'Goods/goodsDetailPointExchange');
                    }
                }
            } else {
                if ($goods_detail["point_exchange_type"] == 0 || $goods_detail["point_exchange_type"] == 2 || $goods_detail["is_open_presell"] == 1) {
                    return view($this->style . 'Goods/goodsDetail');
                } else {
                    return view($this->style . 'Goods/goodsDetailPointExchange');
                }
            }
        } else {
            return view($this->style . 'Goods/' . $goods_detail["wap_custom_template"]);
        }
    }

    //新页面显示视频播放
    public function showVideo(){
        return view($this->style . 'Goods/showVideo');
    }

    /**
     * 根据定位查询当前商品的运费
     * 创建时间：2017年9月29日 15:12:55 王永杰
     */
    public function getShippingFeeNameByLocation()
    {
        $goods_id = request()->post("goods_id", "");
        $express = "";
        if (! empty($goods_id)) {
            
            $user_location = get_city_by_ip();
            if ($user_location['status'] == 1) {
                // 定位成功，查询当前城市的运费
                $goods_express = new GoodsExpress();
                $address = new Address();
                $province = $address->getProvinceId($user_location["province"]);
                $city = $address->getCityId($user_location["city"]);
                $district = $address->getCityFirstDistrict($city['city_id']);
                $express = $goods_express->getGoodsExpressTemplate($goods_id, $province['province_id'], $city['city_id'], $district);
            }
        }
        return $express;
    }

    /**
     * 得到当前时间戳的毫秒数
     *
     * @return number
     */
    public function getCurrentTime()
    {
        $time = time();
        $time = $time * 1000;
        return $time;
    }

    /**
     * 功能：商品评论
     * 创建人：李志伟
     * 创建时间：2017年2月23日11:12:57
     */
    public function getGoodsComments()
    {
        $comments_type = request()->post('comments_type', '');
        $order = new OrderService();
        $condition['goods_id'] = request()->post('goods_id', '');
        switch ($comments_type) {
            case 1:
                $condition['explain_type'] = 1;
                break;
            case 2:
                $condition['explain_type'] = 2;
                break;
            case 3:
                $condition['explain_type'] = 3;
                break;
            case 4:
                $condition['image|again_image'] = array(
                    'NEQ',
                    ''
                );
                break;
        }
        $condition['is_show'] = 1;
        $goodsEvaluationList = $order->getOrderEvaluateDataList(1, PAGESIZE, $condition, 'addtime desc');
        // 查询评价用户的头像
        $memberService = new Member();
        foreach ($goodsEvaluationList['data'] as $v) {
            $v["user_img"] = $memberService->getMemberImage($v["uid"]);
        }
        return $goodsEvaluationList;
    }

    /**
     * 返回商品数量和当前商品的限购
     *
     * @param unknown $goods_id            
     */
    public function getCartInfo($goods_id)
    {
        $goods = new GoodsService();
        $cartlist = $goods->getCart($this->uid);
        $num = 0;
        if(!empty($cartlist)){
            foreach ($cartlist as $v) {
                if ($v["goods_id"] == $goods_id) {
                    $num = $v["num"];
                }
            }
        }
        $this->assign("carcount", count($cartlist)); // 购物车商品数量
        $this->assign("num", $num); // 购物车已购买商品数量
    }

    /**
     * 购物车页面
     */
    public function cart()
    {
        $this->is_member = $this->user->getSessionUserIsMember();
        if ($this->is_member == 0) {
            $redirect = __URL(__URL__ . "/wap/login");
            $this->redirect($redirect);
        }
        $this->assign("shopname", $this->shop_name);
        $goods = new GoodsService();
        
        $cartlist = $goods->getCart($this->uid, $this->instance_id);
        // 店铺，店铺中的商品
        $list = Array();
        for ($i = 0; $i < count($cartlist); $i ++) {
            $list[$cartlist[$i]["shop_id"] . ',' . $cartlist[$i]["shop_name"]][] = $cartlist[$i];
        }
        $this->assign("list", $list);
        $this->assign("countlist", count($cartlist));
        $this->assign("title_before", "购物车");
        
        // 商品阶梯优惠信息
        $goods_id_arr = array();
        $goods_ladder_preferential = array();
        if (count($cartlist) > 0) {
            foreach ($cartlist as $v) {
                if(!in_array($v["goods_id"], $goods_id_arr)){
                    $goods_ladder_preferential[] = $goods->getGoodsLadderPreferential([
                        "goods_id" => $v["goods_id"]
                    ], "quantity desc");
                    array_push($goods_id_arr, $v["goods_id"]);
                }
            }
        }
        $this->assign("goods_ladder_preferential", json_encode($goods_ladder_preferential));
        return view($this->style . 'Goods/cart');
    }

    /**
     * 添加购物车
     * 创建人：李广
     */
    public function addCart()
    {
        $cart_detail = request()->post('cart_detail', '');
        if (! empty($cart_detail)) {
            $cart_detail = json_decode($cart_detail, true);
        }
        $cart_tag = request()->post('cart_tag', '');
        $uid = $this->uid;
        $shop_id = $cart_detail["shop_id"];
        $shop_name = $cart_detail["shop_name"];
        $goods_id = $cart_detail['trueId'];
        $goods_name = $cart_detail['goods_name'];
        $num = $cart_detail['count'];
        $sku_id = $cart_detail['select_skuid'];
        $sku_name = $cart_detail['select_skuName'];
        $price = $cart_detail['price'];
        $cost_price = $cart_detail['cost_price'];
        $picture = $cart_detail['picture'];
        $this->is_member = $this->user->getSessionUserIsMember();
        if (! empty($this->uid) && $this->is_member == 1) {
            $goods = new GoodsService();
            $retval = $goods->addCart($uid, $shop_id, $shop_name, $goods_id, $goods_name, $sku_id, $sku_name, $price, $num, $picture, 0);
        } else {
            $retval = array(
                "code" => - 1,
                "message" => ""
            );
        }
        return $retval;
    }

    /**
     * 购物车修改数量
     */
    public function cartAdjustNum()
    {
        if (request()->isAjax()) {
            $cart_id = request()->post('cartid', '');
            $num = request()->post('num', '');
            $goods = new GoodsService();
            $retval = $goods->cartAdjustNum($cart_id, $num);
            return AjaxReturn($retval);
        } else
            return AjaxReturn(- 1);
    }

    /**
     * 购物车项目删除
     */
    public function cartDelete()
    {
        if (request()->isAjax()) {
            $cart_id_array = request()->post('del_id', '');
            $goods = new GoodsService();
            $retval = $goods->cartDelete($cart_id_array);
            return AjaxReturn($retval);
        } else
            return AjaxReturn(- 1);
    }

    /**
     * 平台商品分类列表
     */
    public function goodsClassificationList()
    {
        $uid = $this->uid;
        $goods_category = new GoodsCategory();
        $goods = new GoodsService();
        $goods_category_list = $goods_category->getCategoryTreeUseInShopIndex();
        // 计算补足数量
        foreach ($goods_category_list as $k => $v) {
            $num = 0;
            if (count($v["child_list"]) < 3) {
                $num = 3 - count($v["child_list"]);
            }
            if (count($v["child_list"]) > 3) {
                $max_row = (count($v["child_list"]) + 1) / 4;
                $max_row = ceil($max_row);
                $num = $max_row * 4 - (count($v["child_list"]) + 1);
            }
            $goods_category_list[$k]['num'] = $num;
            $condition=[];
            $condition['ng.state'] = 1;
            $condition['ng.category_id'] = $v['category_id'];
            $goods_category_list[$k]['goods_list'] = $goods->getGoodsListNew(1, 4, $condition, '');
        }
        $this->assign("goods_category_list", $goods_category_list);

        $this->assign("title_before", "商品分类");
        $webConfig = new WebConfig();
        $show_type = $webConfig->getWapClassifiedDisplayMode($this->instance_id);
        
        if ($show_type == 1) {
            return view($this->style . 'Goods/goodsClassificationFloor');
        } else {
            return view($this->style . 'Goods/goodsClassificationList');
        }
    }

    /**
     * 店铺商品分组列表
     */
    public function goodsGroupList()
    {
        // 查询购物车中商品的数量
        $uid = $this->uid;
        $goods = new GoodsService();
        $cartlist = $goods->getCart($uid);
        $this->assign('uid', $uid);
        $this->assign("carcount", count($cartlist));
        
        $components = new Components();
        $grouplist = $components->goodsGroupList($this->shop_id);
        $group_frist_list = null;
        $group_second_list = null;
        foreach ($grouplist as $group) {
            if ($group["pid"] == 0) {
                $group_frist_list[] = $group;
            } else {
                $group_second_list[] = $group;
            }
        }
        $this->assign("group_frist_list", $group_frist_list);
        $this->assign("group_second_list", $group_second_list);
        
        $group_goods = new GoodsGroup();
        $tree_list = $group_goods->getGroupGoodsTree($this->shop_id);
        $this->assign("tree_list", $tree_list);
        return view($this->style . 'Goods/goodsGroupList');
    }

    /**
     * 商品分类列表
     */
    public function goodsCategoryList()
    {
        $goodscate = new GoodsCategory();
        $one_list = $goodscate->getGoodsCategoryListByParentId(0);
        if (! empty($one_list)) {
            foreach ($one_list as $k => $v) {
                $two_list = array();
                $two_list = $goodscate->getGoodsCategoryListByParentId($v['category_id']);
                $v['child_list'] = $two_list;
                if (! empty($two_list)) {
                    foreach ($two_list as $k1 => $v1) {
                        $three_list = array();
                        $three_list = $goodscate->getGoodsCategoryListByParentId($v1['category_id']);
                        $v1['child_list'] = $three_list;
                    }
                }
            }
        }
        return $one_list;
    }

    /**
     * 加入购物车前显示商品规格
     */
    public function joinCartInfo()
    {
        $goods = new GoodsService();
        $goods_id = request()->post('goods_id', '');
        $goods_detail = $goods->getGoodsDetail($goods_id);
        $this->assign("goods_detail", $goods_detail);
        $this->assign("shopname", $this->shop_name);
        // $this->assign("style", $this->style);
        return view($this->style . 'joinCart');
    }

    /**
     * 搜索商品显示
     */
    public function goodsSearchList()
    {
        if (request()->isAjax()) {
            $search_name = request()->post('search_name', '');
            $sear_type = request()->post('sear_type', '');
            $order = request()->post('obyzd', '');
            $sort = request()->post('st', 'desc');
            $controlType = request()->post('controlType', '');
            $shop_id = request()->post('shop_id', '');
            $page = request()->post("page", 1);
            $goods = new GoodsService();
            $condition['goods_name|keywords'] = [
                'like',
                '%' . $search_name . '%'
            ];
            
            switch ($controlType) {
                case 1:
                    $condition = [
                        'is_new' => 1
                    ];
                    break;
                case 2:
                    $condition = [
                        'is_hot' => 1
                    ];
                    break;
                case 3:
                    $condition = [
                        'is_recommend' => 1
                    ];
                    break;
                default:
                    break;
            }
            
            // 参数过滤
            
            // 如果排序方式不为空，则进行过滤
            if ($sort != "") {
                if ($sort != "desc" && $sort != "asc") {
                    // 非法参数进行过滤
                    $sort = "";
                }
            }
            $orderby = ""; // 排序方式
            if ($order != "") {
                if ($order != "ng.sales" && $order != "ng.is_new" && $order != "ng.promotion_price") {
                    // 非法参数进行过滤
                    $orderby = "ng.sort asc,ng.create_time desc";
                } else {
                    $orderby = $order . " " . $sort;
                }
            } else {
                $orderby = "ng.sort asc,ng.create_time desc";
            }
            
            if (! empty($shop_id)) {
                $condition['ng.shop_id'] = $shop_id;
            }
            $condition['state'] = 1;
            $search_good_list = $goods->getGoodsListNew($page, PAGESIZE, $condition, $orderby);
            return $search_good_list;
        } else {
            $search_name = request()->get('search_name', '');
            $controlType = request()->get('controlType', ''); // 什么类型 1最新 2精品 3推荐
            $controlTypeName = request()->get('controlTypeName', ''); // 什么类型 1最新 2精品 3推荐
            
            if (! empty($search_name)) {
                $search_title = $search_name;
            } else {
                $search_title = $controlTypeName;
            }
            if (mb_strlen($search_name) > 10) {
                $search_name = mb_substr($search_name, 0, 7, 'utf-8') . '...';
            }
            $shop_id = $this->shop_id;
            $this->assign('controlType', $controlType);
            $this->assign('search_name', $search_name);
            $this->assign('shop_id', $shop_id);
            $this->assign('search_title', $search_title);
            return view($this->style . 'Goods/goodsSearchList');
        }
    }

    /**
     * 品牌专区
     */
    public function brandlist()
    {
        $platform = new Platform();
        $goods = new GoodsService();
        // 品牌专区广告位
        $brand_adv = $platform->getPlatformAdvPositionDetail(1162);
        $this->assign('brand_adv', $brand_adv);
        
        if (request()->isAjax()) {
            $brand_id = request()->get("brand_id", "");
            $page_index = request()->get("page", 1);
            if (! empty($brand_id)) {
                $condition['ng.brand_id'] = $brand_id;
            }
            $condition['ng.state'] = 1;
            $list = $goods->getGoodsListNew($page_index, PAGESIZE, $condition, "ng.sort asc,ng.create_time desc");
            return $list;
        } else {
            $goods_category = new GoodsCategory();
            $goods_category_list_1 = $goods_category->getGoodsCategoryList(1, 0, [
                "is_visible" => 1,
                "level" => 1
            ]);
            $goods_brand = new GoodsBrand();
            $goods_brand_list = $goods_brand->getGoodsBrandList(1, 0, '', 'brand_initial asc');
            // print_r(json_encode($goods_brand_list));
            // return;
            // var_dump($goods_brand_list);
            $this->assign("goods_brand_list", $goods_brand_list['data']);
            $this->assign("goods_category_list_1", $goods_category_list_1["data"]);
            $this->assign("title_before", "品牌专区");
            return view($this->style . 'Goods/brandlist');
        }
    }

    /**
     * 商品列表
     */
    public function goodsList()
    {
        // 查询购物车中商品的数量
        $uid = $this->uid;
        $goods = new GoodsService();
        $goods_category_service = new GoodsCategory();
        $cartlist = $goods->getCart($uid);
        $this->assign('uid', $uid);
        $this->assign("carcount", count($cartlist));
        
        if (request()->isAjax()) {
            $category_id = request()->post('category_id', ''); // 商品分类
            $brand_id = request()->post('brand_id', ''); // 品牌
            $order = request()->post('obyzd', ''); // 商品排序分类,order by ziduan
            $sort = request()->post('st', 'desc'); // 商品排序分类 sort
            $page = request()->post('page', 1);
            $min_price = request()->post('mipe', ''); // 价格区间,最小min_price
            $max_price = request()->post('mape', ''); // 最大 max_price
            $attr = request()->post('attr', ''); // 属性值
            $spec = request()->post('spec', ''); // 规格值
            $page_size = request()->post("page_size","");
            // 将属性条件字符串转化为数组
            $attr_array = $this->stringChangeArray($attr);
            // 规格转化为数组
            if ($spec != "") {
                $spec_array = explode(";", $spec);
            } else {
                $spec_array = array();
            }
            
            // 参数过滤
            
            // 如果排序方式不为空，则进行过滤
            if ($sort != "") {
                if ($sort != "desc" && $sort != "asc") {
                    // 非法参数进行过滤
                    $sort = "";
                }
            }
            $orderby = ""; // 排序方式
            if ($order != "") {
                if ($order != "ng.sales" && $order != "ng.is_new" && $order != "ng.promotion_price") {
                    // 非法参数进行过滤
                    $orderby = "ng.sort asc,ng.create_time desc";
                } else {
                    $orderby = $order . " " . $sort;
                }
            } else {
                $orderby = "ng.sort asc,ng.create_time desc";
            }
            
            if(session::get('liebiao') == 1){
            	$goods_list = session::get('goods_data');
            	session::set('liebiao', 0);
            }else{
            	$goods_list = $this->getGoodsListByConditions($category_id, $brand_id, $min_price, $max_price, $page, $page_size, $orderby, $attr_array, $spec_array);
            	//刷新 或者从别的非详情页进入时要清空session数据
            	if($page == 1){
            		session::delete('goods_data');
            	}
            	//将数据存入session
            	$goods_data = [];
            	if(session::get('goods_data')){
            		$goods_data = session::get('goods_data');
            		$goods_data['page_count'] = $goods_list['page_count'];
            		$goods_data['page_nex'] = $page;
            		$goods_data['data'] = array_merge($goods_data['data'], $goods_list['data']);
            	}else{
            		$goods_data = $goods_list;
            	}
            	session::set('goods_data', $goods_data);
            }      
                    
            return $goods_list;
        } else {
            $category_id = request()->get('category_id', ''); // 商品分类
            $brand_id = request()->get('brand_id', ''); // 品牌
            $this->assign('brand_id', $brand_id);
            $this->assign('category_id', $category_id);
            // 筛选条件
            if ($category_id != "") {
                // 获取商品分类下的品牌列表、价格区间
                $category_brands = [];
                $category_price_grades = [];
                
                // 查询品牌列表，用于筛选
                $category_brands = $goods_category_service->getGoodsBrandsByGoodsAttr($category_id);
                
                // 查询价格区间，用于筛选
                $category_price_grades = $goods_category_service->getGoodsCategoryPriceGrades($category_id);
                foreach ($category_price_grades as $k => $v) {
                    $category_price_grades[$k]['price_str'] = $v[0] . '-' . $v[1];
                }
                $category_count = 0; // 默认没有数据
                if ($category_brands != "") {
                    $category_count = 1; // 有数据
                }
                $goods_category_info = $goods_category_service->getGoodsCategoryDetail($category_id);
                
                $attr_id = $goods_category_info["attr_id"];
                // 查询商品分类下的属性和规格集合
                $goods_attribute = $goods->getAttributeInfo([
                    "attr_id" => $attr_id
                ]);
                $attribute_detail = $goods->getAttributeServiceDetail($attr_id, [
                    'is_search' => 1
                ]);
                $attribute_list = array();
                if (! empty($attribute_detail['value_list']['data'])) {
                    $attribute_list = $attribute_detail['value_list']['data'];
                    foreach ($attribute_list as $k => $v) {
                        $value_items = explode(",", $v['value']);
                        $new_value_items = array();
                        foreach ($value_items as $ka => $va) {
                            $new_value_items[$ka]['value'] = $va;
                            $new_value_items[$ka]['value_str'] = $attribute_list[$k]['attr_value_name'] . ',' . $va . ',' . $attribute_list[$k]['attr_value_id'];
                        }
                        $attribute_list[$k]['value'] = trim($v["value"]);
                        $attribute_list[$k]['value_items'] = $new_value_items;
                    }
                }
                $attr_list = $attribute_list;
                // 查询本商品类型下的关联规格
                $goods_spec_array = array();
                if ($goods_attribute["spec_id_array"] != "") {
                    $goods_spec_array = $goods->getGoodsSpecQuery([
                        "spec_id" => [
                            "in",
                            $goods_attribute["spec_id_array"]
                        ],
                        'goods_id' => 0
                    ]);
                    foreach ($goods_spec_array as $k => $v) {
                        foreach ($v["values"] as $z => $c) {
                            $c["value_str"] = $c['spec_id'] . ':' . $c['spec_value_id'];
                        }
                    }
                    sort($goods_spec_array);
                }
                $this->assign("attr_or_spec", $attr_list);
                $this->assign("category_brands", $category_brands);
                $this->assign("category_count", $category_count);
                $this->assign("category_price_grades", $category_price_grades);
                $this->assign("category_price_grades_count", count($category_price_grades));
                $this->assign("goods_spec_array", $goods_spec_array); // 分类下的规格
                $this->assign("title_before", $goods_category_info['category_name']);
            }
            // 获取分类列表
            $goodsCategoryList = $goods_category_service->getCategoryTreeUseInShopIndex();
            $this->assign("goodsCategoryList", $goodsCategoryList);
            
            $template = 'Goods/goodsList';
            if (! empty($goods_category_info["wap_custom_template"])) {
                $template = 'Goods/' . $goods_category_info["wap_custom_template"];
            }
            
            return view($this->style . $template);
        }
    }

    /**
     * 将属性字符串转化为数组
     *
     * @param unknown $string            
     * @return multitype:multitype: |multitype:
     */
    private function stringChangeArray($string)
    {
        if (trim($string) != "") {
            $temp_array = explode(";", $string);
            $attr_array = array();
            foreach ($temp_array as $k => $v) {
                $v_array = array();
                if (strpos($v, ",") === false) {
                    $attr_array = array();
                    break;
                } else {
                    $v_array = explode(",", $v);
                    if (count($v_array) != 3) {
                        $attr_array = array();
                        break;
                    } else {
                        $attr_array[] = $v_array;
                    }
                }
            }
            return $attr_array;
        } else {
            return array();
        }
    }

    /**
     * 根据条件查询商品列表：商品分类查询，关键词查询，价格区间查询，品牌查询
     * 创建人：王永杰
     * 创建时间：2017年2月24日 16:55:05
     */
    public function getGoodsListByConditions($category_id, $brand_id, $min_price, $max_price, $page, $page_size, $order, $attr_array, $spec_array)
    {
        $goods = new GoodsService();
        $condition = null;
        if ($category_id != "") {
            // 商品分类Id
            $condition["ng.category_id"] = $category_id;
        }
        // 品牌Id
        if ($brand_id != "") {
            $condition["ng.brand_id"] = array(
                "in",
                $brand_id
            );
        }
        
        // 价格区间
        if ($max_price != "") {
            $condition["ng.promotion_price"] = [
                [
                    ">=",
                    $min_price
                ],
                [
                    "<=",
                    $max_price
                ]
            ];
        }
        
        // 属性 (条件拼装)
        $array_count = count($attr_array);
        $goodsid_str = "";
        $attr_str_where = "";
        if (! empty($attr_array)) {
            // 循环拼装sql属性条件
            foreach ($attr_array as $k => $v) {
                if ($attr_str_where == "") {
                    $attr_str_where = "(attr_value_id = '$v[2]' and attr_value_name='$v[1]')";
                } else {
                    $attr_str_where = $attr_str_where . " or " . "(attr_value_id = '$v[2]' and attr_value_name='$v[1]')";
                }
            }
            if ($attr_str_where != "") {
                $attr_query = $goods->getGoodsAttributeQuery($attr_str_where);
                
                $attr_array = array();
                foreach ($attr_query as $t => $b) {
                    $attr_array[$b["goods_id"]][] = $b;
                }
                $goodsid_str = "0";
                foreach ($attr_array as $z => $x) {
                    if (count($x) == $array_count) {
                        if ($goodsid_str == "") {
                            $goodsid_str = $z;
                        } else {
                            $goodsid_str = $goodsid_str . "," . $z;
                        }
                    }
                }
            }
        }
        
        // 规格条件拼装
        $spec_count = count($spec_array);
        $spec_where = array();
        
        if ($spec_count > 0) {
            foreach ($spec_array as $k => $v) {
                $tmp_array = explode(':', $v);
                // 得到规格名称
                $spec_info = $goods->getGoodsAttributeList([
                    "spec_id" => $tmp_array[0]
                ], 'spec_name', '');
                $spec_name = $spec_info[0]["spec_name"];
                // 得到规格值名称
                $spec_value_info = $goods->getGoodsAttributeValueList([
                    "spec_value_id" => $tmp_array[1]
                ], 'spec_value_name');
                $spec_value_name = $spec_value_info[0]["spec_value_name"];
                if (! empty($spec_name)) {
                    $spec_where[] = array(
                        'like',
                        '%' . $spec_name . '%'
                    );
                }
                if (! empty($spec_value_name)) {
                    $spec_where[] = array(
                        'like',
                        '%' . $spec_value_name . '%'
                    );
                }
                // if ($spec_where == "") {
                // $spec_where = " attr_value_items_format like '%{$v}%' ";
                // } else {
                // $spec_where = $spec_where . " or " . " attr_value_items_format like '%{$v}%' ";
                // }
            }
            
            // if ($spec_where != "") {
            
            // $goods_query = $this->goods->getGoodsSkuQuery($spec_where);
            // $temp_array = array();
            // foreach ($goods_query as $k => $v) {
            // $temp_array[] = $v["goods_id"];
            // }
            // $goods_query = array_unique($temp_array);
            // if (! empty($goods_query)) {
            // if ($goodsid_str != "") {
            // $attr_con_array = explode(",", $goodsid_str);
            // $goods_query = array_intersect($attr_con_array, $goods_query);
            // $goods_query = array_unique($goods_query);
            // $goodsid_str = "0," . implode(",", $goods_query);
            // } else {
            // $goodsid_str = "0,";
            // $goodsid_str .= implode(",", $goods_query);
            // }
            // } else {
            // $goodsid_str = "0";
            // }
            // }
            if (! empty($spec_where)) {
                $condition["ng.goods_spec_format"] = [
                    $spec_where
                ];
            }
        }
        if ($goodsid_str != "") {
            $condition["goods_id"] = [
                "in",
                $goodsid_str
            ];
        }
        
        $condition['ng.state'] = 1;

        $list = $goods->getGoodsListNew($page, $page_size, $condition, $order);
        
        return $list;
    }

    /**
     * 积分中心
     *
     * @return \think\response\View
     */
    public function integralCenter()
    {
        $platform = new Platform();
        // 积分中心广告位
        $discount_adv = $platform->getPlatformAdvPositionDetail(1165);
        $this->assign('discount_adv', $discount_adv);
        // 积分中心商品
        $this->goods = new GoodsService();
        $order = "";
        // 排序
        $id = request()->get('id', '');
        if ($id) {
            if ($id == 1) {
                $order = "sales desc";
            } else 
                if ($id == 2) {
                    $order = "collects desc";
                } else 
                    if ($id == 3) {
                        $order = "evaluates desc";
                    } else 
                        if ($id == 4) {
                            $order = "shares desc";
                        } else {
                            $id = 0;
                            $order = "";
                        }
        } else {
            $id = 0;
        }
        
        $page_index = request()->get('page', 1);
        $condition = array(
            "ng.state" => 1,
            "ng.point_exchange_type" => array(
                'NEQ',
                0
            )
        );
        $page_count = 25;
        $hotGoods = $this->goods->getGoodsList(1, 4, $condition, $order);
        $allGoods = $this->goods->getGoodsList($page_index, $page_count, $condition, $order);
        if ($page_index) {
            if (($page_index > 1 && $page_index <= $allGoods["page_count"])) {
                $page_index = 1;
            }
        }
        $this->assign("id", $id);
        $this->assign('page', $page_index);
        $this->assign("allGoods", $allGoods);
        $this->assign("hotGoods", $hotGoods);
        $this->assign('page_count', $allGoods['page_count']);
        $this->assign('total_count', $allGoods['total_count']);
        return view($this->style . 'Goods/integralCenter');
    }

    /**
     * 积分中心 全部积分商品
     *
     * @return \think\response\View
     */
    public function integralCenterList()
    {
        return view($this->style . 'Goods/integralCenterList');
    }

    /**
     * 积分中心全部商品Ajax
     */
    public function integralCenterListAjax()
    {
        $platform = new Platform();
        if (request()->isAjax()) {
            // 积分中心商品
            $this->goods = new GoodsService();
            $order = "";
            // 排序
            $id = request()->post('id', '');
            if ($id) {
                if ($id == 1) {
                    $order = "sales desc";
                } else 
                    if ($id == 2) {
                        $order = "collects desc";
                    } else 
                        if ($id == 3) {
                            $order = "evaluates desc";
                        } else 
                            if ($id == 4) {
                                $order = "shares desc";
                            } else {
                                $id = 0;
                                $order = "";
                            }
            } else {
                $id = 0;
            }
            
            $page_index = request()->post('page', '1');
            $condition = array(
                "ng.state" => 1,
                "ng.point_exchange_type" => array(
                    'NEQ',
                    0
                )
            );
            $page_count = 25;
            $allGoods = $this->goods->getGoodsList($page_index, $page_count, $condition, $order);
            return $allGoods['data'];
        }
    }

    /**
     * 设置点赞送积分
     */
    public function getClickPoint()
    {
        if (request()->isAjax()) {
            $shop_id = $this->instance_id;
            $uid = $this->uid;
            $goods_id = request()->post('goods_id', '');
            $goods = new GoodsService();
            $click_detail = $goods->getGoodsSpotFabulous($shop_id, $uid, $goods_id);
            if (empty($click_detail)) {
                $retval = $goods->setGoodsSpotFabulous($shop_id, $uid, $goods_id);
                if ($retval) {
                    $Config = new WebConfig();
                    $integralConfig = $Config->getIntegralConfig($this->instance_id);
                    if ($integralConfig['click_coupon'] == 1) {
                        $rewardRule = new PromoteRewardRule();
                        $result = $rewardRule->getRewardRuleDetail($this->instance_id);
                        if ($result['click_coupon'] != 0) {
                            $member = new Member();
                            $retval1 = $member->memberGetCoupon($this->uid, $result['click_coupon'], 2);
                        }
                    }
                }
                return AjaxReturn($retval);
            } else {
                return $retval = array(
                    "code" => - 1,
                    "message" => "您今天已经赞过该商品了"
                );
            }
        }
    }

    /**
     * 获取商品分类下的商品
     */
    public function getCategoryChildGoods()
    {
        if (request()->isAjax()) {
            $page = request()->post("page", 1);
            $category_id = request()->post("category_id", 0);
            $goods = new GoodsService();
            if ($category_id == 0) {
                $condition['ng.state'] = 1;
                $res = $goods->getGoodsList($page, PAGESIZE, $condition, "ng.sort asc,ng.create_time desc");
            } else {
                $condition['ng.category_id'] = $category_id;
                $condition['ng.state'] = 1;
                $res = $goods->getGoodsList($page, PAGESIZE, $condition, "ng.sort asc,ng.create_time desc");
            }
            return $res;
        }
    }

    /**
     * 查询商品的sku信息
     */
    public function getGoodsSkuInfo()
    {
        $goods_id = request()->post('goods_id', '');
        $this->goods = new GoodsService();
        return $this->goods->getGoodsAttribute($goods_id);
    }

    /**
     * 领取商品优惠劵
     */
    public function receiveGoodsCoupon()
    {
        if (request()->isAjax()) {
            $member = new Member();
            $coupon_type_id = request()->post("coupon_type_id", '');
            $res = $member->memberGetCoupon($this->uid, $coupon_type_id, 3);
            return AjaxReturn($res);
        }
    }

    /**
     * 商品组合套餐列表
     */
    public function comboPackageList()
    {
        $promotion = new Promotion();
        $goodsid = request()->get("goodsid", 0);
        if(empty($goodsid))  $this->error("未获取到套餐信息");
        
        $comboPackageGoodsArray = $promotion->getComboPackageGoodsArray($goodsid);
        $this->assign("comboPackageGoodsArray", $comboPackageGoodsArray);
        if (empty($comboPackageGoodsArray)) {
            $this->error("未获取到套餐信息");
        }
        return view($this->style . "Goods/comboPackageList");
    }

    /**
     * 弹出组合商品sku选择框
     *
     * @return \think\response\View
     */
    public function comboPackageSelectSku()
    {
        $goods = new GoodsService();
        $goods_id = request()->post('goods_id', '');
        $goods_detail = $goods->getGoodsDetail($goods_id);
        $this->assign("goods_detail", $goods_detail);
        $this->assign("shopname", $this->shop_name);
        return view($this->style . 'comboPackageSelectSku');
    }

    /**
     * 优惠券列表
     */
    public function couponList()
    {
        $promotion = new Promotion();
        if (request()->isAjax()) {
            $page_index = request()->post('page', 0);
            $order = request()->post('order', 0);
            $sort = request()->post('sort', 0);
            $condition = array();
            $condition["count"] = [
                "gt",
                0
            ];
            $condition["start_time"] = [
                "lt",
                time()
            ];
            $condition["end_time"] = [
                "gt",
                time()
            ];
            $condition["is_show"] = 1;
            $promotion_list = $promotion->getCouponTypeInfoList($page_index, $page_size = 8, $condition, $order = 'create_time asc');
            // var_dump($promotion_list);die;
            $this->assign('promotion_list', $promotion_list);
            return $promotion_list;
        }
        return view($this->style . 'Goods/CouponList');
    }

    /**
     * 领取优惠券
     */
    public function getCoupon()
    {
        $coupon_type_id = request()->get('coupon_type_id', "");
        if (! empty($coupon_type_id)) {
            $promotion = new Promotion();
            $condition['coupon_type_id'] = [
                'eq',
                $coupon_type_id
            ];
            $data = $promotion->getCouponTypeDetail($coupon_type_id);
            $this->assign('data', $data);
        } else {
            $this->error('当前页面不存在');
        }
        $path = $this->showMemberCouponQecode($coupon_type_id);
        $this->assign('code_path', $path);
        return view($this->style . 'Goods/getCoupon');
    }

    /**
     * 制作用户分享优惠券二维码
     */
    function showMemberCouponQecode($coupon_type_id)
    {
        $uid = ! empty($this->uid) ? $this->uid : 0;
        
        $url = __URL(__URL__ . '/wap/goods/getCoupon?coupon_type_id=' . $coupon_type_id . '&source_uid=' . $uid);
        
        // 查询并生成二维码
        
        $upload_path = "upload/qrcode/coupon_qrcode";
        if (! file_exists($upload_path)) {
            mkdir($upload_path, 0777, true);
        }
        $path = $upload_path . '/coupon_' . $coupon_type_id . '_' . $uid . '.png';
        if (! file_exists($path)) {
            getQRcode($url, $upload_path, "coupon_" . $coupon_type_id . '_' . $uid);
        }
        return $path;
    }

    /**
     * 拼团专区
     * 创建时间：2017年12月27日15:35:28
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function spellingGroupZone()
    {
        if (request()->isAjax()) {
            $pintuan = new Pintuan();
            $page_index = request()->post("page", 1);
            $condition = 'npg.is_open=1';
            $list = $pintuan->getTuangouGoodsList($page_index, PAGESIZE, $condition, 'npg.create_time desc');
            return $list;
        }
        $platform = new Platform();
        $spelling_group_zone_adv = $platform -> getPlatformAdvPositionDetailByApKeyword("spellingGroupZone");
        $this->assign("spelling_group_zone_adv", $spelling_group_zone_adv);
        return view($this->style . "Goods/spellingGroupZone");
    }

    /**
     * 标签专区
     */
    public function promotionZone()
    {
        $platform = new Platform();
        $goods = new GoodsService();
        // 品牌专区广告位
        $brand_adv = $platform->getPlatformAdvPositionDetailByApKeyword("goodsLabel");
        $this->assign('brand_adv', $brand_adv);
        
        if (request()->isAjax()) {
            $page_index = request()->get('page', '1');
            $group_id = request()->get("group_id", "");
            
            $this->goods = new GoodsService();
            
            if (! empty($group_id)) {
                $condition = "FIND_IN_SET(" . $group_id . ",ng.group_id_array) AND ng.state = 1";
            } else {
                $condition['ng.group_id_array'] = array(
                    'neq',
                    0
                );
                $condition['ng.state'] = 1;
            }
            
            $goods_list = $this->goods->getGoodsList($page_index, PAGESIZE, $condition, "", $group_id);
            return $goods_list;
        } else {
            // 标签列表
            $goods_group = new GoodsGroup();
            $groupList = $goods_group->getGoodsGroupList(1, 0, [
                'shop_id' => $this->instance_id,
                'pid' => 0
            ]);
            $this->assign("groupList", $groupList["data"]);
            return view($this->style . 'Goods/promotionZone');
        }
    }

    /**
     * 商品团购详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function groupPurchase()
    {
        $goods_id = request()->get('id', 0);
        if ($goods_id == 0) {
            $this->error("没有获取到商品信息");
        }
        
        $this->web_site = new WebSite();
        $goods = new GoodsService();
        $config_service = new WebConfig();
        $member = new Member();
        $shop_id = $this->instance_id;
        $uid = $this->uid;
        
        $web_info = $this->web_site->getWebSiteInfo();
        $group_id = request()->get("group_id", 0);
        
        // 切换到PC端
        if (! request()->isMobile() && $web_info['web_status'] == 1) {
            $redirect = __URL(__URL__ . "/goods/goodsinfo?goodsid=" . $goods_id);
            $this->redirect($redirect);
            exit();
        }
        
        // 清空待付款订单返回订单详情的标识
        $_SESSION['unpaid_goback'] = "";
        $_SESSION['order_create_flag'] = "";
        
        $goods_detail = $goods->getBasisGoodsDetail($goods_id);
        if (empty($goods_detail)) {
            $this->error("没有获取到商品信息");
        }
        //判断该团购活动状态
        if(empty($goods_detail['group_info'])){
            $redirect = __URL(__URL__ . "/wap/goods/goodsdetail?id=".$goods_id);
            $this->error("未找到该活动信息", $redirect);
        }
        // 商品点击量
        $goods->updateGoodsClicks($goods_id);
        
        // 是否是微信浏览器
        $this->assign("isWeixin", isWeixin());
        
        // 把属性值相同的合并
        $goods_attribute_list = $goods_detail['goods_attribute_list'];
        $goods_attribute_list_new = array();
        foreach ($goods_attribute_list as $item) {
            $attr_value_name = '';
            foreach ($goods_attribute_list as $key => $item_v) {
                if ($item_v['attr_value_id'] == $item['attr_value_id']) {
                    $attr_value_name .= $item_v['attr_value_name'] . ',';
                    unset($goods_attribute_list[$key]);
                }
            }
            if (! empty($attr_value_name)) {
                array_push($goods_attribute_list_new, array(
                    'attr_value_id' => $item['attr_value_id'],
                    'attr_value' => $item['attr_value'],
                    'attr_value_name' => rtrim($attr_value_name, ',')
                ));
            }
        }
        $goods_detail['goods_attribute_list'] = $goods_attribute_list_new;
        
        if($goods_detail['integral_give_type'] == 1 && $goods_detail['give_point'] > 0){
            $price = $goods_detail['promotion_price'] < $goods_detail['member_price'] ? $goods_detail['promotion_price'] : $goods_detail['member_price'];
            $goods_detail['give_point'] = round($price * $goods_detail['give_point'] * 0.01);
        }
        
        // 获取当前时间
        $current_time = $this->getCurrentTime();
        $this->assign('ms_time', $current_time);
        $this->assign("goods_detail", $goods_detail);
        $this->assign("shopname", $this->shop_name);
        $this->assign("price", intval($goods_detail["promotion_price"]));
        $this->assign("goods_id", $goods_id);
        $this->assign("title_before", $goods_detail['goods_name']);
        
        // 返回商品数量和当前商品的限购
        $this->getCartInfo($goods_id);
        
        // 分享
        $ticket = $this->getShareTicket();
        $this->assign("signPackage", $ticket);
        
        // 评价数量
        $evaluates_count = $goods->getGoodsEvaluateCount($goods_id);
        $this->assign('evaluates_count', $evaluates_count);
        
        // 评价
        $goodsEvaluation = "";
        $order = new OrderService();
        $goodsEvaluation = $order->getOrderEvaluateDataList(1, 1, [
            "goods_id" => $goods_id
        ], 'addtime desc');
        if (! empty($goodsEvaluation)) {
            $memberService = new Member();
            $goodsEvaluation["data"][0]["user_img"] = $memberService->getMemberImage($goodsEvaluation["data"][0]["uid"]);
            $this->assign("goodsEvaluation", $goodsEvaluation["data"][0]);
        } else {
            $this->assign("goodsEvaluation", $goodsEvaluation);
        }
        
        // 客服
        $customservice_config = $config_service->getcustomserviceConfig($shop_id);
        if (empty($customservice_config)) {
            $list['id'] = '';
            $list['value']['service_addr'] = '';
        }
        
        $this->assign("customservice_config", $customservice_config);
        // $this->assign('service_addr',$list['value']['service_addr']);
        // 查询点赞记录表，获取详情再判断当天该店铺下该商品该会员是否已点赞
        $click_detail = $goods->getGoodsSpotFabulous($shop_id, $uid, $goods_id);
        $this->assign('click_detail', $click_detail);
        
        // 当前用户是否收藏了该商品
        if (isset($uid)) {
            $is_member_fav_goods = $member->getIsMemberFavorites($uid, $goods_id, 'goods');
        }
        $this->assign("is_member_fav_goods", $is_member_fav_goods);
        
        // 获取商品的优惠劵
        $goods_coupon_list = $goods->getGoodsCoupon($goods_id, $this->uid);
        $this->assign("goods_coupon_list", $goods_coupon_list);
        
        // 组合商品
        $promotion = new Promotion();
        $comboPackageGoodsArray = $promotion->getComboPackageGoodsArray($goods_id);
        $this->assign("comboPackageGoodsArray", $comboPackageGoodsArray[0]);
        
        // 商品阶梯优惠
        $goodsLadderPreferentialList = $goods->getGoodsLadderPreferential([
            "goods_id" => $goods_id
        ], "quantity desc", "quantity,price");
        $this->assign("goodsLadderPreferentialList", array_reverse($goodsLadderPreferentialList));
        
        // 添加足迹
        if ($this->uid > 0) {
            $goods->addGoodsBrowse($goods_id, $this->uid);
        }
        // 商品标签
        $goods_group = new GoodsGroup();
        $goods_group_list = $goods_group->getGoodsGroupList(1, 0, [
            "group_id" => array(
                "in",
                $goods_detail["group_id_array"]
            )
        ], "", "group_name");
        $this->assign("goods_group_list", $goods_group_list["data"]);
        
        // 店铺服务
        $existingMerchant = $config_service->getExistingMerchantService($this->instance_id);
        $this->assign("existingMerchant", $existingMerchant);
        
        return view($this->style . 'Goods/groupPurchase');
    }

    /**
     * 团购专区
     */
    public function groupBuyingArea()
    {
        if (request()->post()) {
            $group_buy_service = new GroupBuy();
            $page = request()->post('page', 1);
            $condition = array(
                "state" => 1,
                "npgb.start_time" => array(
                    "<",
                    time()
                ),
                "npgb.end_time" => array(
                    ">",
                    time()
                )
            );
            $field = 'ng.goods_id,ng.promotion_price,ng.goods_name,ng.picture,npgb.group_id,npgb.group_name,npgb.shop_id,npgb.goods_id,npgb.start_time,npgb.end_time,npgb.max_num,npgb.min_num,npgb.status';
            $group_goods_list = $group_buy_service->getPromotionGroupBuyGoodsList($page, PAGESIZE, $condition, 'npgb.group_id desc', $field);
            return $group_goods_list;
        } else {
            
            $this->assign("title_before", "团购专区");
            return view($this->style . 'Goods/groupBuyingArea');
        }
    }

    /**
     * 积分兑换
     *
     * @return \think\response\View
     */
    public function goodsDetailPointExchange()
    {
        $goods_id = request()->get('id', 0);
        if ($goods_id == 0) {
            $this->error("没有获取到商品信息");
        }
        
        $this->web_site = new WebSite();
        $goods = new GoodsService();
        $config_service = new WebConfig();
        $member = new Member();
        $shop_id = $this->instance_id;
        $uid = $this->uid;
        
        $web_info = $this->web_site->getWebSiteInfo();
        $group_id = request()->get("group_id", 0);
        
        // 切换到PC端
        if (! request()->isMobile() && $web_info['web_status'] == 1) {
            $redirect = __URL(__URL__ . "/goods/goodsinfo?goodsid=" . $goods_id);
            $this->redirect($redirect);
            exit();
        }
        
        // 清空待付款订单返回订单详情的标识
        $_SESSION['unpaid_goback'] = "";
        $_SESSION['order_create_flag'] = "";
        
        $goods_detail = $goods->getBasisGoodsDetail($goods_id);
        if (empty($goods_detail)) {
            $this->error("没有获取到商品信息");
        }
        if ($this->getIsOpenVirtualGoodsConfig() == 0 && $goods_detail['goods_type'] == 0) {
            $this->error("未开启虚拟商品功能");
        }
        // 商品点击量
        $goods->updateGoodsClicks($goods_id);
        
        // 是否是微信浏览器
        $this->assign("isWeixin", isWeixin());
        
        // 把属性值相同的合并
        $goods_attribute_list = $goods_detail['goods_attribute_list'];
        $goods_attribute_list_new = array();
        foreach ($goods_attribute_list as $item) {
            $attr_value_name = '';
            foreach ($goods_attribute_list as $key => $item_v) {
                if ($item_v['attr_value_id'] == $item['attr_value_id']) {
                    $attr_value_name .= $item_v['attr_value_name'] . ',';
                    unset($goods_attribute_list[$key]);
                }
            }
            if (! empty($attr_value_name)) {
                array_push($goods_attribute_list_new, array(
                    'attr_value_id' => $item['attr_value_id'],
                    'attr_value' => $item['attr_value'],
                    'attr_value_name' => rtrim($attr_value_name, ',')
                ));
            }
        }
        $goods_detail['goods_attribute_list'] = $goods_attribute_list_new;
        
        if($goods_detail['integral_give_type'] == 1 && $goods_detail['give_point'] > 0){
            $price = $goods_detail['promotion_price'] < $goods_detail['member_price'] ? $goods_detail['promotion_price'] : $goods_detail['member_price'];
            $goods_detail['give_point'] = round($price * $goods_detail['give_point'] * 0.01);
        }
        
        // 获取当前时间
        $current_time = $this->getCurrentTime();
        $this->assign('ms_time', $current_time);
        $this->assign("goods_detail", $goods_detail);
        $this->assign("shopname", $this->shop_name);
        $this->assign("price", intval($goods_detail["promotion_price"]));
        $this->assign("goods_id", $goods_id);
        $this->assign("title_before", $goods_detail['goods_name']);
        
        // 返回商品数量和当前商品的限购
        $this->getCartInfo($goods_id);
        
        // 分享
        $ticket = $this->getShareTicket();
        $this->assign("signPackage", $ticket);
        
        // 评价数量
        $evaluates_count = $goods->getGoodsEvaluateCount($goods_id);
        $this->assign('evaluates_count', $evaluates_count);
        
        // 评价
        $goodsEvaluation = "";
        $order = new OrderService();
        $goodsEvaluation = $order->getOrderEvaluateDataList(1, 1, [
            "goods_id" => $goods_id
        ], 'addtime desc');
        if (! empty($goodsEvaluation)) {
            $memberService = new Member();
            $goodsEvaluation["data"][0]["user_img"] = $memberService->getMemberImage($goodsEvaluation["data"][0]["uid"]);
            $this->assign("goodsEvaluation", $goodsEvaluation["data"][0]);
        } else {
            $this->assign("goodsEvaluation", $goodsEvaluation);
        }
        
        // 客服
        $customservice_config = $config_service->getcustomserviceConfig($shop_id);
        if (empty($customservice_config)) {
            $list['id'] = '';
            $list['value']['service_addr'] = '';
        }
        
        $this->assign("customservice_config", $customservice_config);
        // $this->assign('service_addr',$list['value']['service_addr']);
        // 查询点赞记录表，获取详情再判断当天该店铺下该商品该会员是否已点赞
        $click_detail = $goods->getGoodsSpotFabulous($shop_id, $uid, $goods_id);
        $this->assign('click_detail', $click_detail);
        
        // 当前用户是否收藏了该商品
        if (isset($uid)) {
            $is_member_fav_goods = $member->getIsMemberFavorites($uid, $goods_id, 'goods');
        }
        $this->assign("is_member_fav_goods", $is_member_fav_goods);
        
        // 获取商品的优惠劵
        $goods_coupon_list = $goods->getGoodsCoupon($goods_id, $this->uid);
        $this->assign("goods_coupon_list", $goods_coupon_list);
        
        // 组合商品
        $promotion = new Promotion();
        $comboPackageGoodsArray = $promotion->getComboPackageGoodsArray($goods_id);
        $this->assign("comboPackageGoodsArray", $comboPackageGoodsArray[0]);
        
        // 商品阶梯优惠
        $goodsLadderPreferentialList = $goods->getGoodsLadderPreferential([
            "goods_id" => $goods_id
        ], "quantity desc", "quantity,price");
        $this->assign("goodsLadderPreferentialList", array_reverse($goodsLadderPreferentialList));
        
        // 添加足迹
        if ($this->uid > 0) {
            $goods->addGoodsBrowse($goods_id, $this->uid);
        }
        // 商品标签
        $goods_group = new GoodsGroup();
        $goods_group_list = $goods_group->getGoodsGroupList(1, 0, [
            "group_id" => array(
                "in",
                $goods_detail["group_id_array"]
            )
        ], "", "group_name");
        $this->assign("goods_group_list", $goods_group_list["data"]);
        
        // 店铺服务
        $existingMerchant = $config_service->getExistingMerchantService($this->instance_id);
        $this->assign("existingMerchant", $existingMerchant);
        
        // 积分抵现比率
        $integral_balance = 0; // 积分可抵金额
        $point_config = $promotion->getPointConfig();
        if ($point_config["is_open"] == 1) {
            if ($goods_detail['max_use_point'] > 0 && $point_config['convert_rate'] > 0) {
                $integral_balance = $goods_detail['max_use_point'] * $point_config['convert_rate'];
            }
        }
        $this->assign("integral_balance", $integral_balance);
        
        return view($this->style . 'Goods/goodsDetailPointExchange');
    }

    /**
     * 专题活动列表页面
     */
    public function promotionTopic()
    {
        $platform = new Platform();
        // 专题活动广告位
        $topic_adv = $platform->getPlatformAdvPositionDetailByApKeyword("wapPromotionTopic");
        $this->assign('topic_adv', $topic_adv);
        
        // dump($brand_adv);
        $promotion = new Promotion();
        $list = $promotion->getPromotionTopicList(1, 0, [
            'status' => 1,
            "start_time" => array(
                "<",
                time()
            ),
            "end_time" => array(
                ">",
                time()
            )
        ]);
        $this->assign('info', $list);
        $this->assign('total_count', count($list['data']));
        // dump($list);
        return view($this->style . 'Goods/promotionTopic');
    }

    public function promotionTopicGoods()
    {
        $topic_id = request()->get('topic_id', 0);
        
        if (! is_numeric($topic_id)) {
            $this->error("没有获取到专题信息");
        }
        $promotion = new Promotion();
        $topic_goods = $promotion->getPromotionTopicDetail($topic_id);
        // dump($topic_goods);
        $this->assign('info', $topic_goods);
        return view($this->style . 'Goods/' . $topic_goods['wap_topic_template']);
    }
    
    /**
     * 砍价商品列表
     */
    public function bargainList(){
        if(request()->isAjax()){
            $page_index = request()->post('page', 1);
            $condition = array(
        		"status" => 1,
            );
            $bargain = new Bargain();
            $list = $bargain->getBargainGoodsPage($page_index, PAGESIZE, $condition);
            return $list;
        }
        return view($this->style.'Goods/bargainList');
    }
    
    /**
     * 砍价商品详情
     */
    public function bargainGoodsInfo(){
        return view($this->style.'Goods/bargainGoodsInfo');
    }
    
    
    /**
     * 砍价商品发起
     */
    public function addBargainLaunch()
    {
    	$bargain = new Bargain();
    	if(request()->isAjax()){
    		$bargain_id = request()->post('bargain_id', '');
    		$sku_id = request()->post('sku_id', '');
    		$address_id = request()->post('address_id', '');
    		$distribution_type = request()->post('distribution_type', '');
    		$launch_id = $bargain->addBargainLaunch($bargain_id, $sku_id, $address_id, $distribution_type);
    		return $launch_id;
    	}
    }
    
    /**
     * 砍价商品发起页面
     */
    public function bargainLaunch()
    {
        // 分享
        $ticket = $this->getShareTicket();
        $this->assign("signPackage", $ticket);
        
        $bargain = new Bargain();
        
    	if(request()->isAjax()){
    		$launch_id = request()->post('launch_id', 0);
    		//发起的活动信息
    		$res = $bargain->addBargainPartake($launch_id);
    		return $res;
    	}
    	
		$launch_id = request()->get('launch_id', 0);
		if(empty($launch_id) ||  !is_numeric($launch_id)){
			$this->error('页面不存在');
		}
		$member  =new Member();
		$user = new User();
		$launch_info = $bargain->getBargainLaunchInfo($launch_id);
		//砍价主用户信息
		$user_info = $user->getUserInfoByUid($launch_info['uid']);
		$is_seif = 1;
		
		if($this->uid != $launch_info['uid']){
			//说明是分享出去的砍刀
			$is_seif = 0;
		}
		$this->assign('is_seif' , $is_seif);
		//分享出去的需要手动砍刀

		//砍价的商品信息
		$goods_info = $bargain->getBragainBySkuGoodsInfo($launch_info['bargain_id'], $launch_info['sku_id']);
		
		$partake = $bargain->getConfig();
		$surplus = number_format($launch_info['goods_money']-$launch_info['bargain_money'] - $launch_info['bargain_min_money'], 2 , "." , "");
		$launch_info['surplus'] = $surplus;
		$this->assign('partake_info', $partake);
		$this->assign('user_info', $user_info);
		$this->assign('launch_info', $launch_info);
		$this->assign('goods_info', $goods_info);
		$this->assign('launch_id', $launch_id);

		//参与该活动的商品详情
		$bargain_goods_info = $bargain->getBargainGoodsInfo($launch_info['bargain_id'], $launch_info['goods_id']);
	
		$this->assign('bargain_goods_info', $bargain_goods_info);
		
		//参团列表
		$partake_list = $bargain->getBargainPartakeList($launch_id);
		//         dump($partake_list);die;
		$this->assign('partake_list', $partake_list);
		
		$is_max_partake = $bargain->getBragainLaunchIsPartakeMax($this->uid, $launch_id);
		$this->assign('is_max_partake', $is_max_partake);
		return view($this->style.'Goods/bargainLaunch');
	}
}