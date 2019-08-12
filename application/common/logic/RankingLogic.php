<?php

namespace app\common\logic;
use app\common\model\Coupon;
use think\Db;

/**
 * 登录逻辑
 */
class RankingLogic
{

    /*
     * 购买金铲子
     */
    public function buy_gold_shovel($user_id,$num){
        Db::startTrans();
        for ($i=0;$i<$num;$i++){
            $data['user_id'] = $user_id;
            $data['user_name'] = M('users')->where(['id'=>$user_id])->value('nick_name');
            $data['rank_time'] = time();
            $data['add_time'] = time();
            $res = Db::name('ranking')->insertGetId($data);
            if($res > 0){
                Db::commit();
                return ['status' => 1, 'msg' => '购买成功！'];
            }else{
                Db::rollback();
                return ['status' => -1, 'msg' => '订单生成错误！'];
            }
        }

    }
}