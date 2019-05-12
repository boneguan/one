<?php
/**
 * BaseController.php
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
namespace app\lead\controller;

use data\service\GoodsCategory;
use data\service\GoodsBrand;
use data\service\Goods;
use data\model\NsGoodsSpecModel;
use data\model\NsGoodsSpecValueModel;
use data\model\AlbumPictureModel;
use data\service\Album;
class Ecshop extends BaseController
{
    public function __construct()
    {
      parent::__construct();
      
    }
   /**
    *导入测试(non-PHPdoc)
    * @see \app\lead\controller\BaseController::test()
    */
    public function test(){
     
        $data = $this->table('category')->select();
        var_dump($data);
    }
    /**
     *  导入商品分类
     */
    public function leadCategory(){
        $data = $this->table('category')->select();
        $goods_category = new GoodsCategory();
        if(!empty($data))
        {
            foreach ($data as $k => $v)
            {
                $category_id = $v['cat_id'];
                $category_name = $v['cat_name'];
                $short_name = $v['cat_name'];
                $pid = $v['parent_id'];
                $is_visible = 1;
                $key_words = $v['keywords'];
                $description = $v['cat_desc'];
                $goods_category->addOrEditGoodsCategory($category_id, $category_name, $short_name, $pid, $is_visible, $key_words, $description, 0, '');
            }
        }
        return 1;
    }
    /**
     * 导入商品品牌
     */
    public function leadBrand(){
        $data = $this->table('brand')->select();
        $goods_brand = new GoodsBrand();
        if(!empty($data))
        {
            foreach ($data as $k => $v)
            {
                $brand_id = $v['brand_id'];
                $brand_name =  $v['brand_name'];
                $brand_initial = '';
                $brand_class = '';
                if(!empty($v['brand_logo']))
                {
                    $brand_pic = 'upload/ecshop/'.$v['brand_logo'];
                }else{
                    $brand_pic = '';
                }
                
                $brand_recommend = 0;
                $sort = $v['sort_order'];
                $brand_ads = 0;
                $category_name = '';
                $category_id_1 = 0;
                $category_id_2 = 0;
                $category_id_3 = 0;
                $goods_brand->addOrUpdateGoodsBrand($brand_id,0, $brand_name, $brand_initial, $brand_class, $brand_pic, $brand_recommend, $sort, $brand_category_name = '', $category_id_array = '', $brand_ads, $category_name, $category_id_1, $category_id_2, $category_id_3);
           
            }
        return 1;
        }
    
    }
/*     public function addOrUpdateGoodsBrand($brand_id, $shop_id = 0, $brand_name, $brand_initial, $brand_class, $brand_pic, $brand_recommend, $sort, $brand_category_name = '', $category_id_array = '', $brand_ads, $category_name, $category_id_1, $category_id_2, $category_id_3)
    {
        Cache::tag("niu_goods_brand")->clear();
        $data = array(
            "brand_id" => $brand_id,
            'shop_id' => $shop_id,
            'brand_name' => $brand_name,
            'brand_initial' => $brand_initial,
            'brand_pic' => $brand_pic,
            'brand_recommend' => $brand_recommend,
            'sort' => $sort,
            'brand_ads' => $brand_ads,
            'category_name' => $category_name,
            'category_id_1' => $category_id_1,
            'category_id_2' => $category_id_2,
            'category_id_3' => $category_id_3
        );
        if ($brand_id != "") {
            $goods_brand_model = new NsGoodsBrandModel();
            $res = $goods_brand_model->save($data);
            $data['brand_id'] = $goods_brand_model->brand_id;
            hook("goodsBrandSaveSuccess", $data);
            return $goods_brand_model->brand_id;
        } else {
            $res = $this->goods_brand->save($data, [
                "brand_id" => $brand_id
            ]);
            $data['brand_id'] = $brand_id;
            hook("goodsBrandSaveSuccess", $data);
            return $res;
        }
    
        // TODO Auto-generated method stub
    } */
    /**
     * 导入商品类型
     */
    public function leadAttribute(){
        $goods = new Goods();
        $attribute_list = $this->table('goods_type')->select();
        if(!empty($attribute_list))
        {
            foreach ($attribute_list as $k => $v)
            {
                $attribute_name = $v['cat_name'];
                $is_use = 1;
                $spec_id_array = '';
                $sort = 0;
                $attribute_value_list = $this->table('attribute')->select();
                $value_string = '';
                foreach ($attribute_value_list as $k_value => $v_value)
                {
                    if($v_value['attr_input_type'] == 1)
                    {
                        $type = 1;
                    }else{
                        if($v_value['attr_type'] == 0 )
                            {
                                $type = 2;
                            }else{
                                $type = 3;
                            }
                    }
                    $value = str_replace("\r\n",',',$v_value['attr_values']);
                   $value_string = $value_string.$v_value['attr_name'].'|'.$type.'|'.$v_value['sort_order'].'|'.'1'.'|'.$value.';';
                }
                $goods->addAttributeService($attribute_name, $is_use, $spec_id_array, $sort, $value_string);
            }
        }
        return 1;
        
    }
    /**
     * 导入商品规格
     */
    public function leadGoodsSpec(){
           $goods_attr_list = $this->table('goods_attr')->select();
           //添加规格
           foreach ($goods_attr_list as $k => $v)
           {
               $goods_spec = new NsGoodsSpecModel();
                $count = $goods_spec->getCount(['spec_id' => $v['attr_id']]);
                $count_attr = $this->table('goods_attr')->where(['attr_id'=>$v['attr_id']])->count();
                if($count == 0 && $count_attr > 0)
                {
                    $attribute_value_info = $this->table('attribute')->where(['attr_id' => $v['attr_id']])->select();
                    $data = array(
                        'spec_id' => $v['attr_id'],
                        'shop_id' => 0,
                        'spec_name' => $attribute_value_info[0]['attr_name'],
                        'sort' => 0,
                        'create_time' => time()
                    );
                    $goods_spec = new NsGoodsSpecModel();
                    $goods_spec->save($data);
                }
            
              
           }
           //修改规格以及添加规格值
           $goods_spec = new NsGoodsSpecModel();
           $spec_list = $goods_spec->getQuery('', '*', '');
           foreach ($spec_list as $k => $v)
           {
               //对应添加规格值
               $attribute_value_info = $this->table('attribute')->where(['attr_id' => $v['spec_id']])->select();
               if(!empty($attribute_value_info[0]['attr_values']))
               {
                   $attribute_value_info_array = explode("\r\n", $attribute_value_info[0]['attr_values']);
                   foreach ($attribute_value_info_array as $k_value => $v_value)
                   {
                       $goods_spec_value_model = new NsGoodsSpecValueModel();
                        $data = array(
                            'spec_id' => $v['spec_id'],
                            'spec_value_name' => $v_value,
                            'sort' => 0,
                            'create_time' => time()
                        );
                        $goods_spec_value_model->save($data);
                   }
               }
               
           }
           return 1;
           
        
    }
    /**
     * 导入商品
     */
    public function leadGoods(){
        $goods = new Goods();
        $goods_list = $this->table('goods')->select();
        foreach ($goods_list as $k => $v)
        {
            //处理相册图片
            //添加主图
            $album = new Album();
            $pic_id = $album->addPicture(rand(1111111111,99999999999), '', 30, 'upload/ecshop'.$v['original_img'], 0, 0, 'upload/ecshop'.$v['goods_img'], 0, 0, 'upload/ecshop'.$v['goods_img'], 0, 0, 'upload/ecshop'.$v['goods_thumb'], 0, 0, 'upload/ecshop'.$v['goods_thumb'], 0, 0,0, '', '', '');
            $img_array = $pic_id;
            //添加多图
            $pic_list = $this->table("goods_gallery")->where(['goods_id' => $v['goods_id']])->select();
            if(!empty($pic_list))
            {
                foreach ($pic_list as $k_pic_list=> $v_pic_list)
                {
                    $ext_pic_id = $album->addPicture(rand(1111111111,99999999999), '', 30, 'upload/ecshop'.$v_pic_list['original_img'], 0, 0, 'upload/ecshop'.$v_pic_list['goods_img'], 0, 0, 'upload/ecshop'.$v_pic_list['goods_img'], 0, 0, 'upload/ecshop'.$v_pic_list['goods_thumb'], 0, 0, 'upload/ecshop'.$v_pic_list['goods_thumb'], 0, 0,0, '', '', '');
                    $img_array = $img_array.','.$ext_pic_id;
                }
            }
            $attr_list = $this->table(goods_attr)->where(['goods_id' => $v['goods_id']])->select();
            $sku_str = '';
            $goods_spec_format = array();
            $goods_spec_array = array();
            $goods_spec_value_array = array();
            foreach ($attr_list as $k_attr=> $v_attr)
            {
                $goods_spec = new NsGoodsSpecModel();
                $goods_spec_value = new NsGoodsSpecValueModel();
                $goods_spec_info = $goods_spec->where(['spec_id' => $v_attr['attr_id']])->select();;
                //说明该规格存在
                if(!empty($goods_spec_info))
                {
                    
                    if(empty($goods_spec_array))
                    {
                        $goods_spec_array[] = $goods_spec_info[0];
                    }else{
                        $tag = 0;
                        foreach ($goods_spec_array as $k_spec_array => $v_spec_array)
                        {
                            if($v_spec_array == $goods_spec_info[0])
                            {
                                $tag = 1;
                                break;
                            }
                            if($tag == 0)
                            {
                                $goods_spec_array[] = $goods_spec_info[0];
                            }
                        }
                    }
                   
                    //查询对应skuid
                    $spec_value_info = $goods_spec_value->where(['spec_id' => $v_attr['attr_id'],'spec_value_name' => $v_attr['attr_value']])->select();
                    if(!empty($spec_value_info))
                    {
                        
                        $sku_price = $v['shop_price'] + $v_attr['attr_price'];
                        $sku_info = $spec_value_info[0]['spec_id'].':'. $spec_value_info[0]['spec_value_id'];
                        $sku_market_price = $v['market_price'];
                        if($sku_str == ""){
                            $sku_str = ''.$sku_info ."¦". $sku_price."¦".$sku_market_price."¦".$sku_market_price."¦".'0'."¦".'';
                        }else{
                            $sku_str .="§".$sku_info ."¦". $sku_price."¦".$sku_market_price."¦".$sku_market_price."¦".'0'."¦".'';
                        }
                        $goods_spec_value_array[] = $spec_value_info[0];
                      
                    }else{
                        $sku_str = '';
                    }
                    var_dump($sku_str);
                    
                }
            }
            //重新组装商品规格
            if(!empty($goods_spec_array))
            {
                foreach ($goods_spec_array as $k_spec_array => $v_spec_array)
                {
                    $value = array();
                    if(!empty($goods_spec_value_array))
                    {
                        foreach ($goods_spec_value_array as $k_goods_spec_value_array => $v_goods_spec_value_array)
                        {
                            if($v_goods_spec_value_array['spec_id'] == $v_spec_array['spec_id'])
                            {
                                $value[] = $goods_spec_value_array;
                            }
                        }
                    }
                    $goods_spec_format[] = array(
                        'spec_name' => $v_spec_array['spec_name'],
                        'spec_id'   => $v_spec_array['spec_id'],
                        'value'     => $value
                    );
                }
            }
            /*
             *sku结构 	if(sku_str == ""){
        	sku_str = sku_id +"¦"+value_array["sku_price"]+"¦"+value_array["market_price"]+"¦"+value_array["cost_price"]+"¦"+value_array["stock_num"]+"¦"+value_array["code"];
        }else{
        	sku_str +="§"+sku_id +"¦"+value_array["sku_price"]+"¦"+value_array["market_price"]+"¦"+value_array["cost_price"]+"¦"+value_array["stock_num"]+"¦"+value_array["code"];
	}
	
	
	
	[
	{"spec_name":"机身颜色","spec_id":55,"value":[{"spec_value_name":"砖石小鳄鱼皮","spec_name":"机身颜色","spec_id":55,"spec_value_id":169,"spec_show_type":3,"spec_value_data":"","spec_value_data_src":""}]},
	{"spec_name":"存储容量","spec_id":56,"value":[{"spec_value_name":"32GB","spec_name":"存储容量","spec_id":56,"spec_value_id":170,"spec_show_type":1,"spec_value_data":""}]},
	{"spec_name":"版本类型","spec_id":57,"value":[{"spec_value_name":"中国大陆","spec_name":"版本类型","spec_id":57,"spec_value_id":171,"spec_show_type":1,"spec_value_data":""}]}]
             */
            
            /***********************************************************************添加规格类型结束***********************************/
            var_dump($sku_str);
            $goods_id = 0;
            $goods_name = $v['goods_name'];
            $shopid = 0;
            $category_id = $v['cat_id'];
            $category_id_1 = 0;
            $category_id_2 = 0;
            $category_id_3 = 0;
            $supplier_id = 0;
            $brand_id = $v['brand_id'];
            $group_id_array = '';
            $goods_type = $v['goods_type'];
            $market_price = $v['market_price'];
            $price = $v['shop_price'];
            $cost_price = 0;
            $point_exchange_type = '';
            $point_exchange = 0;
            $give_point = 0;
            $is_member_discount = 0;
            $shipping_fee = 0;
            $shipping_fee_id = 0;
            $stock = 0;
            $max_buy = 0;
            $min_buy = 0;
            $min_stock_alarm = 0;
            $clicks = 0;
            $sales = 0;
            $collects = 0;
            $star = 0;
            $evaluates = 0;
            $shares = 0;
            $province_id = 0;
            $city_id = 0;
            $picture = $pic_id;
            $keywords = $v['keywords'];
            $introduction = $v['goods_brief'];
            $description = $v['goods_desc'];
            $QRcode = '';
            $code = $v['goods_sn'];
            $is_stock_visible = 0;
            $is_hot = $v['is_hot'];
            $is_recommend = $v['is_promote'];
            $is_new = $v['is_new'];
            $sort = $v['sort_order'];
            $image_array = $img_array;
            $sku_array = $sku_str;
            $state = 1;
            $sku_img_array = '';
            $goods_attribute_id = $v['goods_type'];
            $goods_attribute  = '';
            $goods_spec_format = json_encode($goods_spec_format);
            $goods_weight = $v['goods_weight'];
            $goods_volume = 0;
            $shipping_fee_type = 1;
            $extend_category_id = '';
            $sku_picture_values = '';
            $goods = new Goods();
            $goods->addOrEditGoods($goods_id, $goods_name, $shopid, $category_id, $category_id_1, $category_id_2, $category_id_3, $supplier_id, $brand_id, $group_id_array, $goods_type, $market_price, $price, $cost_price, $point_exchange_type, $point_exchange, $give_point, $is_member_discount, $shipping_fee, $shipping_fee_id, $stock, $max_buy, $min_buy, $min_stock_alarm, $clicks, $sales, $collects, $star, $evaluates, $shares, $province_id, $city_id, $picture, $keywords, $introduction, $description, $QRcode, $code, $is_stock_visible, $is_hot, $is_recommend, $is_new, $sort, $image_array, $sku_array, $state, $sku_img_array, $goods_attribute_id, $goods_attribute, $goods_spec_format, $goods_weight, $goods_volume, $shipping_fee_type, $extend_category_id, $sku_picture_values,'', '', '', '');
        }
      
    }
 
    
}