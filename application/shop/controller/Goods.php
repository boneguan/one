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

namespace app\shop\controller;

use data\service\Address;
use data\service\Config;
use data\service\Goods as GoodsService;
use data\service\GoodsBrand as GoodsBrand;
use data\service\GoodsCategory as GoodsCategoryService;
use data\service\GoodsGroup as GoodsGroupService;
use data\service\Member as MemberService;
use data\service\Order as OrderService;
use data\service\promotion\GoodsExpress;
use data\service\Promotion;
use data\service\Shop as ShopService;
use Qiniu\json_decode;
use think\Cache;
use data\service\GroupBuy;

/**
 * 商品控制器
 * 创建人：李吉
 * 创建时间：2017-02-06 10:59:23
 */
class Goods extends BaseController
{

    // 商品
    private $goods = null;

    private $goods_group = null;

    // 店铺
    private $shop = null;

    // 会员、个人
    private $member = null;

    // 商品分类
    private $goods_category = null;

    // 平台
    private $platform = null;

    public function __construct()
    {
        parent::__construct();
    }

    public function _empty($name)
    {
    }
    
    /**
     * 商品详情
     * 创建人：王永杰
     * 创建时间：2017年2月7日 15:47:00
     *
     * @return \think\response\View
     */
    public function goodsinfo($goodsid)
    {
        $this->goods_category = new GoodsCategoryService();
        $web_info = $this->web_site->getWebSiteInfo();
        if (empty($goodsid)) {
            $goodsid = request()->get('goodsid');
        }
 
        $goodsid = (int)$goodsid;
        if (!empty($goodsid)) {

            $default_client = request()->cookie("default_client", "");
            if ($default_client == "shop") {
            } elseif (request()->isMobile() && $web_info['wap_status'] == 1) {
                $redirect = __URL(__URL__ . "/wap/goods/goodsdetail?id=" . $goodsid);
                $this->redirect($redirect);
                exit();
            }

            // 当切换到PC端时，隐藏右下角返回手机端按钮
            if (!request()->isMobile() && $default_client == "shop") {
                $default_client = "";
            }
            $Config = new Config();
            $this->goods = new GoodsService();
            $this->goods_group = new GoodsGroupService();
            $this->shop = new ShopService();
            $this->member = new MemberService();
            $this->assign('goods_id', $goodsid); // 将商品id传入方便查询当前商品的评论
            $this->member->addMemberViewHistory($goodsid);
            // 商品详情
            $goods_info = $this->goods->getBasisGoodsDetail($goodsid);
            // dump($goods_info);exit();
            // 更新商品点击数
            $this->goods->updateGoodsClicks($goodsid);

            if (empty($goods_info)) {
                $redirect = __URL(__URL__ . '/index');
                $this->redirect($redirect);
            }
            if ($this->getIsOpenVirtualGoodsConfig() == 0 && $goods_info['goods_type'] == 0) {
                $this->error("未开启虚拟商品功能");
            }

            // 检测商品是否限购，是否允许购买
            $goods_purchase_num = $goods_info['min_buy'] > 0 ? $goods_info['min_buy'] : 1;
            $goods_purchase_restriction = $this->goods->getGoodsPurchaseRestrictionForCurrentUser($goodsid, $goods_purchase_num);
            $this->assign("goods_purchase_restriction", $goods_purchase_restriction);

            // 获取当前时间
            $current_time = $this->getCurrentTime();
            $this->assign('ms_time', $current_time);

            $seoconfig = $Config->getSeoConfig($this->instance_id);
            if (!empty($goods_info['keywords'])) {
                $seoconfig['seo_meta'] = $goods_info['keywords']; // 关键词
            }
            $seoconfig['seo_desc'] = $goods_info['goods_name'];
            // 标题title(商品详情页面)
            $this->assign("title_before", $goods_info['goods_name']);
            $this->assign("seoconfig", $seoconfig);
            $this->assign("goods_sku_count", count($goods_info["sku_list"]));
            $this->assign("spec_list", count($goods_info["spec_list"]));
            $this->assign("shop_id", $goods_info['shop_id']); // 所属店铺id
            // 图片必须都存在才行
            $default_gallery_img = "";
            if (count($goods_info["img_list"]) > 0) {
                $default_gallery_img = $goods_info["img_list"][0]["pic_cover_big"];
            }
            $this->assign("default_gallery_img", $default_gallery_img);

            // 店内商品销量排行榜
            $goods_rank = $this->goods->getGoodsRankViewList(1, 5, array(
                "ng.category_id" => $goods_info["category_id"]
            ), "ng.sales desc");
            $this->assign("goods_rank", $goods_rank["data"]);

            // 店内商品收藏数排行榜
            $goods_collection = $this->goods->getGoodsRankViewList(1, 5, array(
                "ng.category_id" => $goods_info["category_id"]
            ), "ng.collects desc");
            $this->assign("goods_collection", $goods_collection["data"]);

            // 当前用户是否收藏了该商品,uid是从baseController获取到的
            $is_member_fav_goods = -1;
            if (isset($this->uid)) {
                $is_member_fav_goods = $this->member->getIsMemberFavorites($this->uid, $goodsid, 'goods');
            }
            $this->assign("is_member_fav_goods", $is_member_fav_goods);

            $evaluates_count = $this->goods->getGoodsEvaluateCount($goodsid);
            $this->assign('evaluates_count', $evaluates_count);

            $integral_flag = 0; // 是否是积分商品

            if ($goods_info["point_exchange_type"] == 1) {
                $integral_flag++;
                // 积分中心-->商品详情界面
            }
            $this->assign("integral_flag", $integral_flag);

            $consult_list = array();
            // 购买咨询 全部
            $consult_list[0] = $this->goods->getConsultList(1, 5, [
                'goods_id' => $goodsid
            ], 'consult_addtime desc');
            // 商品咨询
            $consult_list[1] = $this->goods->getConsultList(1, 5, [
                'goods_id' => $goodsid,
                'ct_id' => 1
            ], 'consult_addtime desc');

            // 支付咨询
            $consult_list[2] = $pay_consult_list = $this->goods->getConsultList(1, 5, [
                'goods_id' => $goodsid,
                'ct_id' => 2
            ], 'consult_addtime desc');

            // 发票及保险咨询
            $consult_list[3] = $invoice_consult_list = $this->goods->getConsultList(1, 5, [
                'goods_id' => $goodsid,
                'ct_id' => 3
            ], 'consult_addtime desc');

            $this->assign('consult_list', $consult_list);

            if (!$goods_info["category_id"] == "") {
                $category_name = $this->goods_category->getCategoryParentQuery($goods_info["category_id"]);
            } else {
                $category_name = "全部分类";
            }
            $this->assign("category_name", $category_name);
            // 获取商品的优惠劵
            $goods_coupon_list = $this->goods->getGoodsCoupon($goodsid, $this->uid);
            $this->assign("goods_coupon_list", $goods_coupon_list);

            // 获取商品优惠券数量
            $coupon_count = count($goods_coupon_list);
            $this->assign('coupon_count', $coupon_count);
            $this->assign("goods_info", $goods_info);
            // 浏览历史
            $member_histrorys = $this->getMemberHistories();
            $this->assign('member_histrorys', $member_histrorys);

            // 猜您喜欢
            $guess_member_likes = $this->member->getGuessMemberLikes();
            $this->assign("guess_member_likes", $guess_member_likes);
            $this->assign("guess_member_likes_count", count($guess_member_likes));

            // 商品品牌
            $goods_brand = new GoodsBrand();
            $brand_detial = $goods_brand->getGoodsBrandInfo($goods_info['brand_id']);
            $this->assign("brand_detial", $brand_detial);

            // 组合商品
            $promotion = new Promotion();
            $comboPackageGoodsArray = $promotion->getComboPackageGoodsArray($goodsid);
            $this->assign("comboPackageGoodsArray", $comboPackageGoodsArray);

            // 商品阶梯优惠
            $goodsLadderPreferentialList = $this->goods->getGoodsLadderPreferential([
                "goods_id" => $goodsid
            ], "quantity desc", "quantity,price");
            $this->assign("goodsLadderPreferentialList", array_reverse($goodsLadderPreferentialList));
            $this->assign("goodsLadderPreferentialListStr", json_encode($goodsLadderPreferentialList));
            $merchant_service_list = $Config->getExistingMerchantService($this->instance_id);
            $this->assign("merchant_service_list", $merchant_service_list);

            // 积分抵现比率
            $integral_balance = 0; // 积分可抵金额
            $point_config = $promotion->getPointConfig();
            if ($point_config["is_open"] == 1) {
                if ($goods_info['max_use_point'] > 0 && $point_config['convert_rate'] > 0) {
                    $integral_balance = $goods_info['max_use_point'] * $point_config['convert_rate'];
                }
            }
            $this->assign("integral_balance", $integral_balance);

            // 添加足迹
            if ($this->uid > 0) {
                $this->goods->addGoodsBrowse($goodsid, $this->uid);
            }
            if (empty($goods_info["pc_custom_template"])) {
                if ($goods_info["point_exchange_type"] == 0 || $goods_info["point_exchange_type"] == 2 || $goods_info["is_open_presell"] == 1) {
                    if ($goods_info["promotion_info"] == '限时折扣') {
                        // 活动-->商品详情界面
                        return view($this->style . 'Goods/goodsInfoPromotion');
                    } else {
                        // 基础-->商品详情界面
                        return view($this->style . 'Goods/goodsInfo');
                    }
                } else {
                    return view($this->style . 'Goods/goodsInfoPointExchange');
                }
            } else {
                // 基础-->商品详情界面 用户自定义
                return view($this->style . 'Goods/' . $goods_info["pc_custom_template"]);
            }
        } else {
            $redirect = __URL(__URL__ . '/index');
            $this->redirect($redirect);
        }
    }

    /**
     * 查询商品是否限购，是否允许购买
     *
     * @return \data\service\int，1：允许购买，0：不允许购买|number
     */
    public function getGoodsPurchaseRestrictionForCurrentUser()
    {
        $goods_id = request()->post("goods_id", "");
        $num = request()->post("num", 0);
        if (!empty($goods_id)) {
            $this->goods = new GoodsService();
            $goods_purchase_restriction = $this->goods->getGoodsPurchaseRestrictionForCurrentUser($goods_id, $num);
            return $goods_purchase_restriction;
        }
        return 0;
    }

    /**
     * 根据定位查询当前商品的运费
     * 创建时间：2017年9月29日 15:12:55 王永杰
     */
    public function getShippingFeeNameByLocation()
    {
        $goods_id = request()->post("goods_id", "");
        $goods_sku_list = request()->post("goods_sku_list", "");

        $res = [];
        if (!empty($goods_id)) {

            $goods_express = new GoodsExpress();
            $address = new Address();
            $order = new OrderService();
            $promotion = new Promotion();

            $user_location = get_city_by_ip();
          
            $res['user_location'] = $user_location;

            if ($user_location['status'] == 1) {

                // 定位成功，查询当前城市的运费
                $province = $address->getProvinceId($user_location["province"]);
                $city = $address->getCityId($user_location["city"]);
                $district = $address->getCityFirstDistrict($city['city_id']);
                $express = $goods_express->getGoodsExpressTemplate($goods_id, $province['province_id'], $city['city_id'], $district);
                $res['express'] = $express;

                $count_money = $order->getGoodsSkuListPrice($goods_sku_list); // 商品金额
                $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
                $no_mail = checkIdIsinIdArr($city['city_id'], $promotion_full_mail['no_mail_city_id_array']);
                if ($no_mail) {
                    $promotion_full_mail['is_open'] = 0;
                }

                if ($promotion_full_mail['is_open'] == 1) {
                    // 满额包邮开启
                    if ($count_money >= $promotion_full_mail["full_mail_money"]) {
                        $res['express'] = "免邮";
                    }
                }
            }
        }
        return $res;
    }

    /**
     * 根据地区获取物流模板
     */
    public function selcectexpress()
    {
        $goods_express = new GoodsExpress();
        $order = new OrderService();
        $promotion = new Promotion();
        $goods_id = request()->post("goods_id", '');
        $province_id = request()->post("province_id", '');
        $city_id = request()->post("city_id", '');
        $district_id = request()->post("disctrict_id", 0);
        $goods_sku_list = request()->post("goods_sku_list", "");
        $express = $goods_express->getGoodsExpressTemplate($goods_id, $province_id, $city_id, $district_id);
        $count_money = $order->getGoodsSkuListPrice($goods_sku_list); // 商品金额
        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        $no_mail = checkIdIsinIdArr($city_id, $promotion_full_mail['no_mail_city_id_array']);
        if ($no_mail) {
            $promotion_full_mail['is_open'] = 0;
        }

        if ($promotion_full_mail['is_open'] == 1) {
            // 满额包邮开启
            if ($count_money >= $promotion_full_mail["full_mail_money"]) {
                $express = "快递：免邮";
            }
        }
        return $express;
    }

    /**
     * 根据地址获取邮费
     * 暂时没有用到这个函数 2017年11月1日18:31:11
     */
    public function getExpressFee()
    {
        $goods_express = new GoodsExpress();
        $promotion = new Promotion();
        $order = new OrderService();
        $goods_id = request()->post('goods_id', '');
        $province = request()->post('province_id', '');
        $goods_sku_list = request()->post("goods_sku_list", "");
        $city = request()->post('city_id', '');
        $district_id = request()->post("disctrict_id", 0);
        $express = $goods_express->getGoodsExpressTemplate($goods_id, $province, $city, $district_id);
        $count_money = $order->getGoodsSkuListPrice($goods_sku_list); // 商品金额
        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        $no_mail = checkIdIsinIdArr($city, $promotion_full_mail['no_mail_city_id_array']);
        if ($no_mail) {
            $promotion_full_mail['is_open'] = 0;
        }

        if ($promotion_full_mail['is_open'] == 1) {
            // 满额包邮开启
            if ($count_money >= $promotion_full_mail["full_mail_money"]) {
                $express = "免邮";
            }
        }
        return $express;
    }

    /**
     * 商品列表
     * 创建人：李志伟
     * 创建时间：2017年2月7日 15:47:10
     * 修改人：王永杰
     * 修改时间：2017年2月28日 11:06:39
     *
     * @return \think\response\View
     */
    public function goodsList()
    {
        $category_id = request()->get('category_id', ''); // 商品分类
        $keyword = request()->get('keyword', ''); // 关键词
        //$keyword = str_replace("'", '', request()->get('keyword', ''));
        $shipping_fee = request()->get('fee', ''); // 是否包邮，0：包邮；1：运费价格
        $stock = request()->get('jxsyh', ''); // 仅显示有货，大于0
        $page = request()->get('page', '1'); // 当前页
        $order = request()->get('obyzd', ''); // 排序字段,order by ziduan
        $sort = request()->get('sort', ''); // 排序方式
        $brand_id = request()->get('brand_id', ''); // 品牌id
        $brand_name = request()->get('brand_name', ''); // 品牌名牌
        $min_price = request()->get('min_p_jg', ''); // 价格区间,最小
        $max_price = request()->get('max_p_jg', ''); // 最大
        $platform_proprietary = request()->get('platform_proprietary', ''); // 平台自营 shopid== 1
        $province_id = request()->get('province_id', ''); // 商品所在地
        $province_name = request()->get('province_name', ''); // 所在地名称

        $attr = request()->get('attr', ''); // 属性值
        $spec = request()->get('spec', ''); // 规格值
        $this->assign("attr_str", $attr);
        $this->assign("spec_str", $spec);

        // 将属性条件字符串转化为数组
        $attr_array = $this->stringChangeArray($attr);
        $this->assign("attr_array", $attr_array);

        // 规格转化为数组
        if ($spec != "") {
            $spec_array = explode(";", $spec);
        } else {
            $spec_array = array();
        }
        $spec_remove_array = array();
        foreach ($spec_array as $k => $v) {
            $spec_remove_array[] = explode(":", $v);
        }

        if (!is_numeric($category_id)) {
            $category_id = "";
        }
        $orderby = ""; // 排序方式 默认按排序号倒序，创建时间倒序排列

        // 过滤参数

        if ($keyword !== "") {
            $keyword = ihtmlspecialchars($keyword);
        }

        // 如果包邮参数不为空，则进行过滤判断
        if ($shipping_fee !== "") {
            if ($shipping_fee != 0 && $shipping_fee != 1) {
                // 非法参数进行过滤
                $shipping_fee = "";
            }
        }

        // 仅显示有货
        if ($stock != "") {
            if (!is_numeric($stock)) {
                // 非法参数进行过滤
                $stock = "";
            }
        }

        // 如果排序方式不为空，则进行过滤
        if ($sort != "") {
            if ($sort != "desc" && $sort != "asc") {
                // 非法参数进行过滤
                $sort = "";
            }
        }

        // 如果排序字段不为空，则进行过滤判断
        if ($order != "") {
            if ($order != "sales" && $order != "is_new" && $order != "price") {
                // 非法参数进行过滤
                $order = "ng.sort asc";
            } else {
                $orderby = "ng." . $order . " " . $sort;
            }
        } else {
            $orderby = "ng.sort asc";
        }

        if ($min_price != "" && $max_price != "") {
            if (!is_numeric($min_price)) {
                $min_price = "";
            }
            if (!is_numeric($max_price)) {
                $max_price = "";
            }
        }

        if ($province_id != "") {
            if (!is_numeric($province_id)) {
                $province_id = "";
            }
        }

        $this->assign("order", $order); // 要排序的字段
        $this->assign("sort", $sort); // 升序降序
        $this->goods_category = new GoodsCategoryService();
        $this->goods = new GoodsService();
        if ($category_id != "") {
            $Config = new Config();

            // 获取商品分类下的品牌列表、价格区间
            $category_brands = [];
            $category_price_grades = [];

            // 查询品牌列表，用于筛选 页面展示
            $category_brands = $this->goods_category->getGoodsBrandsByGoodsAttr($category_id);

            // 查询价格区间，用于筛选 选择价格区间 需优化
            $category_price_grades = $this->goods_category->getGoodsCategoryPriceGrades($category_id);

            $category_count = 0; // 默认没有数据
            if ($category_brands != "") {
                $category_count = 1; // 有数据
            }
            $goods_category_info = $this->goods_category->getGoodsCategoryDetail($category_id);
            $this->assign("curr_category_name", $goods_category_info['category_name']);

            $seoconfig = $Config->getSeoConfig($this->instance_id);
            $this->assign('title_before', $goods_category_info['category_name']);

            if (!empty($goods_category_info['keywords'])) {
                $seoconfig['seo_meta'] = $goods_category_info['keywords']; // 关键词
            }
            if (!empty($goods_category_info['description'])) {
                $seoconfig['seo_desc'] = $goods_category_info['description'];
            }
            $this->assign("seoconfig", $seoconfig);

            $attr_id = $goods_category_info["attr_id"];

            // 查询商品分类下的属性和规格集合
            $goods_attribute = $this->goods->getAttributeInfo([
                "attr_id" => $attr_id
            ]);

            $attribute_detail = $this->goods->getAttributeServiceDetail($attr_id, [
                'is_search' => 1
            ]);

            $attribute_list = array();
            if (!empty($attribute_detail['value_list']['data'])) {
                $attribute_list = $attribute_detail['value_list']['data'];
                foreach ($attribute_list as $k => $v) {
                    $is_unset = 0;
                    if (!empty($attr_array)) {
                        foreach ($attr_array as $t => $m) {
                            if (trim($v["attr_value_id"]) == trim($m[2])) {
                                unset($attribute_list[$k]);
                                $is_unset = 1;
                            }
                        }
                    }
                    if ($is_unset == 0) {
                        $value_items = explode(",", $v['value']);
                        $attribute_list[$k]['value'] = trim($v["value"]);
                        $attribute_list[$k]['value_items'] = $value_items;
                    }
                }
            }
            $attr_list = $attribute_list;

            // 查询本商品类型下的关联规格
            $goods_spec_array = array();
            if ($goods_attribute["spec_id_array"] != "") {
                $goods_spec_array = $this->goods->getGoodsSpecQuery([
                    "spec_id" => [
                        "in",
                        $goods_attribute["spec_id_array"]
                    ],
                    'goods_id' => 0
                ]);
                foreach ($goods_spec_array as $k => $v) {
                    if (!empty($spec_remove_array)) {
                        foreach ($spec_remove_array as $t => $m) {
                            if ($v["spec_id"] == $m[0]) {
                                $spec_remove_array[$t][2] = $v["spec_name"];
                                foreach ($v["values"] as $z => $c) {
                                    if ($c["spec_value_id"] == $m[1]) {
                                        $spec_remove_array[$t][3] = $c["spec_value_name"];
                                    }
                                }
                                unset($goods_spec_array[$k]);
                            }
                        }
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
            $this->assign("curr", 0);
        }

        $goods_field = "ng.goods_id,ng.goods_name,ng_sap.pic_cover_mid,ng.promotion_price,ng.stock,ng.sales";
        // 新品推荐
        if ($category_id != "") {
            $goods_new_list_cache = Cache::tag("pc_goodslist")->get("pc_goods_new_list" . $category_id);

            if (empty($goods_new_list_cache)) {
                $goods_new_list = $this->goods->getGoodsQueryLimit([
                    "ng.state" => 1,
                    "ng.is_new" => 1,
                    "ng.category_id" => $category_id
                ], $goods_field, 3);
                Cache::tag("pc_goodslist")->set("pc_goods_new_list" . $category_id, $goods_new_list);
            } else {
                $goods_new_list = $goods_new_list_cache;
            }
        } else {
            $goods_new_list_cache = Cache::tag("pc_goodslist")->get("pc_goods_new_list0");
            if (empty($goods_new_list_cache)) {

                $goods_new_list = $this->goods->getGoodsQueryLimit([
                    "ng.state" => 1,
                    "ng.is_new" => 1
                ], $goods_field, 3);
                Cache::tag("pc_goodslist")->set("pc_goods_new_list0", $goods_new_list);
            } else {
                $goods_new_list = $goods_new_list_cache;
            }
        }
        $this->assign("goods_new_list", $goods_new_list);

        // 销量排行榜
        $goods_sales_list_cache = Cache::tag("pc_goodslist")->get("pc_goods_sales_list");
        if (empty($goods_sales_list_cache)) {
            $goods_sales_list = $this->goods->getGoodsQueryLimit([
                "ng.state" => 1
            ], $goods_field, 3, "ng.sales desc");
            Cache::tag("pc_goodslist")->set("pc_goods_sales_list", $goods_sales_list);
        } else {
            $goods_sales_list = $goods_sales_list_cache;
        }

        $this->assign("goods_sales_list", $goods_sales_list);

        // 浏览历史
        $member_histrorys = $this->getMemberHistories();
        $this->assign('member_histrorys', $member_histrorys);

        // 猜您喜欢
        $guess_member_likes = $this->member->getGuessMemberLikes();
        $this->assign("guess_member_likes", $guess_member_likes);
        $this->assign("guess_member_likes_count", count($guess_member_likes));

        // -----------------查询条件筛选---------------------
        $this->assign("category_id", $category_id); // 商品分类ID
        $this->assign("brand_id", $brand_id); // 品牌ID
        $this->assign("brand_name", $brand_name); // 品牌ID
        $this->assign("min_price", $min_price); // 最小
        $this->assign("max_price", $max_price); // 最大
        $this->assign("shipping_fee", $shipping_fee); // 是否包邮
        $this->assign("stock", $stock); // 仅显示有货
        $this->assign("platform_proprietary", $platform_proprietary); // 平台自营
        $this->assign("province_name", $province_name);
        // -----------------查询条件筛选----------------------

        $url = request()->url(true); // get参数
        $url_parameter = explode('?', $url); // 筛选属性参数
        if (!empty($url_parameter[1])) {
            $url_parameter_array = explode("&", $url_parameter[1]);
        } else {
            $url_parameter_array = array();
        }

        // 去除参数中的规格 属性参数
        foreach ($url_parameter_array as $k => $v) {
            if (strpos($v, "attr") === 0) {
                unset($url_parameter_array[$k]);
            } elseif (strpos($v, "spec") === 0) {
                unset($url_parameter_array[$k]);
            }
            $url_parameter_array[$k] = urldecode($url_parameter_array[$k]);
        }
        $url_parameter_array = array_unique($url_parameter_array);
        $url_parameter = implode("&", $url_parameter_array);
        $attr_url = "";
        if ($attr != "") {
            $attr_url .= "&attr=$attr";
        }
        if ($spec != "") {
            $attr_url .= "&spec=$spec";
        }
        $this->assign("attr_url", $attr_url);
        $url_parameter_not_shipping = str_replace("&fee=0", "", $url_parameter); // 筛选：排除包邮
        $url_parameter_not_price = str_replace("&min_p_jg=$min_price&max_p_jg=$max_price", "", $url_parameter); // 筛选：排除价格区间
        $url_brand_name = str_replace("%2C", ",", rawurlencode($brand_name));
        $url_parameter_not_brand = str_replace(urldecode("&brand_id=$brand_id&brand_name=" . $url_brand_name . ""), "", $url_parameter); // 筛选：排除品牌
        $url_parameter_not_stock = str_replace("&jxsyh=$stock", "", $url_parameter); // 筛选：排除仅显示有货
        $url_parameter_not_platform_proprietary = str_replace("&platform_proprietary=$platform_proprietary", "", $url_parameter); // 筛选：排除平台自营
        $url_parameter_not_order = str_replace("&obyzd=$order&sort=$sort", "", $url_parameter); // 排序，排除之前的排序规则，防止重复，
        $url_parameter_not_province_id = str_replace("&province_id=$province_id&province_name=" . urlencode($province_name) . "", "", $url_parameter); // 排序，排除之前的排序规则，防止重复，

        $this->assign("url_parameter", $url_parameter); // 正常
        $this->assign("url_parameter_not_order", $url_parameter_not_order); // 排序，排除之前的排序规则，防止重复，
        $this->assign("url_parameter_not_shipping", $url_parameter_not_shipping); // 筛选：排除包邮
        $this->assign("url_parameter_not_price", $url_parameter_not_price . $attr_url); // 筛选：排除价格，
        $this->assign("url_parameter_not_brand", $url_parameter_not_brand . $attr_url); // 筛选：排除品牌
        $this->assign("url_parameter_not_stock", $url_parameter_not_stock); // 筛选：排除仅显示有货
        $this->assign("url_parameter_not_platform_proprietary", $url_parameter_not_platform_proprietary); // 筛选：排除平台自营
        $this->assign("url_parameter_not_province_id", $url_parameter_not_province_id); // 筛选：排除平台自营
        $this->assign("user_location", get_city_by_ip()); // 获取用户位置信息
//         PAGESIZE
        $goods_list = $this->getGoodsListByConditions($category_id, $brand_id, $min_price, $max_price, $keyword, $page, 16, $orderby, $shipping_fee, $stock, $platform_proprietary, $province_id, $attr_array, $spec_array);
        $this->assign("goods_list", $goods_list); // 返回商品列表
        $category_name = "";
        if (!$category_id == "") {
            $category_name = $this->goods_category->getCategoryParentQuery($category_id);
        } else {
            $category_name = "全部分类";
        }
        // if (count($goods_list["data"]) > 0) {
        // $category_name = $goods_list["data"][0]["category_name"]; // 面包屑
        // }
        $this->assign("spec_array", $spec_remove_array);
        $this->assign("category_name", $category_name);
        $this->assign('page_count', $goods_list['page_count']);
        $this->assign('total_count', $goods_list['total_count']);
        $this->assign('page', $page);

        $template = 'Goods/goodsList';
        if (!empty($goods_category_info["pc_custom_template"])) {
            $template = 'Goods/' . $goods_category_info["pc_custom_template"];
        }

        return view($this->style . $template);
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
     * 获取所有地址：省市县
     * 创建人：王永杰
     * 创建时间：2017年3月6日 14:21:43
     */
    public function getAddress()
    {
        // 省
        $address = new Address();
        $province_list = $address->getProvinceList();
        $list["province_list"] = $province_list;

        // 市
        $city_list = $address->getCityList();
        $list["city_list"] = $city_list;
        // 区县
        $district_list = $address->getDistrictList();
        $list["district_list"] = $district_list;
        return $list;
    }

    /**
     * 查询商品的sku信息
     * 创建人：王永杰
     * 创建时间：2017年3月1日 13:39:31
     */
    public function getGoodsSkuInfo()
    {
        $goods_id = request()->post('goods_id', '');
        $this->goods = new GoodsService();
        return $this->goods->getGoodsAttribute($goods_id);
    }

    /**
     * 右侧边栏-->我看过的
     * 创建人：王永杰
     * 创建时间：2017年2月28日 11:06:28
     */
    public function getMemberHistories()
    {
        // 浏览历史
        $this->member = new MemberService();
        $member_histrorys = $this->member->getMemberViewHistory();
        return $member_histrorys;
    }

    /**
     * 功能：ajax删除浏览记录
     * 创建人：李志伟
     * 创建时间：2017年2月16日15:15:36
     */
    public function deleteMemberHistory()
    {
        $this->member = new MemberService();
        $this->member->deleteMemberViewHistory();
        return AjaxReturn(1);
    }

    /**
     * 根据条件查询商品列表：商品分类查询，关键词查询，价格区间查询，品牌查询
     * 创建人：王永杰
     * 创建时间：2017年2月24日 16:55:05
     */
    public function getGoodsListByConditions($category_id, $brand_id, $min_price, $max_price, $keyword, $page, $page_size, $order, $shipping_fee, $stock, $platform_proprietary, $province_id, $attr_array, $spec_array)
    {
        $this->goods = new GoodsService();
        $condition = null;

        if ($category_id != "") {
            // 商品分类Id
            $condition["ng.category_id"] = $category_id;
        }

        // 品牌Id
        if ($brand_id != "") {
            $condition["ng.brand_id"] = [
                "in",
                $brand_id
            ];
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

        // 关键词
        if ($keyword != "") {
            $condition["ng.goods_name|ng.keywords"] = array(
                "like",
                "%" . $keyword . "%"
            );
        }

        // 包邮
        if ($shipping_fee != "") {
            $condition["ng.shipping_fee"] = $shipping_fee;
        }

        // 仅显示有货
        if ($stock != "") {
            $condition["ng.stock"] = array(
                ">",
                $stock
            );
        }

        // 平台直营
        if ($platform_proprietary != "") {
            $condition["ng.shop_id"] = $platform_proprietary;
        }

        // 商品所在地
        if ($province_id != "") {
            $condition["ng.province_id"] = $province_id;
        }
        // 属性 (条件拼装)
        $array_count = count($attr_array);
        $goodsid_str = "";
        $attr_str_where = "";
        if (!empty($attr_array)) {
            // 循环拼装sql属性条件
            foreach ($attr_array as $k => $v) {
                if ($attr_str_where == "") {
                    $attr_str_where = "(attr_value_id = '$v[2]' and attr_value_name='$v[1]')";
                } else {
                    $attr_str_where = $attr_str_where . " or " . "(attr_value_id = '$v[2]' and attr_value_name='$v[1]')";
                }
            }
            if ($attr_str_where != "") {
                $attr_query = $this->goods->getGoodsAttributeQuery($attr_str_where);

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
                $spec_info = $this->goods->getGoodsAttributeList([
                    "spec_id" => $tmp_array[0]
                ], 'spec_name', '');
                $spec_name = $spec_info[0]["spec_name"];
                // 得到规格值名称
                $spec_value_info = $this->goods->getGoodsAttributeValueList([
                    "spec_value_id" => $tmp_array[1]
                ], 'spec_value_name');
                $spec_value_name = $spec_value_info[0]["spec_value_name"];
                if (!empty($spec_name)) {
                    $spec_where[] = array(
                        'like',
                        '%' . $spec_name . '%'
                    );
                }
                if (!empty($spec_value_name)) {
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
            if (!empty($spec_where)) {
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
        $list = $this->goods->getGoodsListNew($page, $page_size, $condition, $order);
        return $list;
    }

    /**
     * 根据关键词返回商品列表
     * 创建人：王永杰
     * 创建时间：2017年2月10日 15:17:00
     */
    public function getGoodsListByKeyWord()
    {
        $page_index = 1;
        $page_size = 0;
        $keyword = request()->get('keyword');
        $keyword = str_replace("'", '', request()->get('keyword', ''));
        $order = "";
        $list = null;
        $this->goods = new GoodsService();
        if ($keyword) {
            $page_index = request()->get('page_index', 1);
            $page_size = request()->get('page_size', 0);

            $order = request()->get('order', '');
            $list = $this->goods->getGoodsViewList($page_index, $page_size, array(
                "ng.goods_name" => array(
                    "like",
                    "%" . $keyword . "%"
                )
            ), $order);
        } else {
            // 没有条件，查询全部
            $list = $this->goods->getGoodsViewList($page_index, $page_size, "", $order);
        }
        return $list;
    }

    /**
     * 获取销量排行榜的商品列表
     * 创建人：王永杰
     * 创建时间：2017年2月9日 17:23:40
     */
    public function getSalesGoodsList()
    {
        $this->goods = new GoodsService();
        $list = $this->goods->getGoodsViewList(1, 3, [
            "ng.state" => 1
        ], "sales desc");
        return $list["data"];
    }

    /**
     * 店铺品牌
     * 创建人：李志伟
     * 创建时间：2017年2月7日17:34:20
     */
    public function brandList()
    {
        $goods_brand = new GoodsBrand();
        $page_index = request()->get('page', 1);
        $page_size = request()->get('page_size', 0);
        $category_id = request()->get('category_id', '');
        $left = request()->get('left', '');
        if ($category_id == '') {
            $list = $goods_brand->getGoodsBrandList($page_index, 16, '', 'sort');
        } else {
            $list = $goods_brand->getGoodsBrandList($page_index, 16, [
                'category_id_1' => $category_id
            ], 'sort');
        }
        $this->assign('category_id', $category_id);
        $this->assign('is_head_goods_nav', 1); // 代表默认显示以及分类
        // 获取商品分类
        $goods_Category = new GoodsCategoryService();
        $condition['level'] = 1;
        $type = $goods_Category->getGoodsCategoryList(1, 0, $condition, 'sort');

        $this->assign('type_list', $type['data']);
        $this->assign('page_count', $list['page_count']);
        $this->assign('total_count', $list['total_count']);
        $this->assign('page', $page_index);
        $this->assign('left', $left);
        $this->assign('list', $list['data']);

        // 浏览历史
        $this->member = new MemberService();
        $member_histrorys = $this->member->getMemberViewHistory();
        $this->assign('member_histrorys', $member_histrorys);

        // 猜您喜欢
        $guess_member_likes = $this->member->getGuessMemberLikes();
        $this->assign("guess_member_likes", $guess_member_likes);
        $this->assign("guess_member_likes_count", count($guess_member_likes));

        $this->assign("title_before", "品牌列表");
        return view($this->style . 'Goods/brandList');
    }

    /**
     * 全部商品分类
     *
     * @return \think\response\View
     */
    public function category()
    {
        return view($this->style . 'Goods/category');
    }

    /**
     * 商品信息
     */
    public function getGoodsInfo()
    {
        $this->member = new MemberService();
        $list = $this->member->getMemberViewHistory();
    }

    /**
     * 积分中心
     * 创建人：王永杰
     * 创建时间：2017年2月17日 17:56:41
     */
    public function integralCenter()
    {
        $this->goods = new GoodsService();
        $order = "";
        // 排序
        $id = request()->get('id', '');
        if ($id) {
            if ($id == 1) {
                $order = "sales desc";
            } elseif ($id == 2) {
                $order = "collects desc";
            } elseif ($id == 3) {
                $order = "evaluates desc";
            } elseif ($id == 4) {
                $order = "shares desc";
            } else {
                $id = 0;
                $order = "sort asc,create_time desc";
            }
        } else {
            $id = 0;
        }

        $page_index = request()->get('page', '1');
        $condition = array(
            "ng.state" => 1,
            "ng.point_exchange_type" => array(
                'in',
                '1,2,3'
            )
        );
        $page_count = 25;
        $hotGoods = $this->goods->getGoodsQueryLimit($condition, "ng.goods_id,ng.goods_name,ng.point_exchange,ng_sap.pic_cover_mid,ng.point_exchange_type,ng.point_exchange", 4);
        $allGoods = $this->goods->getGoodsList($page_index, $page_count, $condition, $order);
        $this->assign("id", $id);
        $this->assign('page', $page_index);
        $this->assign("hotGoods", $hotGoods);
        $this->assign("allGoods", $allGoods);
        $this->assign('page_count', $allGoods['page_count']);
        $this->assign('total_count', $allGoods['total_count']);
        $this->assign("title_before", "积分中心");
        return view($this->style . 'Goods/integralCenter');
    }

    /**
     * 功能：商品评论
     * 创建人：李志伟
     * 创建时间：2017年2月23日11:12:57
     */
    public function getGoodsComments()
    {
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', 0);
        $goods_id = request()->post('goods_id', '');
        $comments_type = request()->post('comments_type', '');
        $order = new OrderService();
        $condition['goods_id'] = $goods_id;
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
        $goodsEvaluationList = $order->getOrderEvaluateDataList($page_index, $page_size, $condition, 'addtime desc');
        $memberService = new MemberService();
        foreach ($goodsEvaluationList['data'] as $v) {
            $v["user_img"] = $memberService->getMemberImage($v["uid"]);
        }
        return $goodsEvaluationList;
    }

    /**
     * 商品购买咨询
     *
     * @return \think\response\View
     */
    public function goodsConsult()
    {
        // 商品详情
        $goodsid = request()->get('goodsid', '');
        $page = request()->get('page', 1);
        $ct_id = request()->get('ct_id', '');

        $this->goods = new GoodsService();
        $goods_info = $this->goods->getGoodsDetail($goodsid);

        $condition['goods_id'] = $goodsid;
        if (!empty($ct_id)) {
            $condition['ct_id'] = $ct_id;
        }

        // 商品咨询
        $consult_list = $this->goods->getConsultList($page, 5, $condition, 'consult_addtime desc');
        $this->assign('consult_list', $consult_list);

        $assign_get_list = array(
            'goods_info' => $goods_info, // 商品详情
            'goodsid' => $goodsid, // 商品id
            'page' => $page, // 当前页
            'page_count' => $consult_list['page_count'], // 总页数
            'total_count' => $consult_list['total_count'], // 总条数
            'consult_list' => $consult_list['data'], // 店铺分页
            'ct_id' => $ct_id
        );

        foreach ($assign_get_list as $key => $value) {
            $this->assign($key, $value);
        }
        $this->assign("title_before", "商品咨询");
        return view($this->style . 'Goods/goodsConsult');
    }

    /**
     * 商品咨询添加
     */
    public function goodsConsultInsert()
    {
        $randomCode = request()->post('randomCode', '');
        if (!captcha_check($randomCode)) {
            return [
                'code' => '-1',
                'message' => '验证码输入错误'
            ];
        }
        $goods_id = request()->post('goods_id', '');
        $shop_id = request()->post('shop_id', '');
        $goods_name = request()->post('goods_name', '');
        $ct_id = request()->post('ct_id', '');
        $consult_content = request()->post('consult_content', '');

        $this->shop = new ShopService();
        $this->member = new MemberService();

        $shop_info = $this->shop->getShopDetail($shop_id);
        $shop_name = $shop_info['base_info']['shop_name'];

        $member_info = $this->member->getMemberDetail();
        $member_name = empty($member_info) ? '' : $member_info['member_name'];
        $uid = empty($this->uid) ? '0' : $this->uid;

        $this->goods = new GoodsService();
        $retval = $this->goods->addConsult($goods_id, $goods_name, $uid, $member_name, $shop_id, $shop_name, $ct_id, $consult_content);
        return AjaxReturn($retval);
    }

    /**
     * 获取商品详情
     */
    public function getGoodsDetail()
    {
        $goods = new GoodsService();
        $goods_id = request()->post('goods_id', '');
        $goods_detail = $goods->getGoodsDetail($goods_id);
        return $goods_detail;
    }

    /**
     * 添加购物车
     */
    public function addCart()
    {
        $goods = new GoodsService();
        $uid = $this->uid;
        $cart_detail = request()->post('cart_detail', '');
        if (!empty($cart_detail)) {
            $cart_detail = json_decode($cart_detail, true);
        }
        $goods_id = $cart_detail['goods_id'];
        $goods_name = $cart_detail['goods_name'];
        $shop_id = $this->instance_id;
        $web_info = $this->web_site->getWebSiteInfo();
        $count = $cart_detail['count'];
        $sku_id = $cart_detail['sku_id'];
        $sku_name = $cart_detail['sku_name'];
        $price = $cart_detail['price'];
        $cost_price = $cart_detail['cost_price'];
        $picture_id = $cart_detail['picture_id'];
        $_SESSION['order_tag'] = ""; // 清空订单
        $retval = $goods->addCart($uid, $shop_id, $web_info['title'], $goods_id, $goods_name, $sku_id, $sku_name, $price, $count, $picture_id, 0);
        return $retval;
    }

    /**
     * 购物车
     * 创建人：王永杰
     * 创建时间：2017年2月7日 15:45:49
     *
     * @return \think\response\View
     */
    public function cart()
    {
        $goods = new GoodsService();
        $cart_list = $goods->getCart($this->uid);
        $this->assign("cart_list", $cart_list);
        $this->assign("title_before", "购物车");
        $this->goods = new GoodsService();
        // 商品阶梯优惠信息
        $goods_id_arr = array();
        $goods_ladder_preferential = array();
        if (count($cart_list) > 0) {
            foreach ($cart_list as $v) {
                if(!in_array($v["goods_id"], $goods_id_arr)){
                    $goods_ladder_preferential[] = $this->goods->getGoodsLadderPreferential([
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
        $goods = new GoodsService();
        $cart_list = $goods->getCart($this->uid);
        return $cart_list;
    }

    /**
     * 根据cartid删除购物车中的商品
     * 创建人：王永杰
     * 创建时间：2017年2月15日 14:34:45
     *
     * @return unknown
     */
    public function deleteShoppingCartById()
    {
        $goods = new GoodsService();
        $cart_id_array = request()->post('cart_id_array', '');
        $res = $goods->cartDelete($cart_id_array);
        $_SESSION['order_tag'] = ""; // 清空订单
        return AjaxReturn($res);
    }

    /**
     * 更新购物车中商品数量
     * 创建人：王永杰
     * 创建时间：2017年2月15日 15:43:23
     *
     * @return unknown
     */
    public function updateCartGoodsNumber()
    {
        $goods = new GoodsService();
        $cart_id = request()->post('cart_id', '');
        $num = request()->post('num', '');
        $_SESSION['order_tag'] = ""; // 清空订单
        $res = $goods->cartAdjustNum($cart_id, $num);
        return $res;
    }

    /**
     * 随机获取商品列表
     */
    public function getRandGoodsListAjax()
    {
        if (request()->isAjax()) {
            $goods = new GoodsService();
            $res = $goods->getRandGoodsList();
            return $res;
        }
    }

    public function test()
    {
        $goods = new GoodsService();
        $spec_array = array(
            "12:28",
            "12:25",
            "2:7"
        );
        $spec_count = count($spec_array);
        $spec_where = "";
        if ($spec_count > 0) {
            foreach ($spec_array as $k => $v) {
                if ($spec_where == "") {
                    $spec_where = " attr_value_items_format like '%{$v}%' ";
                } else {
                    $spec_where = $spec_where . " or " . " attr_value_items_format like '%{$v}%' ";
                }
            }
        }
        $goods_query = $goods->getGoodsSkuQuery($spec_where);
        $temp_array = array();
        foreach ($goods_query as $k => $v) {
            $temp_array[] = $v["goods_id"];
        }
        $goods_query = array_unique($temp_array);
    }

    /**
     * 领取商品优惠劵
     */
    public function receiveGoodsCoupon()
    {
        if (request()->isAjax()) {
            $member = new MemberService();
            $coupon_type_id = request()->post("coupon_type_id", '');
            $res = $member->memberGetCoupon($this->uid, $coupon_type_id, 3);
            return AjaxReturn($res);
        }
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
     * 选择优惠套餐
     * 创建时间：2017年12月6日09:43:02 王永杰
     */
    public function comboPackagePromotionSelected()
    {
        $this->checkLogin();
        $promotion = new Promotion();
        $combo_id = request()->get("combo_id", 0);
        $curr_id = request()->get("curr_id", "");
        $combo_package = $promotion->getComboPackageGoodsById($combo_id, $curr_id);
        if (empty($combo_package)) {
            $this->error("没有获取到组合套餐信息");
        }
        $this->assign("combo_package", $combo_package);
        $this->assign("combo_id", $combo_id);
        return view($this->style . "Goods/comboPackagePromotionSelected");
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
     * 标签专区
     */
    public function promotionZone()
    {
        $page_index = request()->get('page', '1');
        $group_id = request()->get("group_id", "");

        $goods_group = new GoodsGroupService();
        $groupList = $goods_group->getGoodsGroupList(1, 0, [
            'shop_id' => $this->instance_id,
            'pid' => 0
        ]);
        if (!empty($groupList["data"]) && empty($group_id)) {
            $group_id = $groupList["data"][0]["group_id"];
        }
        // 标签列表
        $this->assign("groupList", $groupList["data"]);
        $this->assign("group_id", $group_id);

        $curr_group = $goods_group->getGoodsGroupDetail($group_id);
        $this->assign("curr_group", $curr_group);

        $this->goods = new GoodsService();
        $condition = "";

        if (!empty($group_id)) {
            $condition = "FIND_IN_SET(" . $group_id . ",ng.group_id_array) AND ";
        }
        $condition .= 'ng.state = 1';
        $goods_list = $this->goods->getGoodsList($page_index, PAGESIZE, $condition, "");
        $this->assign("goods_list", $goods_list);
        $this->assign('page_count', $goods_list['page_count']);
        $this->assign('total_count', $goods_list['total_count']);
        $this->assign('page', $page_index);

        // 浏览历史
        $this->member = new MemberService();
        $member_histrorys = $this->member->getMemberViewHistory();
        $this->assign('member_histrorys', $member_histrorys);

        // 猜您喜欢
        $guess_member_likes = $this->member->getGuessMemberLikes();
        $this->assign("guess_member_likes", $guess_member_likes);
        $this->assign("guess_member_likes_count", count($guess_member_likes));

        $this->assign("title_before", "标签专区");
        return view($this->style . 'Goods/promotionZone');
    }

    /**
     * 优惠券
     */
    public function couponList()
    {
        $page_index = request()->get('page', '1');

        if ($page_index < 1) {
            $page_index = 1;
        }

        $promotion_service = new Promotion();
        
        $condition = '(count > 0  AND end_time > '.time().' AND is_show = 1 AND term_of_validity_type = 0)';
        $condition .= 'OR (term_of_validity_type = 1 AND is_show = 1)';
        
        $uid = $this->uid;
        $promotion_list = $promotion_service->getCouponTypeInfoList($page_index, 10, $condition, '', $uid);

        $this->assign('promotion_list', $promotion_list);
        $this->assign('total_count', $promotion_list['total_count']);
        $this->assign('page_count', $promotion_list['page_count']);

        if (request()->isAjax()) {
            return view($this->style . 'Goods/couponListAjax');
        }
        return view($this->style . 'Goods/couponList');
    }

    /**
     * 首页领用优惠券
     */
    public function getCoupon()
    {
        $coupon_type_id = request()->post('coupon_type_id', 0);
        if (!empty($this->uid)) {
            $member = new MemberService();
            $retval = $member->memberGetCoupon($this->uid, $coupon_type_id, 2);
            return AjaxReturn($retval);
        } else {
            return AjaxReturn(NO_LOGIN);
        }
    }

    /**
     * 获取sku列表
     */
    public function getGoodsSkuListAjax()
    {
        if (request()->isAjax()) {
            $this->goods = new GoodsService();
            $goods_id = request()->post("goods_id", "");
            $sku_list = $this->goods->getGoodsSkuListByGoodsId($goods_id);
            return $sku_list;
        }
    }

    /**
     * 团购商品详情
     * 创建人：王永杰
     * 创建时间：2017年2月7日 15:47:00
     *
     * @return \think\response\View
     */
    public function groupPurchase($goodsid)
    {
        $this->goods_category = new GoodsCategoryService();
        $web_info = $this->web_site->getWebSiteInfo();
        if (empty($goodsid)) {
            $goodsid = request()->get('goodsid');
        }

        $goodsid = (int)$goodsid;
        if (!empty($goodsid)) {

            $default_client = request()->cookie("default_client", "");
            if ($default_client == "shop") {
            } elseif (request()->isMobile() && $web_info['wap_status'] == 1) {
                $redirect = __URL(__URL__ . "/wap/goods/goodsdetail?id=" . $goodsid);
                $this->redirect($redirect);
                exit();
            }

            // 当切换到PC端时，隐藏右下角返回手机端按钮
            if (!request()->isMobile() && $default_client == "shop") {
                $default_client = "";
            }
            $Config = new Config();
            $this->goods = new GoodsService();
            $this->goods_group = new GoodsGroupService();
            $this->shop = new ShopService();
            $this->member = new MemberService();
            $this->assign('goods_id', $goodsid); // 将商品id传入方便查询当前商品的评论
            $this->member->addMemberViewHistory($goodsid);
            // 商品详情
            $goods_info = $this->goods->getBasisGoodsDetail($goodsid);

            // dump($goods_info['group_info']['price_array'][0]);exit();
            // 更新商品点击数
            $this->goods->updateGoodsClicks($goodsid);

            if (empty($goods_info)) {
                $redirect = __URL(__URL__ . '/index');
                $this->redirect($redirect);
            }

            //判断该团购活动状态
            if (empty($goods_info['group_info'])) {
                $redirect = __URL(__URL__ . "/goods/goodsinfo?goodsid=" . $goodsid);
                $this->error("未找到该活动信息", $redirect);
            }

            // 检测商品是否限购，是否允许购买
            $goods_purchase_num = $goods_info['min_buy'] > 0 ? $goods_info['min_buy'] : 1;
            $goods_purchase_restriction = $this->goods->getGoodsPurchaseRestrictionForCurrentUser($goodsid, $goods_purchase_num);
            $this->assign("goods_purchase_restriction", $goods_purchase_restriction);

            // 获取当前时间
            $current_time = $this->getCurrentTime();
            $this->assign('ms_time', $current_time);

            $seoconfig = $Config->getSeoConfig($this->instance_id);
            if (!empty($goods_info['keywords'])) {
                $seoconfig['seo_meta'] = $goods_info['keywords']; // 关键词
            }
            $seoconfig['seo_desc'] = $goods_info['goods_name'];
            // 标题title(商品详情页面)
            $this->assign("title_before", $goods_info['goods_name']);
            $this->assign("seoconfig", $seoconfig);
            $this->assign("goods_sku_count", count($goods_info["sku_list"]));
            $this->assign("spec_list", count($goods_info["spec_list"]));
            $this->assign("shop_id", $goods_info['shop_id']); // 所属店铺id
            // 图片必须都存在才行
            $default_gallery_img = "";
            if (count($goods_info["img_list"]) > 0) {
                $default_gallery_img = $goods_info["img_list"][0]["pic_cover_big"];
            }
            $this->assign("default_gallery_img", $default_gallery_img);

            // 店内商品销量排行榜
            $goods_rank = $this->goods->getGoodsRankViewList(1, 5, array(
                "ng.category_id" => $goods_info["category_id"]
            ), "ng.sales desc");
            $this->assign("goods_rank", $goods_rank["data"]);

            // 店内商品收藏数排行榜
            $goods_collection = $this->goods->getGoodsRankViewList(1, 5, array(
                "ng.category_id" => $goods_info["category_id"]
            ), "ng.collects desc");
            $this->assign("goods_collection", $goods_collection["data"]);

            // 当前用户是否收藏了该商品,uid是从baseController获取到的
            $is_member_fav_goods = -1;
            if (isset($this->uid)) {
                $is_member_fav_goods = $this->member->getIsMemberFavorites($this->uid, $goodsid, 'goods');
            }
            $this->assign("is_member_fav_goods", $is_member_fav_goods);

            $evaluates_count = $this->goods->getGoodsEvaluateCount($goodsid);
            $this->assign('evaluates_count', $evaluates_count);

            $integral_flag = 0; // 是否是积分商品

            if ($goods_info["point_exchange_type"] == 1) {
                $integral_flag++;
                // 积分中心-->商品详情界面
            }
            $this->assign("integral_flag", $integral_flag);

            $consult_list = array();
            // 购买咨询 全部
            $consult_list[0] = $this->goods->getConsultList(1, 5, [
                'goods_id' => $goodsid
            ], 'consult_addtime desc');
            // 商品咨询
            $consult_list[1] = $this->goods->getConsultList(1, 5, [
                'goods_id' => $goodsid,
                'ct_id' => 1
            ], 'consult_addtime desc');

            // 支付咨询
            $consult_list[2] = $pay_consult_list = $this->goods->getConsultList(1, 5, [
                'goods_id' => $goodsid,
                'ct_id' => 2
            ], 'consult_addtime desc');

            // 发票及保险咨询
            $consult_list[3] = $invoice_consult_list = $this->goods->getConsultList(1, 5, [
                'goods_id' => $goodsid,
                'ct_id' => 3
            ], 'consult_addtime desc');

            $this->assign('consult_list', $consult_list);

            if (!$goods_info["category_id"] == "") {
                $category_name = $this->goods_category->getCategoryParentQuery($goods_info["category_id"]);
            } else {
                $category_name = "全部分类";
            }
            $this->assign("category_name", $category_name);
            // 获取商品的优惠劵
            $goods_coupon_list = $this->goods->getGoodsCoupon($goodsid, $this->uid);
            $this->assign("goods_coupon_list", $goods_coupon_list);

            // 获取商品优惠券数量
            $coupon_count = count($goods_coupon_list);
            $this->assign('coupon_count', $coupon_count);
            $this->assign("goods_info", $goods_info);
            // 浏览历史
            $member_histrorys = $this->getMemberHistories();
            $this->assign('member_histrorys', $member_histrorys);

            // 猜您喜欢
            $guess_member_likes = $this->member->getGuessMemberLikes();
            $this->assign("guess_member_likes", $guess_member_likes);
            $this->assign("guess_member_likes_count", count($guess_member_likes));

            // 商品品牌
            $goods_brand = new GoodsBrand();
            $brand_detial = $goods_brand->getGoodsBrandInfo($goods_info['brand_id']);
            $this->assign("brand_detial", $brand_detial);

            // 组合商品
            $promotion = new Promotion();
            $comboPackageGoodsArray = $promotion->getComboPackageGoodsArray($goodsid);
            $this->assign("comboPackageGoodsArray", $comboPackageGoodsArray);

            // 商品阶梯优惠
            $goodsLadderPreferentialList = $this->goods->getGoodsLadderPreferential([
                "goods_id" => $goodsid
            ], "quantity desc", "quantity,price");
            $this->assign("goodsLadderPreferentialList", array_reverse($goodsLadderPreferentialList));
            $this->assign("goodsLadderPreferentialListStr", json_encode($goodsLadderPreferentialList));
            $merchant_service_list = $Config->getExistingMerchantService($this->instance_id);
            $this->assign("merchant_service_list", $merchant_service_list);

            // 积分抵现比率
            $integral_balance = 0; // 积分可抵金额
            $point_config = $promotion->getPointConfig();
            if ($point_config["is_open"] == 1) {
                if ($goods_info['max_use_point'] > 0 && $point_config['convert_rate'] > 0) {
                    $integral_balance = $goods_info['max_use_point'] * $point_config['convert_rate'];
                }
            }
            $this->assign("integral_balance", $integral_balance);

            // 添加足迹
            if ($this->uid > 0) {
                $this->goods->addGoodsBrowse($goodsid, $this->uid);
            }

            return view($this->style . 'Goods/groupPurchase');
        } else {
            $redirect = __URL(__URL__ . '/index');
            $this->redirect($redirect);
        }
    }

    /**
     * 团购专区
     */
    public function groupBuyingArea()
    {
        $group_buy_service = new GroupBuy();
        $page_index = request()->get("page", 1);
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
        $group_goods_list = $group_buy_service->getPromotionGroupBuyGoodsList($page_index, PAGESIZE, $condition, 'npgb.group_id desc', $field);
        $this->assign("group_goods_list", $group_goods_list);
        $this->assign("page", $page_index);
        $this->assign("total_count", $group_goods_list['total_count']);
        $this->assign("page_count", $group_goods_list['page_count']);

        // 浏览历史
        $this->member = new MemberService();
        $member_histrorys = $this->member->getMemberViewHistory();
        $this->assign('member_histrorys', $member_histrorys);

        // 猜您喜欢
        $guess_member_likes = $this->member->getGuessMemberLikes();
        $this->assign("guess_member_likes", $guess_member_likes);
        $this->assign("guess_member_likes_count", count($guess_member_likes));

        $this->assign("title_before", "团购专区");
        return view($this->style . 'Goods/groupBuyingArea');
    }

    /**
     * 积分兑换
     * 创建人：王永杰
     * 创建时间：2017年2月7日 15:47:00
     *
     * @return \think\response\View
     */
    public function goodsInfoPointExchange()
    {
        $this->goods_category = new GoodsCategoryService();
        $web_info = $this->web_site->getWebSiteInfo();
        if (empty($goodsid)) {
            $goodsid = request()->get('goodsid');
        }

        $goodsid = (int)$goodsid;
        if (!empty($goodsid)) {

            $default_client = request()->cookie("default_client", "");
            if ($default_client == "shop") {
            } elseif (request()->isMobile() && $web_info['wap_status'] == 1) {
                $redirect = __URL(__URL__ . "/wap/goods/goodsdetail?id=" . $goodsid);
                $this->redirect($redirect);
                exit();
            }

            // 当切换到PC端时，隐藏右下角返回手机端按钮
            if (!request()->isMobile() && $default_client == "shop") {
                $default_client = "";
            }
            $Config = new Config();
            $this->goods = new GoodsService();
            $this->goods_group = new GoodsGroupService();
            $this->shop = new ShopService();
            $this->member = new MemberService();
            $this->assign('goods_id', $goodsid); // 将商品id传入方便查询当前商品的评论
            $this->member->addMemberViewHistory($goodsid);
            // 商品详情
            $goods_info = $this->goods->getBasisGoodsDetail($goodsid);
            if ($goods_info["point_exchange_type"] == 0) {
                $this->error("该商品未设置积分兑换");
            }
            // dump($goods_info);exit();
            // 更新商品点击数
            $this->goods->updateGoodsClicks($goodsid);

            if (empty($goods_info)) {
                $redirect = __URL(__URL__ . '/index');
                $this->redirect($redirect);
            }
            if ($this->getIsOpenVirtualGoodsConfig() == 0 && $goods_info['goods_type'] == 0) {
                $this->error("未开启虚拟商品功能");
            }

            // 检测商品是否限购，是否允许购买
            $goods_purchase_num = $goods_info['min_buy'] > 0 ? $goods_info['min_buy'] : 1;
            $goods_purchase_restriction = $this->goods->getGoodsPurchaseRestrictionForCurrentUser($goodsid, $goods_purchase_num);
            $this->assign("goods_purchase_restriction", $goods_purchase_restriction);

            // 获取当前时间
            $current_time = $this->getCurrentTime();
            $this->assign('ms_time', $current_time);

            $seoconfig = $Config->getSeoConfig($this->instance_id);
            if (!empty($goods_info['keywords'])) {
                $seoconfig['seo_meta'] = $goods_info['keywords']; // 关键词
            }
            $seoconfig['seo_desc'] = $goods_info['goods_name'];
            // 标题title(商品详情页面)
            $this->assign("title_before", $goods_info['goods_name']);
            $this->assign("seoconfig", $seoconfig);
            $this->assign("goods_sku_count", count($goods_info["sku_list"]));
            $this->assign("spec_list", count($goods_info["spec_list"]));
            $this->assign("shop_id", $goods_info['shop_id']); // 所属店铺id
            // 图片必须都存在才行
            $default_gallery_img = "";
            if (count($goods_info["img_list"]) > 0) {
                $default_gallery_img = $goods_info["img_list"][0]["pic_cover_big"];
            }
            $this->assign("default_gallery_img", $default_gallery_img);

            // 店内商品销量排行榜
            $goods_rank = $this->goods->getGoodsRankViewList(1, 5, array(
                "ng.category_id" => $goods_info["category_id"]
            ), "ng.sales desc");
            $this->assign("goods_rank", $goods_rank["data"]);

            // 店内商品收藏数排行榜
            $goods_collection = $this->goods->getGoodsRankViewList(1, 5, array(
                "ng.category_id" => $goods_info["category_id"]
            ), "ng.collects desc");
            $this->assign("goods_collection", $goods_collection["data"]);

            // 当前用户是否收藏了该商品,uid是从baseController获取到的
            $is_member_fav_goods = -1;
            if (isset($this->uid)) {
                $is_member_fav_goods = $this->member->getIsMemberFavorites($this->uid, $goodsid, 'goods');
            }
            $this->assign("is_member_fav_goods", $is_member_fav_goods);

            $evaluates_count = $this->goods->getGoodsEvaluateCount($goodsid);
            $this->assign('evaluates_count', $evaluates_count);

            $integral_flag = 0; // 是否是积分商品

            if ($goods_info["point_exchange_type"] == 1) {
                $integral_flag++;
                // 积分中心-->商品详情界面
            }
            $this->assign("integral_flag", $integral_flag);

            $consult_list = array();
            // 购买咨询 全部
            $consult_list[0] = $this->goods->getConsultList(1, 5, [
                'goods_id' => $goodsid
            ], 'consult_addtime desc');
            // 商品咨询
            $consult_list[1] = $this->goods->getConsultList(1, 5, [
                'goods_id' => $goodsid,
                'ct_id' => 1
            ], 'consult_addtime desc');

            // 支付咨询
            $consult_list[2] = $pay_consult_list = $this->goods->getConsultList(1, 5, [
                'goods_id' => $goodsid,
                'ct_id' => 2
            ], 'consult_addtime desc');

            // 发票及保险咨询
            $consult_list[3] = $invoice_consult_list = $this->goods->getConsultList(1, 5, [
                'goods_id' => $goodsid,
                'ct_id' => 3
            ], 'consult_addtime desc');

            $this->assign('consult_list', $consult_list);

            if (!$goods_info["category_id"] == "") {
                $category_name = $this->goods_category->getCategoryParentQuery($goods_info["category_id"]);
            } else {
                $category_name = "全部分类";
            }
            $this->assign("category_name", $category_name);
            // 获取商品的优惠劵
            $goods_coupon_list = $this->goods->getGoodsCoupon($goodsid, $this->uid);
            $this->assign("goods_coupon_list", $goods_coupon_list);

            // 获取商品优惠券数量
            $coupon_count = count($goods_coupon_list);
            $this->assign('coupon_count', $coupon_count);
            $this->assign("goods_info", $goods_info);
            // 浏览历史
            $member_histrorys = $this->getMemberHistories();
            $this->assign('member_histrorys', $member_histrorys);

            // 猜您喜欢
            $guess_member_likes = $this->member->getGuessMemberLikes();
            $this->assign("guess_member_likes", $guess_member_likes);
            $this->assign("guess_member_likes_count", count($guess_member_likes));

            // 商品品牌
            $goods_brand = new GoodsBrand();
            $brand_detial = $goods_brand->getGoodsBrandInfo($goods_info['brand_id']);
            $this->assign("brand_detial", $brand_detial);

            // 组合商品
            $promotion = new Promotion();
            $comboPackageGoodsArray = $promotion->getComboPackageGoodsArray($goodsid);
            $this->assign("comboPackageGoodsArray", $comboPackageGoodsArray);

            // 商品阶梯优惠
            $goodsLadderPreferentialList = $this->goods->getGoodsLadderPreferential([
                "goods_id" => $goodsid
            ], "quantity desc", "quantity,price");
            $this->assign("goodsLadderPreferentialList", array_reverse($goodsLadderPreferentialList));
            $this->assign("goodsLadderPreferentialListStr", json_encode($goodsLadderPreferentialList));
            $merchant_service_list = $Config->getExistingMerchantService($this->instance_id);
            $this->assign("merchant_service_list", $merchant_service_list);

            // 积分抵现比率
            $integral_balance = 0; // 积分可抵金额
            $point_config = $promotion->getPointConfig();
            if ($point_config["is_open"] == 1) {
                if ($goods_info['max_use_point'] > 0 && $point_config['convert_rate'] > 0) {
                    $integral_balance = $goods_info['max_use_point'] * $point_config['convert_rate'];
                }
            }
            $this->assign("integral_balance", $integral_balance);

            // 添加足迹
            if ($this->uid > 0) {
                $this->goods->addGoodsBrowse($goodsid, $this->uid);
            }

            return view($this->style . 'Goods/goodsInfoPointExchange');
        } else {
            $redirect = __URL(__URL__ . '/index');
            $this->redirect($redirect);
        }
    }

    /**
     * 专题活动列表页面
     */
    public function promotionTopic()
    {
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
        
        // 浏览历史
        $member_histrorys = $this->getMemberHistories();
        $this->assign('member_histrorys', $member_histrorys);
        
        // 猜您喜欢
        $guess_member_likes = $this->member->getGuessMemberLikes();
        $this->assign("guess_member_likes", $guess_member_likes);
        $this->assign("guess_member_likes_count", count($guess_member_likes));
        // dump($list);
        return view($this->style . 'Goods/promotionTopic');
    }

    public function promotionTopicGoods()
    {
        $topic_id = request()->get('topic_id', 0);

        if (!is_numeric($topic_id)) {
            $this->error("没有获取到专题信息");
        }
        $promotion = new Promotion();
        $topic_goods = $promotion->getPromotionTopicDetail($topic_id);
        $this->assign('info', $topic_goods);
        return view($this->style . 'Goods/' . $topic_goods['pc_topic_template']);
    }
}