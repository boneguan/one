<?php
/**
 * User.php
 *
 * @date : 2015.4.24
 * @version : v1.0.0.0
 */
namespace data\service;

use data\model\NsGoodsModel;
use data\service\BaseService as BaseService;
use data\model\UserCardModel as UserCardModel;
use data\model\UserModel as UserModel;
use data\model\NsGoodsModel as GoodsModel;
use data\model\NsOrderModel;
use data\service\Order\OrderGoods;

class UserCard extends BaseService
{
    //密码字典
    private $dic = array(
        0=>'0', 1=>'1', 2=>'2', 3=>'3', 4=>'4', 5=>'5', 6=>'6', 7=>'7', 8=>'8',
        9=>'9', 10=>'A', 11=>'B', 12=>'C', 13=>'D', 14=>'E', 15=>'F', 16=>'G', 17=>'H',
        18=>'I',19=>'J', 20=>'K', 21=>'L', 22=>'M', 23=>'N', 24=>'O', 25=>'P', 26=>'Q',
        27=>'R',28=>'S', 29=>'T', 30=>'U', 31=>'V', 32=>'W', 33=>'X', 34=>'Y', 35=>'Z'
    );
    public $model;
    function __construct()
    {
        parent::__construct();
        $this->model = new UserCardModel();
    }


    /**
     * 会员卡 订单创建*
     * @return number|Exception
     */
    public function orderCardCreate($out_trade_no, $goods_id)
    {
        $order = new NsOrderModel();
        $order->startTrans();
        try {
            // 设定不使用会员余额支付
            $user_money = 0;

            // 获取购买人信息
            $buyer = new UserModel();
            $buyer_info = $buyer->getInfo([
                'uid' => $this->uid
            ], '*');
            // 订单商品费用
            $goods_money = '';
            $point = 0;
            // 获取订单邮费,订单自提免除运费
            $deliver_price = 0;
            $point_money = 0;
            // 订单来源
            if (isWeixin()) {
                $order_from = 1; // 微信
            } elseif (request()->isMobile()) {
                $order_from = 2; // 手机
            } else {
                $order_from = 3; // 电脑
            }
            // 订单支付方式

            // 订单待支付
            $order_status = 4; //订单完成
            // 购买商品获取积分数
            $give_point = 0;

            // 订单费用(具体计算)
            $order_money = 1;   // 会员卡订单费用都设置为 1元
            $pay_money = 1;
            $platform_money = 0;
            $tax_money = 0;

            $shipping_time = date("Y-m-d H:i:s", time());

            $data_order = array(
                'order_type' => 2,
                'order_no' => $this->createOrderNo(0),
                'out_trade_no' => $out_trade_no,
                'payment_type' => 5,
                'shipping_type' => 1,
                'order_from' => $order_from,
                'buyer_id' => $this->uid,
                'user_name' => $buyer_info['nick_name'],
                'buyer_ip' => 1,
                'buyer_message' => '',
                'buyer_invoice' => '',
                'shipping_time' => getTimeTurnTimeStamp($shipping_time), // datetime NOT NULL COMMENT '买家要求配送时间',
                'pay_time'  => time(),
                'receiver_mobile' => $buyer_info['user_tel'], // varchar(11) NOT NULL DEFAULT '' COMMENT '收货人的手机号码',
                'receiver_province' => 0, // int(11) NOT NULL COMMENT '收货人所在省',
                'receiver_city' => 0, // int(11) NOT NULL COMMENT '收货人所在城市',
                'receiver_district' => 0, // int(11) NOT NULL COMMENT '收货人所在街道',
                'receiver_address' => '', // varchar(255) NOT NULL DEFAULT '' COMMENT '收货人详细地址',
                'receiver_zip' => '', // varchar(6) NOT NULL DEFAULT '' COMMENT '收货人邮编',
                'receiver_name' => $buyer_info['real_name'], // varchar(50) NOT NULL DEFAULT '' COMMENT '收货人姓名',
                'shop_id' => 0, // int(11) NOT NULL COMMENT '卖家店铺id',
                'shop_name' => '', // varchar(100) NOT NULL DEFAULT '' COMMENT '卖家店铺名称',
                'goods_money' => $goods_money, // decimal(19, 2) NOT NULL COMMENT '商品总价',
                'tax_money' => $tax_money, // 税费
                'order_money' => $order_money, // decimal(10, 2) NOT NULL COMMENT '订单总价',
                'point' => $point, // int(11) NOT NULL COMMENT '订单消耗积分',
                'point_money' => $point_money, // decimal(10, 2) NOT NULL COMMENT '订单消耗积分抵多少钱',
                'coupon_money' => 0, // _money decimal(10, 2) NOT NULL COMMENT '订单代金券支付金额',
                'coupon_id' => 0, // int(11) NOT NULL COMMENT '订单代金券id',
                'user_money' => $user_money, // decimal(10, 2) NOT NULL COMMENT '订单预存款支付金额',
                'promotion_money' => 0.00, // decimal(10, 2) NOT NULL COMMENT '订单优惠活动金额',
                'shipping_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单运费',
                'pay_money' => $pay_money, // decimal(10, 2) NOT NULL COMMENT '订单实付金额',
                'refund_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单退款金额',
                'give_point' => $give_point, // int(11) NOT NULL COMMENT '订单赠送积分',
                'order_status' => $order_status, // tinyint(4) NOT NULL COMMENT '订单状态',
                'pay_status' => 2, // tinyint(4) NOT NULL COMMENT '订单付款状态',
                'shipping_status' => 0, // tinyint(4) NOT NULL COMMENT '订单配送状态',
                'review_status' => 0, // tinyint(4) NOT NULL COMMENT '订单评价状态',
                'feedback_status' => 0, // tinyint(4) NOT NULL COMMENT '订单维权状态',
                'user_platform_money' => $platform_money, // 平台余额支付
                'coin_money' => 0,
                'create_time' => time(),
                "give_point_type" => 0,
                'shipping_company_id' => 0,
                'fixed_telephone' => '' //固定电话
            ); // datetime NOT NULL DEFAULT 'CURRENT_TIMESTAMP' COMMENT '订单创建时间',

            $order = new NsOrderModel();
            $order->save($data_order);
            $order_id = $order->order_id;
            // 添加订单项
            $order_goods = new OrderGoods();
            $res_order_goods = $order_goods->addOrderGoods($order_id, $goods_sku_list);
            if (! ($res_order_goods > 0)) {
                $this->order->rollback();
                return $res_order_goods;
            }
            $this->addOrderAction($order_id, $this->uid, '会员卡创建订单：');

            $this->order->commit();
            return $order_id;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 会员卡列表
     * @param number $page_index
     * @param number $page_size
     * @param string $condition
     * @param string $order
     * @param string $field
     */
    public function getList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $result = $this->model->pageQuery($page_index, $page_size, $condition, $order,$field);
        $goods_data = array(); $leader_name = array();$user_name = array();
        $User = new UserModel();
        $Goods = new GoodsModel();

        foreach ($result['data'] as $k => $v) {
            if(!empty($v['goods_id'])){
                if(!empty($goods_data[$v['goods_id']])){
                    $result['data'][$k]['goods_name'] = $goods_data[$v['goods_id']];
                } else {
                    $tmp = $Goods->getInfo('goods_id='.$v['goods_id'],'goods_name,code');
                    $result['data'][$k]['goods_name'] = $tmp['goods_name'];
                    $result['data'][$k]['goods_code'] = $tmp['code'];
                }
            }
            if(!empty($v['leader'])){
                if(!empty($leader_name[$v['leader']])){
                    $tmp = $leader_name[$v['leader']];
                    $result['data'][$k]['leader_nick_name'] = $tmp['nick_name'];
                    $result['data'][$k]['leader_real_name'] = $tmp['real_name'];

                } else {
                    $tmp = $User->getInfo('uid='.$v['leader'],'nick_name,real_name');
                    $result['data'][$k]['leader_nick_name'] = $tmp['nick_name'];
                    $result['data'][$k]['leader_real_name'] = $tmp['real_name'];
                    $leader_name[$v['leader']] = $tmp;
                }
            }
            if(!empty($v['user_id'])){
                if(!empty($user_name[$v['user_id']])){
                    $tmp = $user_name[$v['user_id']];
                    $result['data'][$k]['user_nick_name'] = $tmp['nick_name'];
                    $result['data'][$k]['user_real_name'] = $tmp['real_name'];
                } else {
                    $tmp = $User->getInfo('uid='.$v['user_id'],'nick_name,real_name');
                    $result['data'][$k]['user_nick_name'] = $tmp['nick_name'];
                    $result['data'][$k]['user_real_name'] = $tmp['real_name'];
                    $user_name[$v['user_id']] = $tmp;
                }
            }
           /* $member_account = new MemberAccount();
            $result['data'][$k]['point'] = $member_account->getMemberPoint($v['uid'], '');
            $result['data'][$k]['balance'] = $member_account->getMemberBalance($v['uid']);
            $result['data'][$k]['coin'] = $member_account->getMemberCoin($v['uid']);*/
        }
        return $result;
    }
    /**
     * 
     * @return unknown
     */
    public function getUserCardInfo($number)
    {
        $res = $this->model->getInfo('number="'.$number.'"');
        return $res;
    }
    /**
     * 系统用户基础添加方式
     *
     * @param unknown $user_name            
     * @param unknown $password            
     * @param unknown $email            
     * @param unknown $mobile            
     */
    public function batch_add($goodsid, $numcount)
    {
        $data = array(
            'goods_id' => $goodsid,
            'instance_id' => 0,
            'leader' => 0,
            'user_id' => 0,
            'add_time' =>time()
        );
        $data_all = array();

        $number = array();
        $numcount = $numcount > 10000 ? 10000 : $numcount;
            for($i=0; $i < $numcount  ; $i++){
                $no = $this->createCardNo();
               //if(in_array($no,$number)){$i -- ; continue;}
                $number[] = $no;
            }
            $number = array_unique($number);
            $str = array();
            foreach($number as $v){
                $str[] = "'".$v."'";
            }


            //$condition = ['number', 'exp','in ( ' . implode(',',$str) . ')'];
            $where['number'] = array('in',$number);
            $ex_tmp = $this->model->getQuery($where,'number','');
           if(!empty($ex_tmp)){//存在先有的卡号
               $ex_number = array();
               foreach ($ex_tmp as $tmp){
                   $ex_number[] = $tmp['number'];
               }
               $insert_number = array_diff($number,$ex_number);
           }else{
               $insert_number = $number;
           }
            //for($i = 0, $cnt = count($insert_number); $i <$cnt; $i++){
             foreach ($insert_number as $tmp_number){
                $data['number'] = $tmp_number;
                $data_all[] = $data;
            }

             $res = $this->model->saveAll($data_all);
           if($res){
                return array('count',count($insert_number));
           }else{
               return array('count',0);
           }

    }

    /**
     * 分配方法
    */
    public function allot_card($goodsid,$allotNumCount,$uid){
        $whereCard['goods_id'] = $goodsid;
        $whereCard['leader'] = 0;
        $whereCard['user_id'] = array( 'eq', 0 );
        $startEnd = $this->model->allotStartAndEnd($whereCard,$allotNumCount,$uid);
        return $startEnd;
    }
    /**
     * 过滤特殊字符
     * @param unknown $str
     */
    private function filterStr($str)
    {
        if($str){
            $name = $str;
            $name = preg_replace_callback('/\xEE[\x80-\xBF][\x80-\xBF]|\xEF[\x81-\x83][\x80-\xBF]/',function ($matches) { return '';}, $name);
            $name = preg_replace_callback('/xE0[x80-x9F][x80-xBF]‘.‘|xED[xA0-xBF][x80-xBF]/S',function ($matches) { return '';}, $name);
            // 汉字不编码
            $name = json_encode($name);
            $name = preg_replace_callback("/\\\ud[0-9a-f]{3}/i", function ($matches) { return '';}, $name);
            if(!empty($name))
            {
                $name = json_decode($name);
                return $name;
            }else{
                return '';
            }
             
        }else{
            return '';
        }
    }
    /**
     * 创建生成用户名
     *
     * @return string
     */
    protected function createCardNo()
    {
        $user_name = $this->GetRandStr(1,'a') . date("ym") . $this->GetRandStr(2,0).date("dh").$this->GetRandStr(1,0);
        return $user_name;
    }
    /**
     * 随机数生成
     *
     * @return string
     */
    function GetRandStr($length, $tag){
        if($tag == 0) $str='ABCDEFGHJKLMNPQRSTUVWXYZ0123456789';
        else if($tag == 1) $str='0123456789';
        else if($tag == 'a')   $str='ABCDEFGHJKLMNPQRSTUVWXYZ';

        $len=strlen($str)-1;
        $randstr='';
        for($i=0;$i<$length;$i++){
            $num=mt_rand(0,$len);
            $randstr .= $str[$num];
        }
        return $randstr;
    }

    public function encodeID($int, $format=8) {
        $dics = $this->dic;
        $dnum = 36; //进制数
        $arr = array ();
        $loop = true;
        while ($loop) {
            $arr[] = $dics[bcmod($int, $dnum)];
            $int = bcdiv($int, $dnum, 0);
            if ($int == '0') {
                $loop = false;
            }
        }
        if (count($arr) < $format)
            $arr = array_pad($arr, $format, $dics[0]);
        return implode('', array_reverse($arr));
    }

    public function decodeID($ids) {
        $dics = $this->dic;
        $dnum = 36; //进制数
        //键值交换
        $dedic = array_flip($dics);
        //去零
        $id = ltrim($ids, $dics[0]);
        //反转
        $id = strrev($id);
        $v = 0;
        for ($i = 0, $j = strlen($id); $i < $j; $i++) {
            $v = bcadd(bcmul($dedic[$id {
            $i }
            ], bcpow($dnum, $i, 0), 0), $v, 0);
        }
        return $v;
    }

}

