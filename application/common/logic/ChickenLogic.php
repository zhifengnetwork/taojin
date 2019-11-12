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
            return array('status'=>1,'msg'=>'饲料已经抢光，请下次早点来！','data'=>0);
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
        return array('status'=>1,'msg'=>'抢饲料成功！','data'=>0);
    }
    /*
     * 购买鸡
     */
    public function buy_chicken($user_id,$type,$money,$num){
        $usersM=Db::name('users');
        $user=$usersM->where('id',$user_id)->find();
        $where_chicken['user_id']=$user_id;
        $where_chicken['chicken_status']=0;//是否过期
        $chicken_num=Db::name('chicken')->where($where_chicken)->count();//当前多少只鸡
        $coop_chicken_num=Db::name('chicken_coop')->where('user_id',$user_id)->sum('num');
        if($coop_chicken_num!=$chicken_num){//没有自动修改鸡窝的鸡数量
            $sql='SELECT a.* FROM clt_chicken_coop a LEFT JOIN clt_chicken co ON co.coop_id=a.coop_id
                  WHERE a.num>(SELECT COUNT(*) FROM clt_chicken co WHERE co.coop_id=a.coop_id AND co.chicken_status=0 ) AND a.user_id='.$user_id .'  GROUP BY a.coop_id';
            $result = Db::query($sql);
            foreach ($result as $key=>$value){
                $where=[];
                $where['coop_id']=$value['coop_id'];
                $where['chicken_status']=0;
                $num_chicken_coop=Db::name('chicken')->where($where)->count();
                $data=[];
                $data['num']=$num_chicken_coop;
                Db::name('chicken_coop')->where('coop_id',$value['coop_id'])->update($data);
            }
        }
        Db::startTrans();
        $coop_num=Db::name('chicken_coop')->where('user_id',$user_id)->count();//当前多少只窝
//        if(($coop_num*3)<($chicken_num+$num)){//鸡窝不够
//            return array('status'=>-2,'msg'=>'鸡窝不够，请购买鸡窝！');
//        }
        if(($coop_num*3-$coop_chicken_num)<$num){//鸡窝不够
            return array('status'=>-2,'msg'=>'鸡窝不够，请购买鸡窝！');
        }
        if(!$this->chicken_log($user_id,$num,$money,0,$type)){
            Db::rollback();
            return array('status'=>-2,'msg'=>'购买失败！');
        }
        if($type==1){
            if($this->chicken_balance_log($user_id,0,0,$money,$user['chicken_balance'],'购买'.$num.'只鸡')){
                $re=$usersM->where('id',$user_id)->setDec('chicken_balance',$money);
                $r=$usersM->where('phone',18812345678)->setInc('chicken_balance',$money);//购买金沙回收系统账号
                $sys_id=$usersM->where('phone',18812345678)->value('id');
                $sys=$this->chicken_balance_log($sys_id,0,0,-$money,0,'用户'.$user_id.'购买'.$num.'只鸡');
                if(!$re||!$r||!$sys){
                    Db::rollback();
                    return array('status'=>-2,'msg'=>'扣款失败，购买失败！');
                }
            }else{
                Db::rollback();
                return array('status'=>-2,'msg'=>'购买失败！');
            }
        }elseif ($type==2){
            if($this->recharge_balance_log($user_id,0,18,-$money,$user['chicken_balance'],'购买'.$num.'只鸡')){
                $re=$usersM->where('id',$user_id)->setDec('recharge_balance',$money);
                $r=$usersM->where('phone',18812345678)->setInc('recharge_balance',$money);//购买金沙回收系统账号
                $sys_id=$usersM->where('phone',18812345678)->value('id');
                $sys=$this->recharge_balance_log($sys_id,0,17,$money,0,'用户'.$user_id.'购买'.$num.'个鸡窝');
                if(!$re||!$r||!$sys){
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
        $where=[];
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
        if(!$this->chicken_log($user_id,$num,$money,1,$type)){
            Db::rollback();
            return array('status'=>-2,'msg'=>'购买失败！');
        }
        if($type==1){
            if($this->chicken_balance_log($user_id,0,0,$money,$user['chicken_balance'],'购买'.$num.'个鸡窝')){
                $re=$usersM->where('id',$user_id)->setDec('chicken_balance',$money);
                $r=$usersM->where('phone',18812345678)->setInc('chicken_balance',$money);//购买金沙回收系统账号
                $sys_id=$usersM->where('phone',18812345678)->value('id');
                $sys=$this->chicken_balance_log($sys_id,0,0,-$money,0,'用户'.$user_id.'购买'.$num.'个鸡窝');
                if(!$re||!$r||!$sys){
                    Db::rollback();
                    return array('status'=>-2,'msg'=>'扣款失败，购买失败！');
                }
            }else{
                Db::rollback();
                return array('status'=>-2,'msg'=>'购买失败！');
            }
        }elseif ($type==2){
            if($this->recharge_balance_log($user_id,0,17,-$money,$user['chicken_balance'],'购买'.$num.'个鸡窝')){
                $re=$usersM->where('id',$user_id)->setDec('recharge_balance',$money);
                $r=$usersM->where('phone',18812345678)->setInc('recharge_balance',$money);//购买金沙回收系统账号
                $sys_id=$usersM->where('phone',18812345678)->value('id');
                $sys=$this->recharge_balance_log($sys_id,0,17,$money,0,'用户'.$user_id.'购买'.$num.'个鸡窝');
                if(!$re||!$r||!$sys){
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
            if(!$res||!$re){
//                Db::rollback();
                return array('status'=>-2,'msg'=>'喂养失败！');
            }
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
        if(!$chicken_list){
            return array('status'=>-2,'msg'=>'您没有需要收取的鸡蛋！');
        }
        $egg_num=0;//鸡蛋数
        $chicken_num=0;//鸡数
        $ids='';//过期鸡id
        $ids_c='';//产蛋鸡id
        Db::startTrans();
        foreach ($chicken_list as $key=>$value){
            if((120-$value['num'])>1){//超过1只蛋
                $egg_num=$egg_num+1;
                if(!$ids_c){
                    $ids_c=$value['chicken_id'];
                }else{
                    $ids_c=$ids_c.','.$value['chicken_id'];
                }
                $data=[];
                $data['num']=$value['num']+1;
                $data['status']=0;
                $data_s=$chickenM->where('chicken_id',$value['chicken_id'])->update($data);
                if(!$data_s){
                    Db::rollback();
                    return array('status'=>-2,'msg'=>'收取失败,鸡更新错误！');
                }
            }else{
                if(120-$value['num']>0){
                    $egg_num=$egg_num+(120-$value['num']);
                }
                if(!$ids){
                    $ids=$value['chicken_id'];
                }else{
                    $ids=$ids.','.$value['chicken_id'];
                }
                $chicken_coop=Db::name('chicken_coop')->where('coop_id',$value['coop_id'])->setDec('num',1);
                if(!$chicken_coop){
                    Db::rollback();
                    return array('status'=>-2,'msg'=>'收取失败,鸡窝更新错误！');
                }
            }
            $chicken_num=$chicken_num+1;
        }
        $user=Db::name('users')->where('id',$user_id)->find();
        if($ids){
            $where=[];
            $where['chicken_id']=array('in',$ids);
            $data=[];
            $data['num']=120;//产蛋
            $data['chicken_status']=1;//过期
            $data['status']=0;
            $r=Db::name('chicken')->where($where)->update($data);
            if(!$r){
                Db::rollback();
                return array('status'=>-2,'msg'=>'收取失败，蛋过期处理失败！');
            }
        }
        $user_balance['egg_num']=$user['egg_num']+$egg_num*2;//鸡蛋收益
        $user_balance['chicken_integral']=$user['chicken_integral']+$egg_num;//一个蛋一个糖果
        $re=Db::name('users')->where(['id'=>$user_id])->update($user_balance);//用户获得收益
        $egg_log=$this->egg_log($user_id,0,3,$egg_num*2,$user['egg_num'],'鸡蛋收益');
        $chicken_integral=$this->chicken_integral_log($user_id,0,3,$egg_num,$user['chicken_integral'],'鸡蛋收益获得糖果');
        if(!$re||!$egg_log||!$chicken_integral){
            Db::rollback();
            return array('status'=>-2,'msg'=>'收取失败！');
        }
        if(!$this->agent_money($user_id,$egg_num,$chicken_num)){
            Db::rollback();
            return array('status'=>-2,'msg'=>'收取失败,代理收益错误！');
        }
        Db::commit();
        return array('status'=>1,'msg'=>'收取成功！');
    }
    /*
    * 返佣+代理计算
    */
    public function agent_money($user_id,$egg_num,$chicken_num){
        $users=Db::name('users')->where('id',$user_id)->find();
        $p_1=Db::name('users')->where('id',$users['p_1'])->find();
//        $user_level=$users['level'];//当前用户级别
        //返佣
        if($p_1){
            if(!$this->user_balance($p_1['id'],$egg_num,20,'直推返利',4,$chicken_num)){
                return false;
            }
            //用上级作为循环查找对象
            if($p_1['level']==6){//上级是矿场主
                if(!$this->user_balance($p_1['id'],$egg_num,20,'代理返点',7,$chicken_num)){//矿场主
                    return false;
                }
                if($p_1['p_1']){
                    $data=$this->above($p_1['p_1'],$p_1['level']);
                    if($data){
                        if(!$this->user_balance($data['data']['id'],$egg_num,5,'平级代理返点',8,$chicken_num)){//平级奖
                            return false;
                        }
                    }
                }
            }elseif($p_1['level']==1){//上级是矿队长
                if(!$this->user_balance($p_1['id'],$egg_num,5,'代理返点',6,$chicken_num)){//上级  矿队长
                    return false;
                }
                if($p_1['p_1']){
                    $data=$this->above($p_1['p_1'],$p_1['level']);
                    if($data){
                        if($data['type']==0){//平级
                            if(!$this->user_balance($data['data']['id'],$egg_num,5,'平级代理返点',8,$chicken_num)){//平级奖
                                return false;
                            }
                            $data_fl=$this->above($data['data']['p_1'],$data['data']['level']);
                            if($data_fl){
                                if ($data_fl['type']==1){
                                    if(!$this->user_balance($data_fl['data']['id'],$egg_num,15,'代理返点',7,$chicken_num)){//顶级   矿场主
                                        return false;
                                    }
                                    $data_fl=$this->above($data_fl['data']['p_1'],$data_fl['data']['level']);
                                    if($data_fl){
                                        if(!$this->user_balance($data_fl['data']['id'],$egg_num,5,'平级代理返点',8,$chicken_num)){//平级奖
                                            return false;
                                        }
                                    }
                                }
                            }
                        }elseif($data['type']==1){
                            if(!$this->user_balance($data['data']['id'],$egg_num,15,'代理返点',7,$chicken_num)){//顶级   矿场主
                                return false;
                            }
                            $data=$this->above($data['data']['p_1'],$data['data']['level']);
                            if($data){
                                if(!$this->user_balance($data['data']['id'],$egg_num,5,'平级代理返点',8,$chicken_num)){//平级奖
                                    return false;
                                }
                            }
                        }

                    }
                }
            }else{//上级没有代理返点
                if($p_1['p_1']){
                    $data=$this->above($p_1['p_1'],1);
                    if($data){
                        if($data['type']==0){
                            if(!$this->user_balance($data['data']['id'],$egg_num,5,'代理返点',6,$chicken_num)){//上级  矿队长
                                return false;
                            }
                            $data_level=$this->above($data['data']['p_1'],$data['data']['level']);
                            if($data_level){
                                if($data_level['type']==0){
                                    if(!$this->user_balance($data_level['data']['id'],$egg_num,5,'平级代理返点',8,$chicken_num)){//平级奖
                                        return false;
                                    }
                                    $data_fl=$this->above($data_level['data']['p_1'],$data_level['data']['level']);
                                    if($data_fl){
                                        if ($data_fl['type']==1){
                                            if(!$this->user_balance($data_fl['data']['id'],$egg_num,15,'代理返点',7,$chicken_num)){//顶级   矿场主
                                                return false;
                                            }
                                            $data_fl=$this->above($data_fl['data']['p_1'],$data_fl['data']['level']);
                                            if($data_fl){
                                                if(!$this->user_balance($data_fl['data']['id'],$egg_num,5,'平级代理返点',8,$chicken_num)){//平级奖
                                                    return false;
                                                }
                                            }
                                        }
                                    }
                                }elseif ($data_level['type']==1){
                                    if(!$this->user_balance($data['data']['id'],$egg_num,15,'代理返点',7,$chicken_num)){//顶级   矿场主
                                        return false;
                                    }
                                    $data=$this->above($data['data']['p_1'],$data['data']['level']);
                                    if($data){
                                        if(!$this->user_balance($data['data']['id'],$egg_num,5,'平级代理返点',8,$chicken_num)){//平级奖
                                            return false;
                                        }
                                    }
                                }
                            }
                        }elseif($data['type']==1){
                            if(!$this->user_balance($data['data']['id'],$egg_num,20,'代理返点',7,$chicken_num)){//顶级   矿场主
                                return false;
                            }
                            $data=$this->above($data['data']['p_1'],$data['data']['level']);
                            if($data){
                                if(!$this->user_balance($data['data']['id'],$egg_num,5,'平级代理返点',8,$chicken_num)){//平级奖
                                    return false;
                                }
                            }
                        }

                    }
                }
            }
            //上上级计算返佣
            $p_2=Db::name('users')->where('id',$users['p_2'])->find();
            if($p_2){
                if(!$this->user_balance($p_2['id'],$egg_num,20,'间推返利',5,$chicken_num)){
                    return false;
                }
            }
        }
//        if($surplus_money>0){//未拿完扣取费用，则钱归系统账号
//            $user_admin=Db::name('users')->where('phone',18866666666)->find();
//            if($user_admin){
//                $surplus=$money*$surplus_money/100;
//                $detail=[];
//                $detail['user_id']=$user_admin['id'];
//                $detail['type']=16;//矿场主
//                $detail['money']=$surplus;
//                $detail['createtime']=time();
//                $detail['intro']='返佣未拿完';
//                $ids=Db::name('moneydetail')->insertGetId($detail);
//                if(!$ids){
//                    return false;
//                }
//            }
//        }
        return true;

    }
    /*
     * 查找上级代理等级
     */
    public function above($user_id,$level){
        $user=Db::name('users')->field('id,p_1,level')->where('id',$user_id)->find();
        if(!$user){
            return false;
        }
        if($user['level']>$level){
            return ['data'=>$user,'type'=>1];//上级
        }
        if($level==$user['level']){
            return ['data'=>$user,'type'=>0];//平级
        }
        if($user['p_1']){//还有上级
            return $this->above($user['p_1'],$level);//递归寻找上级或平级
        }else{
            return false;
        }
    }
    /*
     * 代理返佣
     */
    public function user_balance($user_id,$egg_num,$percent,$intro,$type,$chicken_num){
        $user=Db::name('users')->where('id',$user_id)->find();
        $where['user_id']=$user_id;
        $where['chicken_status']=0;
        $user_chicken=Db::name('chicken')->where($where)->order('chicken_id')->select();//当前用户鸡数量
        $user_chicken_num=count($user_chicken);
        if($user_chicken_num==0){
            return true;
        }
        if($user_chicken_num>=$chicken_num){//用户鸡数量大于返佣用户，则取返佣用户数量
            $user_chicken_num=$chicken_num;

        }
        if($egg_num>$user_chicken_num){//鸡蛋收益大于鸡数量
            $money=$user_chicken_num*$percent/100*2;//收益
            $chicken_integral=$user_chicken_num;
        }else{//鸡蛋收益小于鸡数量
            $money=$egg_num*$percent/100*2;//收益
            $chicken_integral=$egg_num;
        }
        $user_egg=$chicken_integral*$percent/100;//返利鸡蛋、糖果
        $user_balance['egg_num']=$user['egg_num']+$money;//鸡蛋收益
        $user_balance['chicken_integral']=$user['chicken_integral']+$user_egg;//一个蛋一个糖果
        $re=Db::name('users')->where(['id'=>$user_id])->update($user_balance);//用户获得收益
        $egg_log=$this->egg_log($user_id,0,$type,$money,$user['egg_num'],$intro);
        $chicken_integral_log=$this->chicken_integral_log($user_id,0,$type,$money/2,$user['chicken_integral'],$intro);
        if(!$re||!$egg_log||!$chicken_integral_log){
            return false;
        }

        foreach ($user_chicken as $key=>$value){
            $where['chicken_id']=$value['chicken_id'];
            if($user_egg>(120-$value['num'])){//鸡蛋数量大于当前鸡剩余量
                $data=[];
                $data['num']=120;//产蛋
                $data['chicken_status']=1;//过期
                $data['status']=0;
                $r=Db::name('chicken')->where($where)->update($data);
                if(!$r){
                    return false;
                }
            }else{
                $data=[];
                $data['num']=$value['num']+$user_egg;
                $data['status']=0;
                $data_s=Db::name('chicken')->where($where)->update($data);//鸡下蛋
                if(!$data_s){
                    return false;
                }
                break;//下蛋完成，跳出循环
            }
        }
        return true;
    }
    /**
     * 鸡笼金沙
     * @param $user_id  用户id
     * @param $to_user_id  赠送用户id
     * @param $type    类型
     * @param $money   改变金沙
     * @param $balance  原有金沙
     * @param $desc  说明描述
     * @return boolean
     */
    public function chicken_balance_log($user_id,$to_user_id,$type,$money,$balance,$desc){
        $data['user_id']=$user_id;
        $data['to_user_id']=$to_user_id;
        $data['type']=$type;
        $data['money']=-$money;
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
        $detail['user_id']=$user_id;
        $detail['type']=$type;//买道具
        $detail['money']=-$money;
        $detail['balance']=$balance;
        $detail['be_user_id']=$to_user_id;
        $detail['createtime']=time();
        $detail['intro']=$desc;
        $id=Db::name('moneydetail')->insertGetId($detail);
        if($id){
            return true;
        }else{
            return false;
        }
    }
    /**
     * 鸡蛋收益log
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
     * 鸡笼糖果
     * @param $user_id  用户id
     * @param $to_user_id  赠送用户id
     * @param $type    类型
     * @param $money   改变糖果
     * @param $balance  原有金沙
     * @param $desc  说明描述
     * @return boolean
     */
    public function chicken_integral_log($user_id,$to_user_id,$type,$money,$balance,$desc){
        $data['user_id']=$user_id;
        $data['to_user_id']=$to_user_id;
        $data['type']=$type;
        $data['money']=$money;
        $data['balance']=$balance;
        $data['desc']=$desc;
        $data['add_time']=time();
        $id=Db::name('chicken_integral_log')->insertGetId($data);
        if($id){
            return true;
        }else{
            return false;
        }
    }
    /**
     * 释放冻结余额log
     * @param $user_id  用户id
     * @param $type    类型
     * @param $money   改变金沙
     * @param $balance  原有金沙
     * @param $desc  说明描述
     * @return boolean
     */
    public function release_balance_log($user_id,$type,$money,$balance,$desc){
        $data['user_id']=$user_id;
        $data['type']=$type;
        $data['money']=$money;
        $data['balance']=$balance;
        $data['desc']=$desc;
        $data['add_time']=time();
        $id=Db::name('release_balance_log')->insertGetId($data);
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
    public function chicken_log($user_id,$num,$money,$type,$pay_type){
        $data['user_id']=$user_id;
        $data['num']=$num;
        $data['money']=$money;
        $data['type']=$type;
        $data['pay_type']=$pay_type;
        $data['add_time']=time();
        $id=Db::name('chicken_log')->insertGetId($data);
        if($id){
            return true;
        }else{
            return false;
        }
    }
}