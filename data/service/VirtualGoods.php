<?php
/**
 * AuthGroup.php
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
 * @date : 2015.4.24
 * @version : v1.0.0.0
 */
namespace data\service;

use data\api\IVirtualGoods;
use data\model\NsVirtualGoodsModel;
use data\model\NsVirtualGoodsTypeModel;
use data\service\BaseService as BaseService;
use data\model\NsVirtualGoodsViewModel;
use data\model\AlbumPictureModel;
use data\model\NsVirtualGoodsGroupModel;
use data\model\NsGoodsModel;
use data\model\NsGoodsSkuModel;
use think\Log;

class VirtualGoods extends BaseService implements IVirtualGoods
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \data\api\IVirtualGoods::editVirtualGoodsGroup()
     */
    public function editVirtualGoodsGroup($virtual_goods_group_name, $interfaces, $create_time)
    {
        // TODO Auto-generated method stub
    }

    /**
     * 获取虚拟商品类型列表
     *
     * @param 当前页 $page_index            
     * @param 显示页数 $page_size            
     * @param 条件 $condition            
     * @param 排序 $order            
     * @param 字段 $field            
     */
    function getVirtualGoodsTypeList($page_index, $page_size = 0, $condition = array(), $order = "virtual_goods_type_id desc", $field = "*")
    {
        $virtual_goods_type_model = new NsVirtualGoodsTypeModel();
        $res = $virtual_goods_type_model->pageQuery($page_index, $page_size, $condition, $order, $field);
        return $res;
    }

    /**
     * 根据id查询虚拟商品类型
     *
     * @ERROR!!!
     *
     * @see \data\api\IVirtualGoods::getVirtualGoodsTypeById()
     */
    function getVirtualGoodsTypeById($virtual_goods_type_id)
    {
        $virtual_goods_type_model = new NsVirtualGoodsTypeModel();
        $res = $virtual_goods_type_model->getInfo([
            'virtual_goods_type_id' => $virtual_goods_type_id
        ], "*");
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IVirtualGoods::getVirtualGoodsTypeInfo()
     */
    function getVirtualGoodsTypeInfo($condition = '')
    {
        $virtual_goods_type_model = new NsVirtualGoodsTypeModel();
        $res = $virtual_goods_type_model->getInfo($condition, "*");
        return $res;
    }

    /**
     * 编辑虚拟商品类型
     *
     * @ERROR!!!
     *
     * @see \data\api\IVirtualGoods::editVirtualGoodsType()
     */
    public function editVirtualGoodsType($virtual_goods_type_id, $virtual_goods_group_id, $validity_period, $confine_use_number, $value_info, $goods_id)
    {
        $virtual_goods_type_model = new NsVirtualGoodsTypeModel();
        $res = 0;
        if ($virtual_goods_type_id == 0) {
            
            // 添加
            $data = array(
                'virtual_goods_group_id' => $virtual_goods_group_id,
                'validity_period' => $validity_period,
                'confine_use_number' => $confine_use_number,
                'shop_id' => $this->instance_id,
                'create_time' => time(),
                'relate_goods_id' => $goods_id
            );
            
            // 如果不是点卡的话，添加配置信息
            if ($virtual_goods_group_id != 3) {
                $data['value_info'] = $value_info;
            }
            $res = $virtual_goods_type_model->save($data);
        } else {
            
            // 修改
            $data = array(
                'validity_period' => $validity_period,
                'confine_use_number' => $confine_use_number,
                'relate_goods_id' => $goods_id
            );
            
            // 如果不是点卡的话，添加配置信息
            if ($virtual_goods_group_id != 3) {
                $data['value_info'] = $value_info;
            }
            $res = $virtual_goods_type_model->save($data, [
                'virtual_goods_type_id' => $virtual_goods_type_id
            ]);
        }
        
        if ($virtual_goods_group_id == 3) {
            
            if($value_info != ''){
                $value_array = json_decode($value_info, true);
                foreach ($value_array as $item) {
                    $this->addVirtualGoods($this->instance_id, '', 0.00, '', '', 0, '', $validity_period, 0, 0, 0, $confine_use_number, - 2, $goods_id, $item['remark']);
                }    
                
                //更新库存
                $this->setVirtualCardByGoodsStock($goods_id);
            }
        }
        return $res;
    }

    /**
     * 设置虚拟商品类型启用禁用
     * 创建时间：2017年11月23日 19:37:28 王永杰
     *
     * @ERROR!!!
     *
     * @see \data\api\IVirtualGoods::setVirtualGoodsTypeIsEnabled()
     */
    public function setVirtualGoodsTypeIsEnabled($virtual_goods_type_id, $is_enabled)
    {
        $virtual_goods_type_model = new NsVirtualGoodsTypeModel();
        $data['is_enabled'] = $is_enabled;
        $res = $virtual_goods_type_model->save($data, [
            'virtual_goods_type_id' => $virtual_goods_type_id
        ]);
        return $res;
    }

    /**
     * 根据id删除虚拟商品类型
     * 创建时间：2017年11月23日 19:37:19 王永杰
     *
     * @ERROR!!!
     *
     * @see \data\api\IVirtualGoods::deleteVirtualGoodsType()
     */
    public function deleteVirtualGoodsType($virtual_goods_type_id)
    {
        $virtual_goods_type_model = new NsVirtualGoodsTypeModel();
        $res = $virtual_goods_type_model->destroy([
            'virtual_goods_type_id' => [
                'in',
                $virtual_goods_type_id
            ]
        ]);
        return $res;
    }

    /**
     * 添加虚拟商品
     * 创建时间：2017年11月23日 19:37:08 王永杰
     *
     * @ERROR!!!
     *
     * @see \data\api\IVirtualGoods::addVirtualGoods()
     */
    public function addVirtualGoods($shop_id, $virtual_goods_name, $money, $buyer_id, $buyer_nickname, $order_goods_id, $order_no, $validity_period, $start_time, $end_time, $use_number, $confine_use_number, $use_status, $goods_id, $remark)
    {
        $virtual_goods_model = new NsVirtualGoodsModel();
        
        $data = array(
            'virtual_code' => $this->generateVirtualCode($shop_id),
            'virtual_goods_name' => $virtual_goods_name,
            'money' => $money,
            'buyer_id' => $buyer_id,
            'buyer_nickname' => $buyer_nickname,
            'order_goods_id' => $order_goods_id,
            'order_no' => $order_no,
            'validity_period' => $validity_period,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'use_number' => $use_number,
            'confine_use_number' => $confine_use_number,
            'use_status' => $use_status,
            'shop_id' => $shop_id,
            'create_time' => time(),
            "goods_id" => $goods_id,
            'remark' => $remark
        );
        
        $res = $virtual_goods_model->save($data);
        
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IVirtualGoods::updateVirtualGoods()
     */
    public function updateVirtualGoods($virtual_goods_id, $virtual_goods_name, $money, $buyer_id, $buyer_nickname, $order_goods_id, $order_no, $start_time, $end_time)
    {
        $virtual_goods_model = new NsVirtualGoodsModel();
        $data = array(
            'virtual_goods_name' => $virtual_goods_name,
            'money' => $money,
            'buyer_id' => $buyer_id,
            'buyer_nickname' => $buyer_nickname,
            'order_goods_id' => $order_goods_id,
            'order_no' => $order_no,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'use_status' => 1,
            'create_time' => time(),
            'use_number' => 1,
        );
        
        $res = $virtual_goods_model->save($data, [
            'virtual_goods_id' => $virtual_goods_id
        ]);
        return $res;
    }

    /**
     * 生成虚拟码
     * 创建时间：2017年11月23日 19:37:03 王永杰
     */
    public function generateVirtualCode($shop_id)
    {
        $time_str = date('YmdHis');
        $rand_code = rand(0, 999999);
        
        $virtual_code = $time_str . $rand_code . $shop_id;
        $virtual_code = md5($virtual_code);
        $virtual_code = substr($virtual_code, 16, 32);
        // $virtual_goods_model = new NsVirtualGoodsModel();
        // $order_obj = $virtual_goods_model->getFirstData([
        // "shop_id" => $shop_id
        // ], "virtual_goods_id DESC");
        // $num = 0;
        // if (! empty($order_obj)) {
        // $order_no_max = $order_obj["virtual_code"];
        // if (empty($order_no_max)) {
        // $num = 1;
        // } else {
        // if (substr($time_str, 0, 12) == substr($order_no_max, 0, 12)) {
        // $max_no = substr($order_no_max, 12, 4);
        // $num = $max_no * 1 + 1;
        // } else {
        // $num = 1;
        // }
        // }
        // } else {
        // $num = 1;
        // }
        // $virtual_code = $time_str . sprintf("%04d", $num);
        // $count = $virtual_goods_model->getCount(['virtual_code'=>$virtual_code]);
        // if($count>0){
        // return $this->generateVirtualCode($shop_id);
        // }
        return $virtual_code;
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \data\api\IVirtualGoods::deleteVirtualGoods()
     */
    public function deleteVirtualGoods($virtual_code)
    {
        // TODO Auto-generated method stub
    }

    /**
     * 根据主键id删除虚拟商品
     * 创建时间：2018年3月6日16:33:57
     * 
     * @param unknown $virtual_goods_id            
     */
    public function deleteVirtualGoodsById($virtual_goods_id)
    {
        $virtual_goods_model = new NsVirtualGoodsModel();
        
        $data['virtual_goods_id'] = [
            'in',
            $virtual_goods_id
        ];
        $res = $virtual_goods_model->destroy($data);
        return $res;
    }

    /**
     * 根据订单编号查询虚拟商品列表
     *
     * @ERROR!!!
     *
     * @see \data\api\IVirtualGoods::getVirtualGoodsListByOrderNo()
     */
    function getVirtualGoodsListByOrderNo($order_no)
    {
        $virtual_goods_model = new NsVirtualGoodsModel();
        $list = $virtual_goods_model->getQuery([
            "order_no" => $order_no
        ], "*", "virtual_goods_id asc");
        if (! empty($list)) {
            
            foreach ($list as $k => $v) {
                if ($v['use_status'] == - 1) {
                    $list[$k]['use_status_msg'] = '已过期';
                } elseif ($v['use_status'] == 0) {
                    $list[$k]['use_status_msg'] = '未使用';
                } elseif ($v['use_status'] == 1) {
                    $list[$k]['use_status_msg'] = '已使用';
                }
            }
            return $list;
        }
        return '';
    }

    /**
     * 获取虚拟商品列表
     */
    function getVirtualGoodsList($page_index, $page_size, $condition, $order = "")
    {
        $ns_virtual_goods_view = new NsVirtualGoodsViewModel();
        $list = $ns_virtual_goods_view->getViewList($page_index, $page_size, $condition, $order);
        foreach ($list["data"] as $k => $v) {
            $album_picture = new AlbumPictureModel();
            $picture_info = $album_picture->getInfo([
                "pic_id" => $v["picture"]
            ], "pic_cover_mid");
            $picture_info_src = '';
            if (empty($picture_info)) {
                $picture_info_src = '';
            } else {
                $picture_info_src = $picture_info["pic_cover_mid"];
            }
            $list["data"][$k]["picture_info"] = $picture_info_src;
        }
        return $list;
    }

    /**
     * 根据商品id查询点卡库存（虚拟商品列表）
     * 创建时间：2018年3月6日14:39:03 王永杰
     *
     * @param unknown $page_index            
     * @param unknown $page_size            
     * @param unknown $condition            
     * @param string $order            
     */
    public function getVirtualGoodsListByGoodsId($page_index, $page_size, $condition, $order = "")
    {
        $ns_virtual_goods_view = new NsVirtualGoodsViewModel();
        $list = $ns_virtual_goods_view->getViewList($page_index, $page_size, $condition, $order);
        return $list;
    }

    /**
     * 根据商品id查询点卡库存数量
     * 创建时间：2018年3月6日14:53:31
     *
     * @param unknown $goods_id            
     */
    public function getVirtualGoodsCountByGoodsId($goods_id)
    {
        $ns_virtual_goods_view = new NsVirtualGoodsViewModel();
        $res = $ns_virtual_goods_view->getCount([
            'goods_id' => $goods_id
        ]);
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IVirtualGoods::getVirtualGoodsGroup()
     */
    public function getVirtualGoodsGroup($condition = '1=1')
    {
        $virtual_group_model = new NsVirtualGoodsGroupModel();
        $list = $virtual_group_model->getQuery($condition, '*', '');
        return $list;
    }
    
    /**
     * (non-PHPdoc)
     * @see \data\api\IVirtualGoods::getVirtualGoodsInfo()
     */
    public function getVirtualGoodsGroupInfo($virtual_goods_group_id){
        
        $virtual_group_model = new NsVirtualGoodsGroupModel();
        $virtual_goods_group_info = $virtual_group_model->getInfo(['virtual_goods_group_id'=>$virtual_goods_group_id], '*');
        return $virtual_goods_group_info;
    }
    
    /**
     * (non-PHPdoc)
     * @see \data\api\IVirtualGoods::addBatchVirtualCard()
     */
    public function addBatchVirtualCard($virtual_goods_type_id, $goods_id, $virtual_card_json){
        
        $virtual_card_array = json_decode($virtual_card_json, true);
        $virtual_goods_type_info = $this->getVirtualGoodsTypeById($virtual_goods_type_id);
        foreach($virtual_card_array as $item){
            
            $this->addVirtualGoods($this->instance_id, '', 0.00, '', '', 0, '', $virtual_goods_type_info['validity_period'], 0, 0, 0, $virtual_goods_type_info['confine_use_number'], - 2, $goods_id, $item['remark']);
        }
        
        //更新商品库存
        $this->setVirtualCardByGoodsStock($goods_id);
        return 1;
    }
    
    /**
     * (non-PHPdoc)
     * @see \data\api\IVirtualGoods::setVirtualCardByGoodsStock()
     */
    public function setVirtualCardByGoodsStock($goods_id){
        
        $virtual_goods_type_model = new NsVirtualGoodsTypeModel();
        $virtual_goods_type_info = $virtual_goods_type_model->getInfo(['relate_goods_id'=>$goods_id], '*');
        
        if($virtual_goods_type_info['virtual_goods_group_id'] == 3){
            $virtual_goods_model = new NsVirtualGoodsModel();
            $virtual_count = $virtual_goods_model->getCount(['use_status'=> -2, 'goods_id'=> $goods_id]);
            
            $goods_model = new NsGoodsModel();
            $res = $goods_model->save(['stock'=> $virtual_count], ['goods_id'=> $goods_id]);
            
            $goods_sku_model = new NsGoodsSkuModel();
            $res = $goods_sku_model->save(['stock' => $virtual_count], ['goods_id'=> $goods_id]);
        }
    }
}