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
namespace app\api\controller;

use data\service\GoodsCategory;
use data\service\GoodsBrand;
use data\service\Goods as GoodsService;
use data\service\promotion\GoodsExpress;
use data\service\Address;
use data\service\Order;
use data\service\Platform;
use data\service\Member;
use data\service\Config as WebConfig;
use data\service\promotion\PromoteRewardRule;
use data\service\Config;
use data\service\Promotion;
use data\service\Pintuan;
use data\service\GoodsGroup;
use data\service\WebSite;
use data\service\GroupBuy;
use data\service\Bargain;

class Goods extends BaseController
{

    /**
     * 获取首页商品分类楼层
     *
     * @param unknown $shop_id
     *            店铺id，默认0
     * @param unknown $num
     *            查询商品数量
     */
    public function getGoodsCategoryBlockQuery()
    {
        $title = "获取首页商品分类楼层";
        $shop_id = request()->post('shop_id', 0);
        $num = request()->post('num', 4);
        $goods_category = new GoodsCategory();
        $block_list = $goods_category->getGoodsCategoryBlockQuery($shop_id, $num);
        return $this->outMessage($title, $block_list);
    }

    /**
     * 获取商品品牌列表
     */
    public function getGoodsBrandList()
    {
        $title = "获取商品品牌列表";
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post("page_size", 0);
        $condition = request()->post("condition", '');
        $order = request()->post('order', '');
        $goods_brand = new GoodsBrand();
        $list = $goods_brand->getGoodsBrandList($page_index, $page_size, $condition, $order);
        return $this->outMessage($title, $list);
    }

    /**
     * 品牌专区信息
     */
    public function getBrandListInfo()
    {
        $title = "获取品牌专区信息";
        $platform = new Platform();
        $goods = new GoodsService();
        $goods_category = new GoodsCategory();
        $goods_brand = new GoodsBrand();
        
        // 品牌专区广告位
        $brand_adv = $platform->getPlatformAdvPositionDetail(1162);
        if (! empty($brand_adv['adv_list'])) {
            foreach ($brand_adv['adv_list'] as $k => $v) {
                if (strpos($brand_adv['adv_list'][$k]['adv_image'], "http://") === false && strpos($brand_adv['adv_list'][$k]['adv_image'], "https://") === false) {
                    $brand_adv['adv_list'][$k]['adv_image'] = getBaseUrl() . "/" . $brand_adv['adv_list'][$k]['adv_image'];
                }
            }
        }
        
        $goods_category_list_1 = $goods_category->getGoodsCategoryList(1, 0, [
            "is_visible" => 1,
            "level" => 1
        ]);
        $goods_brand_list = $goods_brand->getGoodsBrandList(1, 0, '', 'brand_initial asc');
        $data = array(
            'brand_adv' => $brand_adv,
            'goods_category_list_1' => $goods_category_list_1,
            'goods_brand_list' => $goods_brand_list
        );
        
        return $this->outMessage($title, $data);
    }

    /**
     * 品牌专区商品列表
     */
    public function getBrandGoodsList()
    {
        $title = "获取品牌商品列表";
        $brand_id = request()->post("brand_id", 0);
        if ($brand_id == 0) {
            return $this->outMessage($title, null, '-50', "无法获取品牌信息");
        }
        $page_index = request()->post("page", 1);
        $goods = new GoodsService();
        if (! empty($brand_id)) {
            $condition['ng.brand_id'] = $brand_id;
        }
        $condition['ng.state'] = 1;
        $list = $goods->getGoodsList($page_index, PAGESIZE, $condition, "ng.sort asc,ng.create_time desc");
        if (! empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                if (strpos($list['data'][$k]['pic_cover_small'], "http://") === false && strpos($list['data'][$k]['pic_cover_small'], "https://") === false) {
                    $list['data'][$k]['pic_cover_small'] = getBaseUrl() . "/" . $list['data'][$k]['pic_cover_small'];
                }
            }
        }
        return $this->outMessage($title, $list);
    }

    /**
     * 团购专区广告位
     */
    public function getGroupPurchaseAdv()
    {
        $title = "团购专区广告位";
        $platform = new Platform();
        // 团购专区广告位
        $group_purchase_adv = $platform->getPlatformAdvPositionDetailByApKeyword('groupBuyArea');
        $data = array(
            'group_purchase_adv' => $group_purchase_adv
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 团购专区商品列表
     */
    public function getGroupPurchaseGoodsList()
    {
        $title = "团购专区商品列表";
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
        return $this->outMessage($title, $group_goods_list);
    }

    /**
     * 商品详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function goodsDetail()
    {
        $title = "获取商品详情";
        $goods_id = request()->post('id', 0);
        if ($goods_id == 0) {
            return $this->outMessage($title, '', '-50', "无法获取商品信息");
        }
        
        $this->web_site = new WebSite();
        $goods = new GoodsService();
        $config_service = new WebConfig();
        $member = new Member();
        
        $group_id = request()->post('group_id', 0);
        $bargain_id = request()->post('bargain_id', 0);
        $shop_id = $this->instance_id;
        $uid = $this->uid;
        
        $goods_detail = $goods->getBasisGoodsDetail($goods_id);
        if (empty($goods_detail)) {
            return $this->outMessage($title, '', '-50', "无法获取商品信息");
        }
        if ($this->getIsOpenVirtualGoodsConfig() == 0 && $goods_detail['goods_type'] == 0) {
            return $this->outMessage($title, '', '-50', "未开启虚拟商品功能");
        }
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
        $evaluates_count = $goods->getGoodsEvaluateCount($goods_id);
        $goods_detail['evaluates_count'] = $evaluates_count;
        if (! empty($this->uid)) {
            // 查询点赞记录表，获取详情再判断当天该店铺下该商品该会员是否已点赞
            $click_detail = $goods->getGoodsSpotFabulous($this->instance_id, $this->uid, $goods_id);
            
            $member = new Member();
            // 当前用户是否收藏了该商品
            $is_member_fav_goods = $member->getIsMemberFavorites($this->uid, $goods_id, 'goods');
            
            $cartlist = $goods->getCart($this->uid);
            $cart_count = count($cartlist);
        } else {
            $click_detail = array();
            $is_member_fav_goods = array();
            $cart_count = 0;
        }
        
        // 获取商品的优惠劵
        $goods_coupon_list = $goods->getGoodsCoupon($goods_id, $this->uid);
        $goods_detail["goods_coupon_list"] = $goods_coupon_list;
        
        // 组合商品
        $promotion = new Promotion();
        $comboPackageGoodsArray = $promotion->getComboPackageGoodsArray($goods_id);
        $goods_detail["comboPackageGoodsArray"] = $comboPackageGoodsArray[0];
        
        // 商品阶梯优惠
        $goodsLadderPreferentialList = $goods->getGoodsLadderPreferential([
            "goods_id" => $goods_id
        ], "quantity desc", "quantity,price");
        $goods_detail["goodsLadderPreferentialList"] = array_reverse($goodsLadderPreferentialList);
        
        // 添加点击量
        $goods->updateGoodsClicks($goods_id);
        // 添加足迹
        if ($this->uid > 0) {
            $goods->addGoodsBrowse($goods_id, $this->uid);
        }
        // 获取当前时间
        $current_time = $this->getCurrentTime();
        
        $goods_detail['member_price'] = number_format($goods_detail['member_price'], 2);
        // 店铺服务
        $existingMerchant = $config_service->getExistingMerchantService($this->instance_id);
        
        // 判断当前商品是否有拼团
        if (empty($goods_detail["wap_custom_template"])) {
            // start 砍价
            $is_support_bargain = IS_SUPPORT_BARGAIN;
            $is_bargain = 0;
            if (! empty($bargain_id) && $is_support_bargain == 1) {
                $bargain = new Bargain();
                $bargain_config = $bargain->getConfig();
                if ($bargain_config['is_use'] == 1) {
                    // 砍价商品详情
                    $goods_bargain = $bargain->getBargainGoodsInfo($bargain_id, $goods_id);
                    $is_bargain = $goods_bargain['status'] == 1 ? 1 : 0;
                }
            }
            if ($is_bargain == 1) {
                $goods_detail['addresslist'] = $member->getMemberExpressAddressList();
                $goods_detail['goods_bargain'] = $goods_bargain;
                $goods_detail['bargain_id'] = $bargain_id;
                // end 砍价
            } else {
                $is_support_pintuan = IS_SUPPORT_PINTUAN;
                if ($is_support_pintuan == 1) {
                    $pintuan = new Pintuan();
                    $goods_pintuan = $pintuan->getGoodsPintuanDetail($goods_id);
                    if ($goods_pintuan['is_open']) {
                        // 商品拼团详情
                        $goods_pintuan['tuangou_content_json'] = json_decode($goods_pintuan['tuangou_content_json'], true);
                        $pintuan_condition['goods_id'] = $goods_id;
                        $pintuan_condition['status'] = 1;
                        $goods_pintuan['pintuan_list'] = $pintuan->getGoodsPintuanStatusList(1, 0, $pintuan_condition, 'create_time desc');
                        $goods_detail['goods_pintuan'] = $goods_pintuan;
                        // 检查当前拼团是否存在
                        $tuangou_group_count = 0;
                        if ($group_id > 0) {
                            $tuangou_group_count = $pintuan->getTuangouGroupCount($group_id, $goods_id);
                        }
                        $goods_detail['tuangou_group_count'] = $tuangou_group_count;
                        $goods_detail['group_id'] = $group_id;
                    }
                }
            }
        }
        // 积分配置
        $max_use_point = $goods_detail['max_use_point'];
        if (! empty($max_use_point)) {
            $point_config = $promotion->getPointConfig();
            if ($max_use_point == 0 || $point_config['convert_rate'] == 0) {
                $point_config["is_open"] = 0;
            }
            $goods_detail["point_config"] = $point_config;
        }
        $goods_detail['is_bargain'] = $is_bargain;
        $goods_detail['existingMerchant'] = $existingMerchant;
        $goods_detail['click_detail'] = $click_detail;
        $goods_detail['is_member_fav_goods'] = $is_member_fav_goods;
        $goods_detail['cart_count'] = $cart_count;
        $goods_detail['current_time'] = $current_time;
        return $this->outMessage($title, $goods_detail);
    }

    /**
     * 商品详情，APP专用
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function goodsDetailForApp()
    {
        $title = "获取商品详情";
        $goods_id = request()->post('id', 70);
        if ($goods_id == 0) {
            return $this->outMessage($title, '', '-50', "无法获取商品信息");
        }
        
        $this->web_site = new WebSite();
        $goods = new GoodsService();
        $config_service = new WebConfig();
        $member = new Member();
        
        $group_id = request()->post('group_id', 0);
        $shop_id = $this->instance_id;
        $uid = $this->uid;
        
        $goods_detail = $goods->getBasisGoodsDetailForApp($goods_id);
        
        if (empty($goods_detail)) {
            return $this->outMessage($title, '', '-50', "无法获取商品信息");
        }
        if ($this->getIsOpenVirtualGoodsConfig() == 0 && $goods_detail['goods_type'] == 0) {
            return $this->outMessage($title, '', '-50', "未开启虚拟商品功能");
        }
        // 把属性值相同的合并
        $goods_attribute_list = $goods_detail['goods_attribute_list'];
        if (! empty($goods_attribute_list)) {
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
        }
        $evaluates_count = $goods->getGoodsEvaluateCount($goods_id);
        if (! empty($evaluates_count)) {
            $goods_detail['evaluates_count'] = $evaluates_count;
        }
        if (! empty($this->uid)) {
            // 查询点赞记录表，获取详情再判断当天该店铺下该商品该会员是否已点赞
            // $click_detail = $goods->getGoodsSpotFabulous($this->instance_id, $this->uid, $goods_id);
            
            $member = new Member();
            // 当前用户是否收藏了该商品
            $is_member_fav_goods = $member->getIsMemberFavorites($this->uid, $goods_id, 'goods');
            
            $cartlist = $goods->getCart($this->uid);
            $cart_count = count($cartlist);
            
            // if (! empty($click_detail)) {
            // $goods_detail['click_detail'] = $click_detail;
            // }
            $goods_detail['is_member_fav_goods'] = $is_member_fav_goods;
            $goods_detail['cart_count'] = $cart_count;
        }
        
        // 获取商品的优惠劵
        $goods_coupon_list = $goods->getGoodsCoupon($goods_id, $this->uid);
        if (! empty($goods_coupon_list)) {
            $goods_detail["goods_coupon_list"] = $goods_coupon_list;
        }
        
        // 组合商品
        $promotion = new Promotion();
        $comboPackageGoodsArray = $promotion->getComboPackageGoodsArray($goods_id);
        if (! empty($comboPackageGoodsArray)) {
            $goods_detail["comboPackageGoodsArray"] = $comboPackageGoodsArray[0];
        }
        
        // 商品阶梯优惠
        $goodsLadderPreferentialList = $goods->getGoodsLadderPreferential([
            "goods_id" => $goods_id
        ], "quantity desc", "quantity,price");
        if (! empty($goodsLadderPreferentialList)) {
            $goods_detail["goodsLadderPreferentialList"] = array_reverse($goodsLadderPreferentialList);
        }
        
        // 添加点击量
        $goods->updateGoodsClicks($goods_id);
        // 添加足迹
        if ($this->uid > 0) {
            $goods->addGoodsBrowse($goods_id, $this->uid);
        }
        // 获取当前时间
        $current_time = $this->getCurrentTime();
        
        $goods_detail['member_price'] = number_format($goods_detail['member_price'], 2);
        
        // 店铺服务
        $existingMerchant = $config_service->getExistingMerchantService($this->instance_id);
        if (! empty($existingMerchant)) {
            $goods_detail['existingMerchant'] = $existingMerchant;
        }
        
        // 判断当前商品是否有拼团
        if (empty($goods_detail["wap_custom_template"])) {
            $is_support_pintuan = IS_SUPPORT_PINTUAN;
            if ($is_support_pintuan == 1) {
                $pintuan = new Pintuan();
                $goods_pintuan = $pintuan->getGoodsPintuanDetail($goods_id);
                if ($goods_pintuan['is_open']) {
                    // 商品拼团详情
                    $goods_pintuan['tuangou_content_json'] = json_decode($goods_pintuan['tuangou_content_json'], true);
                    $pintuan_condition['goods_id'] = $goods_id;
                    $pintuan_condition['status'] = 1;
                    $goods_pintuan['pintuan_list'] = $pintuan->getGoodsPintuanStatusList(1, 0, $pintuan_condition, 'create_time desc');
                    $goods_detail['goods_pintuan'] = $goods_pintuan;
                    // 检查当前拼团是否存在
                    $tuangou_group_count = 0;
                    if ($group_id > 0) {
                        $tuangou_group_count = $pintuan->getTuangouGroupCount($group_id, $goods_id);
                    }
                    $goods_detail['tuangou_group_count'] = $tuangou_group_count;
                    $goods_detail['group_id'] = $group_id;
                }
            }
        }
        // 积分配置
        $max_use_point = $goods_detail['max_use_point'];
        if (! empty($max_use_point)) {
            $point_config = $promotion->getPointConfig();
            if ($max_use_point == 0 || $point_config['convert_rate'] == 0) {
                $point_config["is_open"] = 0;
            }
            $goods_detail["point_config"] = $point_config;
        }
        $goods_detail['current_time'] = $current_time;
        return $this->outMessage($title, $goods_detail);
    }

    /**
     * 根据定位查询当前商品的运费
     * 创建时间：2017年9月29日 15:12:55 王永杰
     */
    public function getShippingFeeNameByLocation()
    {
        $title = '根据定位查询当前商品运费';
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
                
                if (! empty($express) && is_string($express)) {
                    if (is_string($express)) {
                        $express = str_replace('￥', '', $express);
                    }
                    $new_express = array();
                    $new_express[0] = $express == '免邮' ? array(
                        'co_id' => 0,
                        'express_fee' => '免邮'
                    ) : array(
                        'co_id' => 0,
                        'express_fee' => $express
                    );
                    $express = $new_express;
                }
                return $this->outMessage($title, $express);
            }
        }
    }

    /**
     * 商品组合套餐列表
     */
    public function comboPackageList()
    {
        $title = '商品组合套餐列表';
        $promotion = new Promotion();
        $goods_id = request()->post("goods_id", 0);
        if (empty($goods_id)) {
            return $this->outMessage($title, '', - 50, '无法获取商品信息');
        }
        $comboPackageGoodsArray = $promotion->getComboPackageGoodsArray($goods_id);
        $this->assign("comboPackageGoodsArray", $comboPackageGoodsArray);
        $data = array(
            "comboPackageGoodsArray" => $comboPackageGoodsArray
        );
        if (empty($comboPackageGoodsArray)) {
            return $this->outMessage($title, '', - 10, '未获取到套餐信息');
        }
        return $this->outMessage($title, $data);
    }

    /**
     * 弹出组合商品sku选择框
     *
     * @return \think\response\View
     */
    public function comboPackageSelectSku()
    {
        $title = '组合商品规格';
        $goods = new GoodsService();
        $goods_id = request()->post('goods_id', '');
        if (empty($goods_id)) {
            return $this->outMessage($title, '', - 50, '无法获取商品信息');
        }
        $goods_detail = $goods->getGoodsDetail($goods_id);
        if (empty($goods_detail)) {
            return $this->outMessage($title, '', - 10, '未获取到套餐信息');
        }
        $data = array(
            "goods_detail" => $goods_detail,
            "shopname" => $this->shop_name
        );
        return $this->outMessage($title, $data);
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
     * 获取评论统计
     *
     * @return \think\response\Json
     */
    public function getGoodsEvaluateCount()
    {
        $title = "获取评论统计，需要必填参数goods_id";
        $goods_id = request()->post('goods_id', 0);
        if ($goods_id == 0) {
            return $this->outMessage($title, '', '-50', "无法获取商品信息");
        }
        $goods = new GoodsService();
        $evaluates_count = $goods->getGoodsEvaluateCount($goods_id);
        return $this->outMessage($title, $evaluates_count);
    }

    /**
     * 根据定位查询当前商品的运费
     * 创建时间：2017年9月29日 15:12:55
     */
    public function getShippingFeeByAddressName()
    {
        $title = "根据地址查询当前商品的运费,传入地址信息名称";
        $goods_id = request()->post("goods_id", "");
        $province = request()->post("province", "");
        $city = request()->post("city", "");
        $express = "";
        if (! empty($goods_id)) {
            $goods_express = new GoodsExpress();
            $address = new Address();
            $province_id = $address->getProvinceId($province);
            $city_id = $address->getCityId($city);
            $district_id = $address->getCityFirstDistrict($city_id['city_id']);
            $express = $goods_express->getGoodsExpressTemplate($goods_id, $province_id['province_id'], $city_id['city_id'], $district_id);
        }
        
        return $this->outMessage($title, $express);
    }

    /**
     * 根据地址id查询当前商品的运费
     * 创建时间：2017年9月29日 15:12:55
     */
    public function getShippingFeeByAddressId()
    {
        $title = "根据地址查询当前商品的运费,传入地址信息id";
        $goods_id = request()->post("goods_id", "");
        $province = request()->post("province", "");
        $city = request()->post("city", "");
        $district = request()->post("district", '');
        $express = "";
        if (! empty($goods_id)) {
            $goods_express = new GoodsExpress();
            $express = $goods_express->getGoodsExpressTemplate($goods_id, $province, $city, $district);
        }
        
        return $this->outMessage($title, $express);
    }

    /**
     * 功能：商品评论
     * 创建人：李志伟
     * 创建时间：2017年2月23日11:12:57
     */
    public function getGoodsComments()
    {
        $title = "获取商品评论,传入商品参数商品id，comments_type:1,2,3";
        $comments_type = request()->post('comments_type', '');
        $condition['goods_id'] = request()->post('goods_id', '');
        $page = request()->post('page', 1);
        if (empty($condition['goods_id'])) {
            return $this->outMessage($title, "", '-50', "无法获取商品信息");
        }
        $order = new Order();
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
        $goodsEvaluationList = $order->getOrderEvaluateDataList($page, PAGESIZE, $condition, 'addtime desc');
        // 查询评价用户的头像
        $memberService = new Member();
        foreach ($goodsEvaluationList['data'] as $v) {
            $v["user_img"] = $memberService->getMemberImage($v["uid"]);
        }
        return $this->outMessage($title, $goodsEvaluationList);
    }

    /**
     * 返回商品数量和当前商品的限购
     *
     * @param unknown $goods_id            
     */
    public function getGoodsCartInfo()
    {
        $title = "获取当前会员针对当前商品购物车数量以及限购数量";
        $goods_id = request()->post("goods_id", "");
        $uid = $this->uid;
        if (empty($goods_id)) {
            return $this->outMessage($title, "", '-50', "无法获取商品信息");
        }
        if (empty($uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息!");
        }
        $applet_goods = new GoodsService();
        $cartlist = $applet_goods->getCart($uid);
        $num = 0;
        foreach ($cartlist as $v) {
            if ($v["goods_id"] == $goods_id) {
                $num = $v["num"];
            }
        }
        $data = array(
            'cartcount' => count($cartlist),
            'num' => $num
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 购物车
     */
    public function cart()
    {
        $title = "获取购物车信息,需要会员登录";
        $uid = $this->uid;
        if (empty($uid)) {
            return $this->outMessage($title, null, '-9999', "无法获取会员登录信息!");
        }
        $goods = new GoodsService();
        $cartlist = $goods->getCart($uid, $this->instance_id);
        // 店铺，店铺中的商品
        $list = Array();
        $list['cart_list'] = $cartlist;
        
        foreach ($list['cart_list'] as $k => $v) {
            
            if (! empty($list['cart_list'][$k]['picture_info']['pic_cover_small'])) {
                if (strpos($list['cart_list'][$k]['picture_info']['pic_cover_small'], "http://") === false && strpos($list['cart_list'][$k]['picture_info']['pic_cover_small'], "https://") === false) {
                    $list['cart_list'][$k]['picture_info']['pic_cover_small'] = getBaseUrl() . "/" . $list['cart_list'][$k]['picture_info']['pic_cover_small'];
                }
            }
        }
        
        // 商品阶梯优惠信息
        $goods_ladder_preferential = array();
        if (count($cartlist) > 0) {
            foreach ($cartlist as $v) {
                $goods_ladder_preferential[] = $goods->getGoodsLadderPreferential([
                    "goods_id" => $v["goods_id"]
                ], "quantity desc");
            }
        }
        if (! empty($goods_ladder_preferential)) {
            $list['goods_ladder_preferential'] = json_encode($goods_ladder_preferential);
        } else {
            $list['goods_ladder_preferential'] = "";
        }
        return $this->outMessage($title, $list);
    }

    /**
     * 添加购物车
     * 创建人：李广
     */
    public function addCart()
    {
        $title = "添加购物车,需要会员登录，以及cart_detail:注意是json序列";
        $cart_detail = request()->post('cart_detail', '');
        $uid = $this->uid;
        if (! empty($cart_detail)) {
            $cart_detail = json_decode($cart_detail, true);
        } else {
            return $this->outMessage($title, "", '-50', "无法获取购物车信息!");
        }
        if (empty($uid)) {
            return $this->outMessage($title, "", '-9999', "无法获取会员登录信息!");
        }
        $shop_id = $cart_detail["shop_id"];
        $shop_name = $cart_detail["shop_name"];
        $goods_id = $cart_detail['trueId'];
        $goods_name = $cart_detail['goods_name'];
        $num = $cart_detail['count'];
        $sku_id = $cart_detail['select_skuid'];
        $sku_name = $cart_detail['select_skuName'];
        $price = $cart_detail['price'];
        $picture = $cart_detail['picture'];
        $goods = new GoodsService();
        $retval = $goods->addCart($uid, $shop_id, $shop_name, $goods_id, $goods_name, $sku_id, $sku_name, $price, $num, $picture, 0);
        return $this->outMessage($title, $retval);
    }

    /**
     * 购物车修改数量
     */
    public function cartAdjustNum()
    {
        $title = "修改购物车数量";
        $cart_id = request()->post('cartid', '');
        $num = request()->post('num', '');
        $uid = $this->uid;
        if (empty($cart_id)) {
            return $this->outMessage($title, null, '-50', "无法获取购物车信息!");
        }
        if (empty($num)) {
            return $this->outMessage($title, null, '-50', "无法获取商品数量!");
        }
        if (empty($uid)) {
            return $this->outMessage($title, null, '-9999', "无法获取会员登录信息!");
        }
        $applet_goods = new GoodsService();
        $retval = $applet_goods->cartAdjustNum($cart_id, $num);
        return $this->outMessage($title, $retval);
    }

    /**
     * 购物车项目删除
     */
    public function cartDelete()
    {
        $title = "删除购物车, del_id:中间,隔开";
        $cart_id_array = request()->post('del_id', '');
        $uid = $this->uid;
        if (empty($cart_id_array)) {
            return $this->outMessage($title, null, '-50', "无法获取选种商品!");
        }
        if (empty($uid)) {
            return $this->outMessage($title, null, '-9999', "无法获取会员登录信息!");
        }
        $applet_goods = new GoodsService();
        $retval = $applet_goods->cartDelete($cart_id_array);
        return $this->outMessage($title, $retval);
    }

    /**
     * 平台商品分类列表
     */
    public function goodsClassificationList()
    {
        $title = "商品分类树，根据手机端商品分类设置显示";
        $uid = $this->uid;
        $goods_category = new GoodsCategory();
        $goods_category_list = $goods_category->getCategoryTreeUseInShopIndex();
        $webConfig = new WebConfig();
        $show_type = $webConfig->getWapClassifiedDisplayMode($this->instance_id);
        
        // 依据显示类型计算补足数量
        if ($show_type == 2) {
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
            }
        }
        foreach ($goods_category_list as $k => $v) {
            
            if (strpos($goods_category_list[$k]['category_pic'], "http://") === false && strpos($goods_category_list[$k]['category_pic'], "https://") === false) {
                $goods_category_list[$k]['category_pic'] = getBaseUrl() . "/" . $goods_category_list[$k]['category_pic'];
            }
            
            foreach ($goods_category_list[$k]['child_list'] as $child_k => $child_v) {
                if (strpos($goods_category_list[$k]['child_list'][$child_k]["category_pic"], "http://") === false && strpos($goods_category_list[$k]['child_list'][$child_k]["category_pic"], "https://") === false) {
                    $goods_category_list[$k]['child_list'][$child_k]["category_pic"] = getBaseUrl() . "/" . $goods_category_list[$k]['child_list'][$child_k]["category_pic"];
                }
                
                if (! empty($goods_category_list[$k]['child_list'][$child_k]['child_list'])) {
                    foreach ($goods_category_list[$k]['child_list'][$child_k]['child_list'] as $third_k => $third_v) {
                        if (strpos($goods_category_list[$k]['child_list'][$child_k]['child_list'][$third_k]["category_pic"], "http://") === false && strpos($goods_category_list[$k]['child_list'][$child_k]['child_list'][$third_k]["category_pic"], "https://") === false) {
                            $goods_category_list[$k]['child_list'][$child_k]['child_list'][$third_k]["category_pic"] = getBaseUrl() . "/" . $goods_category_list[$k]['child_list'][$child_k]['child_list'][$third_k]["category_pic"];
                        }
                    }
                }
            }
        }
        
        $data = array(
            'goods_category_list' => $goods_category_list,
            'show_type' => $show_type
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 搜索商品显示
     */
    public function goodsSearchList()
    {
        $title = "商品列表查询";
        $sear_name = request()->post('search_name', '');
        $sear_type = request()->post('search_type', '');
        $order = request()->post('obyzd', '');
        $sort = request()->post('st', 'desc');
        $controlType = request()->post('controlType', '');
        $shop_id = request()->post('shop_id', '');
        $page = request()->post("page", 1);
        $goods = new GoodsService();
        $condition['goods_name|keywords'] = [
            'like',
            '%' . $sear_name . '%'
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
        return $this->outMessage($title, $search_good_list['data']);
    }

    /**
     * 获取品牌专区广告位
     */
    public function getBrandAdvPosition()
    {
        $title = "品牌专区广告位";
        $platform = new Platform();
        // 品牌专区广告位
        $brand_adv = $platform->getPlatformAdvPositionDetail(1162);
        $this->outMessage($title, $brand_adv);
    }

    /**
     * 品牌专区
     */
    public function brandlist()
    {
        $title = "品牌专区商品列表";
        $goods = new GoodsService();
        $brand_id = request()->post("brand_id", "");
        $page_index = request()->post("page", 1);
        if (! empty($brand_id)) {
            $condition['ng.brand_id'] = $brand_id;
        }
        $condition['ng.state'] = 1;
        $list = $goods->getGoodsList($page_index, PAGESIZE, $condition, "ng.sort desc,ng.create_time desc");
        return $this->outMessage($title, $list);
    }

    /**
     * 商品列表
     */
    public function goodsList()
    {
        $title = "商品列表查询";
        $goods_category_service = new GoodsCategory();
        $category_id = request()->post('category_id', '1'); // 商品分类
        $brand_id = request()->post('brand_id', ''); // 品牌
        $order = request()->post('obyzd', ''); // 商品排序分类,order by ziduan
        $sort = request()->post('st', 'desc'); // 商品排序分类 sort
        $page = request()->post('page', 1);
        $page_size = request()->post("page_size", PAGESIZE);
        $min_price = request()->post('mipe', ''); // 价格区间,最小min_price
        $max_price = request()->post('mape', ''); // 最大 max_price
        $attr = request()->post('attr', ''); // 属性值
        $spec = request()->post('spec', ''); // 规格值
                                             
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
        
        $goodsCategoryList = $goods_category_service->getCategoryTreeUseInShopIndex();
        $goods_list = $this->getGoodsListByConditions($category_id, $brand_id, $min_price, $max_price, $page, $page_size, $orderby, $attr_array, $spec_array);
        
        // 获取商品分类下的品牌列表、价格区间
        $category_brands = [];
        $category_price_grades = [];
        
        // 查询品牌列表，用于筛选
        $category_brands = $goods_category_service->getGoodsCategoryBrands($category_id);
        
        // 查询价格区间，用于筛选
        $category_price_grades = $goods_category_service->getGoodsCategoryPriceGrades($category_id);
        foreach ($category_price_grades as $k => $v) {
            $category_price_grades[$k]['price_str'] = $v[0] . '-' . $v[1];
        }
        $data = array(
            'goodsCategoryList' => $goodsCategoryList,
            'goods_list' => $goods_list['data'],
            'category_brands' => $category_brands,
            'category_price_grades' => $category_price_grades
        );
        return $this->outMessage($title, $data);
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
        $spec_where = "";
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
            }
            
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
     * 获取积分中心广告位
     */
    public function getintegralCenterAdvPosition()
    {
        $title = "积分中心广告位";
        $platform = new Platform();
        // 积分中心广告位
        $integral_adv = $platform->getPlatformAdvPositionDetail(1165);
        return $this->outMessage($title, $integral_adv);
    }

    /**
     * 积分中心
     *
     * @return \think\response\View
     */
    public function getIntegralCenterGoods()
    {
        $title = "获取积分中心商品,order_type:1.销量2.收藏3.点赞4.分享";
        // 积分中心商品
        $this->goods = new GoodsService();
        $order = "";
        // 排序
        $id = request()->post('order_type', '');
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
        
        $platform = new Platform();
        // 积分中心广告位
        $integral_adv = $platform->getPlatformAdvPositionDetail(1165);
        
        $page_index = request()->post('page', 1);
        $condition = array(
            "ng.state" => 1,
            "ng.point_exchange_type" => array(
                'NEQ',
                0
            )
        );
        $page_count = 25;
        $allGoods = $this->goods->getGoodsList($page_index, $page_count, $condition, $order);
        $data = array(
            'integral_adv' => $integral_adv,
            'goods_list' => $allGoods
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 商品点赞赠送积分
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function getClickPoint()
    {
        $title = "商品点赞获赠积分";
        $goods_id = request()->post('goods_id', '');
        
        if (empty($this->uid)) {
            return $this->outMessage($title, '', '-9999', "无法获取会员登录信息");
        }
        if (empty($goods_id)) {
            return $this->outMessage($title, '', '-50', "无法获取商品信息");
        }
        
        $goods = new GoodsService();
        $click_detail = $goods->getGoodsSpotFabulous($this->instance_id, $this->uid, $goods_id);
        if (empty($click_detail)) {
            $retval = $goods->setGoodsSpotFabulous($this->instance_id, $this->uid, $goods_id);
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
            return $this->outMessage($title, $retval);
        } else {
            return $this->outMessage($title, - 1);
        }
    }

    /**
     * 获取商品分类下的商品
     */
    public function getCategoryGoodsList()
    {
        $title = "获取商品分类下的商品列表";
        $page = request()->post("page", 1);
        $category_id = request()->post("category_id", 0);
        $goods = new GoodsService();
        if ($category_id == 0) {
            return $this->outMessage($title, '', '-50', "无法获取分类信息");
        } else {
            $condition['ng.category_id'] = $category_id;
            $condition['ng.state'] = 1;
            $res = $goods->getGoodsList($page, PAGESIZE, $condition, "ng.sort desc,ng.create_time desc");
            return $this->outMessage($title, $res);
        }
    }

    /**
     * 查询商品的sku信息
     */
    public function getGoodsSkuInfo()
    {
        $title = "获取商品的sku信息";
        $goods_id = request()->post('goods_id', '');
        if (empty($goods_id)) {
            return $this->outMessage($title, '', '-50', "无法获取商品信息");
        }
        $goods = new GoodsService();
        $data = $goods->getGoodsAttribute($goods_id);
        return $this->outMessage($title, $data);
    }

    /**
     * 商品默认图
     */
    public function getDefaultImages()
    {
        $title = '获取商品图默认配置';
        $config = new Config();
        $info = $config->getDefaultImages(0);
        return $this->outMessage($title, $info);
    }

    /**
     * 优惠券列表
     */
    public function couponList()
    {
        $title = "获取优惠券列表";
        $promotion = new Promotion();
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
        return $this->outMessage($title, $promotion_list);
    }

    /**
     * 优惠券详情
     */
    public function getCoupon()
    {
        $title = "获取优惠券详情";
        $coupon_type_id = request()->post('coupon_type_id', "");
        if (empty($coupon_type_id)) {
            return $this->outMessage($title, '', '-50', "无法获取优惠券信息");
        }
        $promotion = new Promotion();
        $condition['coupon_type_id'] = [
            'eq',
            $coupon_type_id
        ];
        $data = $promotion->getCouponTypeDetail($coupon_type_id);
        $path = $this->showMemberCouponQecode($coupon_type_id);
        
        $data = array(
            'data' => $data,
            'path' => $path
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 领取商品优惠劵
     */
    public function receiveGoodsCoupon()
    {
        $title = "领取商品优惠券";
        if (empty($this->uid)) {
            return $this->outMessage($title, '', '-9999', "无法会员登录信息");
        }
        $coupon_type_id = request()->post("coupon_type_id", '');
        if (empty($coupon_type_id)) {
            return $this->outMessage($title, '', '-50', "无法获取优惠券信息");
        }
        $member = new Member();
        $res = $member->memberGetCoupon($this->uid, $coupon_type_id, 3);
        return $this->outMessage($title, $res);
    }

    /**
     * 拼团专区
     * 创建时间：2017年12月27日15:35:28
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function spellingGroupZone()
    {
        $title = '拼团专区';
        $pintuan = new Pintuan();
        $page_index = request()->post("page", 1);
        $condition = 'npg.is_open=1';
        $list = $pintuan->getTuangouGoodsList($page_index, PAGESIZE, $condition, 'npg.create_time desc');
        return $this->outMessage($title, $list);
    }

    /**
     * 标签专区
     */
    public function promotionZone()
    {
        $title = '标签专区';
        $platform = new Platform();
        $goods = new GoodsService();
        // 品牌专区广告位
        $promotion_adv = $platform->getPlatformAdvPositionDetailByApKeyword("goodsLabel");
        if (! empty($promotion_adv)) {
            if (! empty($promotion_adv['adv_list'])) {
                foreach ($promotion_adv['adv_list'] as $k => $v) {
                    if (strpos($promotion_adv['adv_list'][$k]['adv_image'], "http://") === false && strpos($promotion_adv['adv_list'][$k]['adv_image'], "https://") === false) {
                        $promotion_adv['adv_list'][$k]['adv_image'] = getBaseUrl() . "/" . $promotion_adv['adv_list'][$k]['adv_image'];
                    }
                }
            }
        }
        
        $page_index = request()->post('page', 1);
        $group_id = request()->post("group_id", "");
        
        $condition = "";
        
        if (! empty($group_id)) {
            $condition = "FIND_IN_SET(" . $group_id . ",ng.group_id_array) AND ng.state = 1";
        } else {
            $condition['ng.group_id_array'] = array(
                'neq',
                0
            );
            $condition['ng.state'] = 1;
        }
        
        $goods_list = $goods->getGoodsList($page_index, PAGESIZE, $condition, "", $group_id);
        if (! empty($goods_list['data'])) {
            foreach ($goods_list['data'] as $k => $v) {
                if (strpos($goods_list['data'][$k]['pic_cover_small'], "http://") === false && strpos($goods_list['data'][$k]['pic_cover_small'], "https://") === false) {
                    $goods_list['data'][$k]['pic_cover_small'] = getBaseUrl() . "/" . $goods_list['data'][$k]['pic_cover_small'];
                }
            }
        }
        
        // 标签列表
        $goods_group = new GoodsGroup();
        $group_list = $goods_group->getGoodsGroupList(1, 0, [
            'shop_id' => $this->instance_id,
            'pid' => 0
        ]);
        
        $data = array(
            'promotion_adv' => $promotion_adv,
            'group_list' => $group_list,
            'goods_list' => $goods_list
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 商品团购详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function groupPurchase()
    {
        $title = '商品团购详情';
        $goods_id = request()->post('goods_id', 0);
        if ($goods_id == 0) {
            return $this->outMessage($title, '', - 50, '没有获取到商品信息');
        }
        $this->web_site = new WebSite();
        $goods = new GoodsService();
        $config_service = new WebConfig();
        $member = new Member();
        $shop_id = $this->instance_id;
        $uid = $this->uid;
        
        $web_info = $this->web_site->getWebSiteInfo();
        $group_id = request()->post("group_id", 0);
        
        $goods_detail = $goods->getBasisGoodsDetail($goods_id);
        if (empty($goods_detail)) {
            return $this->outMessage($title, '', - 50, '没有获取到商品信息');
        }
        if ($this->getIsOpenVirtualGoodsConfig() == 0 && $goods_detail['goods_type'] == 0) {
            return $this->outMessage($title, '', - 50, '未开启虚拟商品功能');
        }
        // 商品点击量
        $goods->updateGoodsClicks($goods_id);
        
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
        
        // 获取当前时间
        $current_time = $this->getCurrentTime();
        $goods_detail['ms_time'] = $current_time;
        $goods_detail["shopname"] = $this->shop_name;
        
        // 返回商品数量和当前商品的限购
        $goods_detail['restricted_purchase'] = $this->getCartInfo($goods_id);
        
        // 评价数量
        $evaluates_count = $goods->getGoodsEvaluateCount($goods_id);
        $goods_detail['evaluates_count'] = $evaluates_count;
        
        // 评价
        $goodsEvaluation = "";
        $order = new Order();
        $goodsEvaluation = $order->getOrderEvaluateDataList(1, 1, [
            "goods_id" => $goods_id
        ], 'addtime desc');
        if (! empty($goodsEvaluation)) {
            $memberService = new Member();
            $goodsEvaluation["data"][0]["user_img"] = $memberService->getMemberImage($goodsEvaluation["data"][0]["uid"]);
            $goods_detail["goodsEvaluation"] = $goodsEvaluation["data"][0];
        } else {
            $goods_detail["goodsEvaluation"] = $goodsEvaluation;
        }
        
        // 客服
        $customservice_config = $config_service->getcustomserviceConfig($shop_id);
        if (empty($customservice_config)) {
            $list['id'] = '';
            $list['value']['service_addr'] = '';
        }
        
        $data["customservice_config"] = $customservice_config;
        // 查询点赞记录表，获取详情再判断当天该店铺下该商品该会员是否已点赞
        $click_detail = $goods->getGoodsSpotFabulous($shop_id, $uid, $goods_id);
        $goods_detail['click_detail'] = $click_detail;
        
        // 当前用户是否收藏了该商品
        if (isset($uid)) {
            $is_member_fav_goods = $member->getIsMemberFavorites($uid, $goods_id, 'goods');
        }
        $goods_detail["is_member_fav_goods"] = $is_member_fav_goods;
        
        // 获取商品的优惠劵
        $goods_coupon_list = $goods->getGoodsCoupon($goods_id, $this->uid);
        $goods_detail["goods_coupon_list"] = $goods_coupon_list;
        
        // 组合商品
        $promotion = new Promotion();
        $comboPackageGoodsArray = $promotion->getComboPackageGoodsArray($goods_id);
        $goods_detail["comboPackageGoodsArray"] = $comboPackageGoodsArray[0];
        
        // 商品阶梯优惠
        $goodsLadderPreferentialList = $goods->getGoodsLadderPreferential([
            "goods_id" => $goods_id
        ], "quantity desc", "quantity,price");
        $goods_detail["goodsLadderPreferentialList"] = array_reverse($goodsLadderPreferentialList);
        
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
        $goods_detail["goods_group_list"] = $goods_group_list["data"];
        
        // 店铺服务
        $existingMerchant = $config_service->getExistingMerchantService($this->instance_id);
        $goods_detail["existingMerchant"] = $existingMerchant;
        
        return $this->outMessage($title, $goods_detail);
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
        foreach ($cartlist as $v) {
            if ($v["goods_id"] == $goods_id) {
                $num = $v["num"];
            }
        }
        $data = array(
            'carcount' => count($cartlist),
            'num' => $num
        );
        return $data;
    }

    /**
     * 获取商品分类，app用
     * 创建时间：2018年3月22日15:43:16
     *
     * @return Ambigous <\think\response\Json, string>
     */
    public function getGoodsCategoryListForApp()
    {
        $is_parent = request()->post("is_parent", 1); // 是否是父级
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post("page_size", PAGESIZE);
        $condition = array();
        $condition['category_pic'] = [
            '<>',
            ''
        ];
        $condition['is_visible'] = 1;
        if ($is_parent == 0) {
            $condition['pid'] = 0;
        } else {
            $condition['pid'] = [
                '>',
                0
            ];
        }
        $goods_category = new GoodsCategory();
        $res = $goods_category->getGoodsCategoryList($page_index, $page_size, $condition, "sort asc,category_id asc", "category_id,category_name,short_name,category_pic,pid,description");
        if (! empty($res['data'])) {
            foreach ($res['data'] as $k => $v) {
                if (! empty($res['data'][$k]['category_pic'])) {
                    if (strpos($res['data'][$k]['category_pic'], "http://") === false && strpos($res['data'][$k]['category_pic'], "https://") === false) {
                        $res['data'][$k]['category_pic'] = getBaseUrl() . "/" . $res['data'][$k]['category_pic'];
                    }
                }
            }
        }
        return $this->outMessage("APP分类界面数据", $res);
    }

    /**
     * 获取商品二级分类，app用
     * 创建时间：2018年3月22日15:43:16
     *
     * @return Ambigous <\think\response\Json, string>
     */
    public function getGoodsCategoryChildListForApp()
    {
        $pid = request()->post("pid", 0); // 是否是父级
        $goods_category = new GoodsCategory();
        $res = array();
        $list = $goods_category->getGoodsCategoryListByParentId($pid);
        if (! empty($list)) {
            foreach ($list as $k => $v) {
                if (! empty($list[$k]['category_pic'])) {
                    if (strpos($list[$k]['category_pic'], "http://") === false && strpos($list[$k]['category_pic'], "https://") === false) {
                        $list[$k]['category_pic'] = getBaseUrl() . "/" . $list[$k]['category_pic'];
                    }
                }
            }
        }
        $res['data'] = $list;
        return $this->outMessage("APP二级分类界面数据", $res);
    }

    /**
     * 积分兑换
     *
     * @return \think\response\View
     */
    public function goodsDetailPointExchange()
    {
        $title = '积分兑换商品详情';
        $goods_id = request()->post('id', 0);
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
        $group_id = request()->post("group_id", 0);
        
        $goods_detail = $goods->getBasisGoodsDetail($goods_id);
        if (empty($goods_detail)) {
            $this->outMessage($title, '', - 50, "没有获取到商品信息");
        }
        if ($this->getIsOpenVirtualGoodsConfig() == 0 && $goods_detail['goods_type'] == 0) {
            $this->outMessage($title, '', - 50, "未开启虚拟商品功能");
        }
        // 商品点击量
        $goods->updateGoodsClicks($goods_id);
        
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
        
        // 获取当前时间
        $current_time = $this->getCurrentTime();
        
        // 返回商品数量和当前商品的限购
        $goods_detail['restricted_purchase'] = $this->getCartInfo($goods_id);
        
        // 评价数量
        $evaluates_count = $goods->getGoodsEvaluateCount($goods_id);
        $goods_detail['evaluates_count'] = $evaluates_count;
        
        // 评价
        $goodsEvaluation = "";
        $order = new Order();
        $goodsEvaluation = $order->getOrderEvaluateDataList(1, 1, [
            "goods_id" => $goods_id
        ], 'addtime desc');
        if (! empty($goodsEvaluation)) {
            $memberService = new Member();
            $goodsEvaluation["data"][0]["user_img"] = $memberService->getMemberImage($goodsEvaluation["data"][0]["uid"]);
            $goods_detail["goodsEvaluation"] = $goodsEvaluation["data"][0];
        } else {
            $goods_detail["goodsEvaluation"] = $goodsEvaluation;
        }
        // 客服
        $customservice_config = $config_service->getcustomserviceConfig($shop_id);
        if (empty($customservice_config)) {
            $list['id'] = '';
            $list['value']['service_addr'] = '';
        }
        
        $goods_detail["customservice_config"] = $customservice_config;
        // $this->assign('service_addr',$list['value']['service_addr']);
        // 查询点赞记录表，获取详情再判断当天该店铺下该商品该会员是否已点赞
        $click_detail = $goods->getGoodsSpotFabulous($shop_id, $uid, $goods_id);
        
        // 当前用户是否收藏了该商品
        if (isset($uid)) {
            $is_member_fav_goods = $member->getIsMemberFavorites($uid, $goods_id, 'goods');
        }
        $goods_detail['is_member_fav_goods'] = $is_member_fav_goods;
        
        // 获取商品的优惠劵
        $goods_coupon_list = $goods->getGoodsCoupon($goods_id, $this->uid);
        $goods_detail['goods_coupon_list'] = $goods_coupon_list;
        
        // 组合商品
        $promotion = new Promotion();
        $comboPackageGoodsArray = $promotion->getComboPackageGoodsArray($goods_id);
        $goods_detail['comboPackageGoodsArray'] = $comboPackageGoodsArray[0];
        
        // 商品阶梯优惠
        $goodsLadderPreferentialList = $goods->getGoodsLadderPreferential([
            "goods_id" => $goods_id
        ], "quantity desc", "quantity,price");
        $goods_detail['goodsLadderPreferentialList'] = array_reverse($goodsLadderPreferentialList);
        
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
        
        $goods_detail['goods_group_list'] = $goods_group_list["data"];
        
        // 店铺服务
        $existingMerchant = $config_service->getExistingMerchantService($this->instance_id);
        
        // 积分抵现比率
        $integral_balance = 0; // 积分可抵金额
        $point_config = $promotion->getPointConfig();
        if ($point_config["is_open"] == 1) {
            if ($goods_detail['max_use_point'] > 0 && $point_config['convert_rate'] > 0) {
                $integral_balance = $goods_detail['max_use_point'] * $point_config['convert_rate'];
            }
        }
        $goods_detail["integral_balance"] = $integral_balance;
        $goods_detail['existingMerchant'] = $existingMerchant;
        $goods_detail['click_detail'] = $click_detail;
        $goods_detail['is_member_fav_goods'] = $is_member_fav_goods;
        $goods_detail['shopname'] = $this->shop_name;
        $goods_detail['current_time'] = $current_time;
        
        $this->outMessage($title, $goods_detail);
    }

    /**
     * 专题活动列表页面
     */
    public function promotionTopic()
    {
        $title = '专题活动列表';
        $platform = new Platform();
        // 专题活动广告位
        $topic_adv = $platform->getPlatformAdvPositionDetailByApKeyword("wapPromotionTopic");
        
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
        
        $data = array(
            'topic_adv' => $topic_adv,
            'info' => $list,
            'total_count' => count($list['data'])
        );
        
        return $this->outMessage($title, $data);
    }

    /**
     * 专题详情
     */
    public function promotionTopicGoods()
    {
        $title = '专题详情';
        $topic_id = request()->post('topic_id', 0);
        
        if (! is_numeric($topic_id)) {
            return $this->outMessage($title, '', - 10, '没有获取到专题信息');
        }
        $promotion = new Promotion();
        $topic_goods = $promotion->getPromotionTopicDetail($topic_id);
        
        $data = array(
            'info' => $topic_goods
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 获取热门商品列表，app用
     * 创建时间：2018年4月8日14:06:22
     */
    public function getHotGoodsList()
    {
        $goods = new GoodsService();
        $page_index = 1;
        $page_size = 10;
        $condition = array();
        $condition['is_hot'] = 1;
        $res = $goods->getSearchGoodsList($page_index, $page_size, $condition, $order = '', $field = 'goods_id,goods_name,collects,introduction,picture');
        if (! empty($res['data'])) {
            foreach ($res['data'] as $k => $v) {
                if (! empty($res['data'][$k]['picture_info']["pic_cover_big"])) {
                    if (strpos($res['data'][$k]['picture_info']["pic_cover_big"], "http://") === false && strpos($res['data'][$k]['picture_info']["pic_cover_big"], "https://") === false) {
                        $res['data'][$k]['picture_info']["pic_cover_big"] = getBaseUrl() . "/" . $res['data'][$k]['picture_info']["pic_cover_big"];
                    }
                }
            }
            return $this->outMessage("热门商品", $res['data']);
        } else {
            return $this->outMessage("热门商品", null, - 1, "暂无热门产品");
        }
    }

    /**
     * 发起砍价
     */
    public function addBargainLaunch()
    {
        $title = '发起砍价';
        $bargain = new Bargain();
        $bargain_id = request()->post('bargain_id', '');
        $sku_id = request()->post('sku_id', '');
        $address_id = request()->post('address_id', '');
        $launch_id = $bargain->addBargainLaunch($bargain_id, $sku_id, $address_id);
        $data = array(
            'launch_id' => $launch_id
        );
        return $this->outMessage($title, $data);
    }
}