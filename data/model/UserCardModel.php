<?php
/**
 * UserModel.php
 *
 * @date : 2015.1.17
 * @version : v1.0.0.0
 */

namespace data\model;
use think\Db;
use data\model\BaseModel as BaseModel;
/**
 * 用户会员卡表
 */
class UserCardModel extends BaseModel {
    protected $table = 'ns_user_card';
    protected $rule = [
    ];
    protected $msg = [

    ];

    public function allotStartAndEnd($whereCard,$allotNumCount,$uid){
        $resIndex = db($this->table)->where($whereCard)->order('card_id')->limit(0,$allotNumCount)->select();
        $whereSel['start'] = $resIndex[0]['card_id'];
        $whereSel['end']  = $resIndex[$allotNumCount-1]['card_id'];

        $map['card_id'] = array(array('egt',$whereSel['start']),array('elt',$whereSel['end']));
        $save['leader'] = $uid;
        $res = db($this->table)->where($map)->update($save);
        return $res;
    }

}
