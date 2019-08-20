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
    public function buy_gold_shovel($user_id,$num,$money){
        Db::startTrans();
        for ($i=0;$i<$num;$i++){
            $data['user_id']=$user_id;
            $data['rank_time']=time();
            $data['add_time']=time();
            $res=Db::name('ranking')->insertGetId($data);
            if(!$res){
                Db::rollback();
                return ['status' => -1, 'msg' => '订单生成错误！'];
            }
        }

    }
}