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
        $system = Db::name('system')->field('id,name,title,money,logo')->where('id=1')->find();
        $user = Db::name('users')->where(['id'=>$user_id])->find();
        $balance = $user['balance'];
        $money=$system['money'] * $num;
        if($balance < ( $money ) ){
            return ['status' => -1, 'msg' => '余额不足！'];
        }
        Db::startTrans();
        $re=Db::name('users')->where(['id'=>$user_id])->setDec('balance',$money);
        if(!$re){
            Db::rollback();
            return ['status' => -2, 'msg' => '余额扣取失败！'];
        }
        $detail=[];
        $detail['user_id']=$user_id;
        $detail['type']=8;//买道具
        $detail['money']=$balance;
        $detail['createtime']=time();
        $detail['intro']='购买'.$num.'个道具';
        $ids=Db::name('moneydetail')->insertGetId($detail);
        if(!$ids){
            Db::rollback();
            return ['status' => -2, 'msg' => '购买失败！'];
        }
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
                return ['status' => -2, 'msg' => '订单生成错误！'];
            }
        }

    }
}