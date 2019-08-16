<?php

namespace app\common\logic;
use app\common\model\Coupon;
use think\Db;

/**
 *
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
        $r=Db::name('jackpot')->where('id',1)->setInc('integral_num',$money/2);
        $re=Db::name('users')->where(['id'=>$user_id])->setDec('balance',$money);
        if(!$re||!$r){
            Db::rollback();
            return ['status' => -2, 'msg' => '余额扣取失败或者奖池增加失败！'];
        }else{
            $detail=[];
            $detail['user_id']=$user_id;
            $detail['type']=8;//买道具
            $detail['money']=-$balance;
            $detail['createtime']=time();
            $detail['intro']='购买'.$num.'个道具';
            $ids=Db::name('moneydetail')->insertGetId($detail);
            if(!$ids){
                Db::rollback();
                return ['status' => -2, 'msg' => '扣款log生成失败，则购买失败！'];
            }
            $buy_prop = Db::name('config')->where(['name'=>'buy_prop','inc_type'=>'taojin'])->value('value');
            $balance_unlock=$buy_prop*$balance;
            if($balance_unlock>$user['lock_balance']){
                $balance_unlock=$user['lock_balance'];//如果可解冻余额超过本身的冻结余额多，则解冻当前所有冻结余额
            }
            if($balance_unlock!=0){
                $ress=Db::name('users')->where(['id'=>$user_id])->setInc('balance',$balance_unlock);
                $rs=Db::name('users')->where(['id'=>$user_id])->setDec('lock_balance',$balance_unlock);
                if(!$ress||!$rs){
                    Db::rollback();
                    return ['status' => -2, 'msg' => '冻结余额解冻失败！'];
                }
            }
            $detail=[];
            $detail['user_id']=$user_id;
            $detail['type']=3;//解冻
            $detail['money']=$balance_unlock;
            $detail['createtime']=time();
            $detail['intro']='冻结余额';
            $ids=Db::name('moneydetail')->insertGetId($detail);
            if(!$ids){
                Db::rollback();
                return ['status' => -2, 'msg' => '解冻log生成失败，购买失败！'];
            }
            //代理返点
        }
        for ($i=0;$i<$num;$i++){
            $data['user_id'] = $user_id;
            $data['user_name'] = M('users')->where(['id'=>$user_id])->value('nick_name');
            $data['rank_time'] = time();
            $data['add_time'] = time();
            $res = Db::name('ranking')->insertGetId($data);
            if(!$res){
                Db::rollback();
                return ['status' => -2, 'msg' => '订单生成错误！'];
            }
        }
        Db::commit();
        return ['status' => 1, 'msg' => '购买成功！'];
    }

    /*
     * 三倍出局随机选取某个值
     */
    public function triple_out($balance_give_integral,$double_percent,$triple_out,$luck_time,$goods_money=20){

        $where['add_time']=['gt',$luck_time];
        $where['out_source']=0;//没有抽奖
        $where['rank_status']=0;//没有出局
        //随机抽取一条符合条件的数据
        $ranking=Db::name('ranking')->where($where)->limit(1)->orderRaw('rand()')->find();
        if(!$ranking){
            return true;//已经没有符合条件的数据了。返回已经完成
        }
        $user=Db::name('users')->where(['id'=>$ranking['user_id']])->find();
        if(!$user){
            return false;
        }
        $money=($goods_money*3)-($goods_money*3*$double_percent/100);//三倍、扣除手续费
        Db::startTrans();
        $res=Db::name('users')->where(['id'=>$ranking['user_id']])->setInc('lock_balance',$money);
        $data=[];
        $data['rank_status']=1;//出局
        $data['out_source']=$triple_out;//出局源
        $r=Db::name('ranking')->where('id',$ranking['id'])->update($data);
        if(!$res||!$r){
            Db::rollback();
            return false;
        }
        //赠送糖果
        $tg_num=ceil($goods_money*3/$balance_give_integral);//赠送糖果取整
        $data=[];
        $data['num']=$tg_num;
        $data['user_id']=$ranking['user_id'];
        $data['end_time']=time()+24*3600;//24小时过期
        $data['add_time']=time();
        $ids=Db::name('give')->insertGetId($data);
        $detail['user_id']=$user['id'];
        $detail['typefrom']=1;
        $detail['type']=10;//出局赠送
        $detail['money']=$money;
        $detail['createtime']=time();
        $detail['intro']='三倍出局获得冻结余额';
        $id=Db::name('moneydetail')->insertGetId($detail);
        if(!$id||!$ids){
            Db::rollback();
            return false;
        }
        //TODO   代理费用
        Db::commit();
        return false;
    }

    /*
     * 两倍出局
     */
    public function double_out($balance_give_integral,$double_percent,$double_out,$goods_money=20){
        $where['out_source']=0;//没有抽奖
        $where['rank_status']=0;//没有出局
        $ranking=Db::name('ranking')->where($where)->limit(1)->order('id')->find();
        if(!$ranking){
            return true;//已经没有符合条件的数据了。返回已经完成
        }
        $user=Db::name('users')->where(['id'=>$ranking['user_id']])->find();
        if(!$user){
            return false;
        }
        $money=($goods_money*2)-($goods_money*2*$double_percent/100);//两倍、扣除手续费
        Db::startTrans();
        $res=Db::name('users')->where(['id'=>$ranking['user_id']])->setInc('lock_balance',$money);
        $data=[];
        $data['rank_status']=1;//出局
        $data['out_source']=$double_out;//出局源
        $r=Db::name('ranking')->where('id',$ranking['id'])->update($data);
        if(!$res||!$r){
            Db::rollback();
            return false;
        }
        //赠送糖果
        $tg_num=ceil($goods_money*2/$balance_give_integral);//赠送糖果取整
        $data=[];
        $data['num']=$tg_num;
        $data['user_id']=$ranking['user_id'];
        $data['end_time']=time()+24*3600;//24小时过期
        $data['add_time']=time();
        $ids=Db::name('give')->insertGetId($data);
        $detail['user_id']=$user['id'];
        $detail['typefrom']=1;
        $detail['type']=10;//出局赠送
        $detail['money']=$money;
        $detail['createtime']=time();
        $detail['intro']='三倍出局获得冻结余额';
        $id=Db::name('moneydetail')->insertGetId($detail);
        if(!$id||!$ids){
            Db::rollback();
            return false;
        }
        //TODO   代理费用
        Db::commit();
        return false;
    }
    /*
     * 抽奖
     */
    public function reward($user_id,$money,$double_percent){
        $user_money=$money-$money*$double_percent/100;
        Db::startTrans();
        $res=Db::name('users')->where('id',$user_id)->setInc('balance',$user_money);
        if(!$res){
            Db::rollback();
            return false;
        }
        $detail['user_id']=$user_id;
        $detail['type']=9;//中奖
        $detail['money']=$money;
        $detail['createtime']=time();
        $detail['intro']='中奖获得余额';
        $id=Db::name('moneydetail')->insertGetId($detail);
        if(!$id){
            Db::rollback();
            return false;
        }
        //TODO   代理费用
        Db::commit();
        return false;
    }
}