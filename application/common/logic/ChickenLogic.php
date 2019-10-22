<?php

namespace app\common\logic;
use app\common\model\Coupon;
use think\Db;

/**
 *
 */
class ChickenLogic
{
    /*
     * 抢饲料
     */
    public function rob_feed($user_id,$type){
        $feed=Db::name('feed');
        $feed_logM=Db::name('feed_log');
        $user_feed=$feed->where('user_id',$user_id)->find();
        $chicken_num=Db::name('chicken')->where('user_id',$user_id)->count();
        if($chicken_num==0){
            return array('status'=>-2,'msg'=>'请先购买鸡，再抢饲料！');
        }
        $source=strtotime(date("Y-m-d")." 00:00:00");
        $where['source']=$source;
        $where['type']=$type;
        $count=$feed_logM->where($where)->count();
        $feed_num= Db::name('config')->where(['name'=>'feed_num','inc_type'=>'chicken'])->value('value');
        if($count>=$feed_num){
            return array('status'=>-2,'msg'=>'饲料已经抢光，请下次早点来！');
        }
        $where['user_id']=$user_id;
        $feed_log=$feed_logM->where($where)->find();
        if($feed_log){
            return array('status'=>-2,'msg'=>'您已经抢过了！');
        }else{
            Db::startTrans();
            if($user_feed){
                $user_feed['num']=1;
                $user_feed['up_time']=time();
                $re=$feed->update($user_feed);
                $feed_log['user_id']=$user_id;
                $feed_log['source']=$source;
                $feed_log['type']=$type;
                $feed_log['add_time']=time();
                $ids=$feed_logM->insertGetId($feed_log);
                if(!$re||!$ids){
                    Db::rollback();
                    return array('status'=>-2,'msg'=>'抢饲料失败！');
                }
            }else{
                $user_feed['user_id']=$user_id;
                $user_feed['num']=1;
                $user_feed['up_time']=time();
                $user_feed['add_time']=time();
                $id=$feed->insertGetId($user_feed);
                $feed_log['user_id']=$user_id;
                $feed_log['source']=$source;
                $feed_log['type']=$type;
                $feed_log['add_time']=time();
                $ids=$feed_logM->insertGetId($feed_log);
                if(!$id||!$ids){
                    Db::rollback();
                    return array('status'=>-2,'msg'=>'抢饲料失败！');
                }
            }
        }
        Db::commit();
        return array('status'=>1,'msg'=>'抢饲料成功！');
    }
    /*
     * 购买鸡
     */
    public function buy_chicken($user_id,$type,$money,$num){
        $usersM=Db::name('users');
        $user=$usersM->where('id',$user_id)->find();
        Db::startTrans();
        $where_chicken['user_id']=$user_id;
        $where_chicken['chicken_status']=0;//是否过期
        $chicken_num=Db::name('chicken')->where($where_chicken)->count();//当前多少只鸡
        $coop_num=Db::name('chicken_coop')->where('user_id',$user_id)->count();//当前多少只窝
        if(($coop_num*3)<($chicken_num+$num)){//鸡窝不够
            return array('status'=>-2,'msg'=>'鸡窝不够，请购买鸡窝！');
        }
        if(!$this->chicken_log($user_id,$num)){
            Db::rollback();
            return array('status'=>-2,'msg'=>'购买失败！');
        }
        if($type==1){
            if($this->chicken_balance_log($user_id,0,0,-$money,$user['chicken_balance'],'购买'.$num.'只鸡')){
                $re=$usersM->where('id',$user_id)->setDec('chicken_balance',$money);
                if(!$re){
                    Db::rollback();
                    return array('status'=>-2,'msg'=>'扣款失败，购买失败！');
                }
            }else{
                Db::rollback();
                return array('status'=>-2,'msg'=>'购买失败！');
            }
        }elseif ($type==2){
            if($this->recharge_balance_log($user_id,0,0,-$money,$user['chicken_balance'],'购买'.$num.'只鸡')){
                $re=$usersM->where('id',$user_id)->setDec('chicken_recharge_balance',$money);
                if(!$re){
                    Db::rollback();
                    return array('status'=>-2,'msg'=>'扣款失败，购买失败！');
                }
            }else{
                Db::rollback();
                return array('status'=>-2,'msg'=>'购买失败！');
            }
        }else{
            Db::rollback();
            return array('status'=>-2,'msg'=>'购买失败，类型不存在！');
        }
        $where['user_id']=$user_id;
        $where['num']=array('neq',3);
        $ids='';
        $chicken_coop=Db::name('chicken_coop')->where($where)->order('coop_id')->select();
        foreach ($chicken_coop as $key=>$value){
            if($num+$value['num']>3){
                if($ids){
                    $ids=$ids.','.$value['coop_id'];
                }else{
                    $ids=$value['coop_id'];
                }
                for($i=0;$i<(3-$value['num']);$i++){
                    $chicken['coop_id']=$value['coop_id'];
                    $chicken['user_id']=$user_id;
                    $chicken['add_time']=time();
                    $chicken_id=Db::name('chicken')->insertGetId($chicken);
                    if(!$chicken_id){
                        Db::rollback();
                        return array('status'=>-2,'msg'=>'购买失败！');
                    }
                    $num=$num-1;
                }
            }else{
                for($i=0;$i<$num;$i++){
                    $chicken['coop_id']=$value['coop_id'];
                    $chicken['user_id']=$user_id;
                    $chicken['add_time']=time();
                    $chicken_id=Db::name('chicken')->insertGetId($chicken);
                    if(!$chicken_id){
                        Db::rollback();
                        return array('status'=>-2,'msg'=>'购买失败！');
                    }
                }
                $chicken_coop_data['num']=$value['num']+$num;
                $chicken_coop_data['coop_id']=$value['coop_id'];
                Db::name('chicken_coop')->update($chicken_coop_data);
                $num=0;
                break;
            }
        }
        if($ids){
            $where=[];
            $where['coop_id']=array('in',$ids);
            $data=[];
            $data['num']=3;
            Db::name('chicken_coop')->where($where)->update($data);
        }
        Db::commit();
        return array('status'=>1,'msg'=>'购买成功！');
    }
    public function buy_chicken_coop($user_id,$type,$money,$num){
        $usersM=Db::name('users');
        $user=$usersM->where('id',$user_id)->find();
        Db::startTrans();
        if($type==1){
            if($this->chicken_balance_log($user_id,0,0,-$money,$user['chicken_balance'],'购买'.$num.'个鸡窝')){
                $re=$usersM->where('id',$user_id)->setDec('chicken_balance',$money);
                if(!$re){
                    Db::rollback();
                    return array('status'=>-2,'msg'=>'扣款失败，购买失败！');
                }
            }else{
                Db::rollback();
                return array('status'=>-2,'msg'=>'购买失败！');
            }
        }elseif ($type==2){
            if($this->recharge_balance_log($user_id,0,0,-$money,$user['chicken_balance'],'购买'.$num.'个鸡窝')){
                $re=$usersM->where('id',$user_id)->setDec('chicken_recharge_balance',$money);
                if(!$re){
                    Db::rollback();
                    return array('status'=>-2,'msg'=>'扣款失败，购买失败！');
                }
            }else{
                Db::rollback();
                return array('status'=>-2,'msg'=>'购买失败！');
            }
        }else{
            Db::rollback();
            return array('status'=>-2,'msg'=>'购买失败，类型不存在！');
        }
        $data=array();
        for ($i=0;$i<$num;$i++){
            $data[]=[
                'user_id'=>$user_id,
                'add_time'=>time()
            ];
        }
        $result =  Db::name('chicken_coop')->insertAll($data);
        if(!$result){
            Db::rollback();
            return array('status'=>-2,'msg'=>'购买失败！');
        }
        Db::commit();
        return array('status'=>1,'msg'=>'购买成功！');
    }

    /*
     * 喂养鸡
     */
    public function feed_chicken($user_id){
        $feed=Db::name('feed');
        $chickenM=Db::name('chicken');
        $user_feed=$feed->where('user_id',$user_id)->find();
        if($user_feed['num']>0) {
            $where['user_id']=$user_id;
            $where['chicken_status']=0;
            $chicken_list=$chickenM->where($where)->select();//当前用户所有鸡
            $ids='';
            foreach ($chicken_list as $key=>$value){
                if($ids){
                    $ids=$ids.','.$value['chicken_id'];
                }else{
                    $ids=$value['chicken_id'];
                }
            }
            $where=[];
            $where['chicken_id']=array('in',$ids);
            $data=[];
            $data['status']=1;//喂养
//            Db::startTrans();
            $res=Db::name('chicken')->where($where)->update($data);
            $re=Db::name('feed')->where('user_id',$user_id)->setDec('num',1);
//            if(!$res||!$re){
//                Db::rollback();
//                return array('status'=>-2,'msg'=>'喂养失败！');
//            }
//            Db::commit();
            return array('status'=>1,'msg'=>'喂养成功！');
        }else{
            return array('status'=>-2,'msg'=>'您没有饲料，不能喂养！');
        }
    }
    /*
     * 收取蛋
     */
    public function harvest_egg($user_id){
        $chickenM=Db::name('chicken');
        $where['user_id']=$user_id;
        $where['status']=1;//喂养了
        $where['chicken_status']=0;
        $chicken_list=$chickenM->where($where)->select();//当前用户已经喂养过的所有鸡
    }
    /**
     * 鸡笼金沙
     * @param $user_id  用户id
     * @param $to_user_id  赠送用户id
     * @param $type    类型
     * @param $money   改变金沙
     * @param $balance  原有金沙
     * @return boolean
     */
    public function chicken_balance_log($user_id,$to_user_id,$type,$money,$balance,$desc){
        $data['user_id']=$user_id;
        $data['to_user_id']=$to_user_id;
        $data['type']=$type;
        $data['money']=$money;
        $data['balance']=$balance;
        $data['desc']=$desc;
        $data['add_time']=time();
        $id=Db::name('chicken_balance_log')->insertGetId($data);
        if($id){
            return true;
        }else{
            return false;
        }
    }
    /**
     * 鸡笼余额
     * @param $user_id  用户id
     * @param $to_user_id  赠送用户id
     * @param $type    类型
     * @param $money   改变金沙
     * @param $balance  原有金沙
     * @param $desc  说明描述
     * @return boolean
     */
    public function recharge_balance_log($user_id,$to_user_id,$type,$money,$balance,$desc){
        $data['user_id']=$user_id;
        $data['to_user_id']=$to_user_id;
        $data['type']=$type;
        $data['money']=$money;
        $data['balance']=$balance;
        $data['desc']=$desc;
        $data['add_time']=time();
        $id=Db::name('recharge_balance_log')->insertGetId($data);
        if($id){
            return true;
        }else{
            return false;
        }
    }
    /**
     * 鸡蛋log
     * @param $user_id  用户id
     * @param $to_user_id  赠送用户id
     * @param $type    类型
     * @param $money   改变金沙
     * @param $balance  原有金沙
     * @param $desc  说明描述
     * @return boolean
     */
    public function egg_log($user_id,$to_user_id,$type,$money,$balance,$desc){
        $data['user_id']=$user_id;
        $data['to_user_id']=$to_user_id;
        $data['type']=$type;
        $data['money']=$money;
        $data['balance']=$balance;
        $data['desc']=$desc;
        $data['add_time']=time();
        $id=Db::name('egg_log')->insertGetId($data);
        if($id){
            return true;
        }else{
            return false;
        }
    }

    /**
     * @param $user_id  用户id
     * @param $num   购买数量
     * @return bool
     */
    public function chicken_log($user_id,$num){
        $data['user_id']=$user_id;
        $data['num']=$num;
        $data['add_time']=time();
        $id=Db::name('chicken_log')->insertGetId($data);
        if($id){
            return true;
        }else{
            return false;
        }
    }
}