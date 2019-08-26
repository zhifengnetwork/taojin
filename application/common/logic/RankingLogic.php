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
    public function buy_gold_shovel($user_id,$num,$today_start=0){
        $system = Db::name('system')->field('id,name,title,money,logo')->where('id=1')->find();
        $user = Db::name('users')->where(['id'=>$user_id])->find();
        $balance = $user['balance'];
        $money=$system['money'] * $num;
        if($balance < ( $money ) ){
            return ['status' => -2, 'msg' => '余额不足！'];
        }
        //注册奖励开关
        $if_register_reward = Db::name('config')->where(['name'=>'if_register_reward','inc_type'=>'taojin'])->value('value');
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
            $detail['money']=-$money;
            $detail['createtime']=time();
            $detail['intro']='购买'.$num.'个道具';
            $ids=Db::name('moneydetail')->insertGetId($detail);
            if(!$ids){
                Db::rollback();
                return ['status' => -2, 'msg' => '扣款log生成失败，则购买失败！'];
            }
            $buy_prop = Db::name('config')->where(['name'=>'buy_prop','inc_type'=>'taojin'])->value('value');
            $balance_unlock=$buy_prop*$money;
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
            }
        }
        //注册奖励
        if($if_register_reward){
            if(!$user['is_reward']&&$user['p_1']){//奖励上级
                $re_lock_balance=Db::name('config')->where(['name'=>'re_lock_balance','inc_type'=>'taojin'])->value('value');
                $yq_lock_balance=Db::name('config')->where(['name'=>'yq_lock_balance','inc_type'=>'taojin'])->value('value');
                $rsw=Db::name('users')->where(['id'=>$user_id])->setInc('lock_balance',$re_lock_balance);
                $reward=Db::name('users')->where(['id'=>$user_id])->update(['is_reward'=>1]);
                $yq=Db::name('users')->where(['id'=>$user['p_1']])->setInc('lock_balance',$yq_lock_balance);
                if(!$rsw||!$yq||!$reward){
                    Db::rollback();
                    return ['status' => -2, 'msg' => '注册奖励发放失败！'];
                }
                $detail=[];
                $detail['user_id']=$user_id;
                $detail['type']=1;//注册奖励
                $detail['typefrom']=1;//冻结余额
                $detail['money']=$re_lock_balance;
                $detail['createtime']=time();
                $detail['intro']='注册奖励'.$re_lock_balance;
                if(!Db::name('moneydetail')->insertGetId($detail)){
                    Db::rollback();
                    return ['status' => -2, 'msg' => '注册奖励log生成失败！'];
                }
                $detail=[];
                $detail['user_id']=$user['p_1'];
                $detail['type']=2;//邀请奖励
                $detail['typefrom']=1;//冻结余额
                $detail['money']=$yq_lock_balance;
                $detail['createtime']=time();
                $detail['intro']='邀请奖励'.$re_lock_balance;
                if(!Db::name('moneydetail')->insertGetId($detail)){
                    Db::rollback();
                    return ['status' => -2, 'msg' => '邀请奖励log生成失败！'];
                }
            }
        }
        if($today_start!=0){
            $today_start=$today_start;//01秒
        }
        $user_name=M('users')->where(['id'=>$user_id])->value('nick_name');
        for ($i=0;$i<$num;$i++){
            $data['user_id'] = $user_id;
            $data['user_name'] = $user_name;
            if($today_start!=0){
                $data['rank_time'] = $today_start;
                $today_start=$today_start+60;//+1分钟
            }else{
                $data['rank_time'] = time();
            }
            $data['money']=$system['money'];
            $data['add_time'] = time();
            $res = Db::name('ranking')->insertGetId($data);
            if(!$res){
                Db::rollback();
                return ['status' => -2, 'msg' => '订单生成错误！'];
            }
        }
        if($user['p_1']){
            $level_one=Db::name('user_level')->where('id',1)->find();//矿队长
            $level_two=Db::name('user_level')->where('id',6)->find();//矿场主
            $is_dl=$this->select_up($user['p_1'],$level_one,$level_two);
        }
        Db::commit();
        return ['status' => 1, 'msg' => '购买成功！'];
    }
    /*
     * 向上查找
     */
    public function select_up($user_id,$level_one,$level_two){
        $users=Db::name('users')->field('id,level')->where('id',$user_id)->select();
        if($users&&$users['level']==6){//找到一个上级是顶级，则上级的上级一定是顶级
            return true;//
        }
        //查找下级
        $list=Db::name('users')->field('id,level')->where('p_1',$users['id'])->select();
        $my_list=$list;
        $my_list=$this->select_all_down($list,$my_list);
        $num=count($my_list);//下级人数
        if($users['level']==0){//普通用户升级
            if($num>$level_one['num']){
                $money=$this->all_money($my_list);
                if($money>$level_one['yeji']){
                    $data['level']=1;
                    Db::name('users')->where('id',$users['id'])->update($data);
                }
            }
        }elseif($users['level']==1){//矿队长升级
            if($num>$level_two['num']){
                $money=$this->all_money($my_list);
                if($money>$level_two['yeji']){
                    $data['level']=6;
                    Db::name('users')->where('id',$users['id'])->update($data);
                }
            }
        }
        if($users['p_1']){//如果有上级,则递归查找
            $this->select_up($users['p_1'],$level_one,$level_two);
        }else{
            return true;//没有上级  返回true
        }
    }
    /*
     * 查询所有id下订单总额
     */
    public function all_money($my_list){
        $ids='';
        foreach ($my_list as $key=>$value){
            if(!$ids){
                $ids=$my_list[$key]['id'];
            }else{
                $ids=$ids.','.$my_list[$key]['id'];
            }
        }
        $all_money=Db::name('ranking')->where('user_id','in',$ids)->sum('money');
        return $all_money;
    }
    /*
     * 查找所有下级
     */
    public function select_all_down($team_list,$my_list){
        if($team_list){
            foreach ($team_list as $key=>$value){//循环找下级
                $list=Db::name('users')->field('id')->where('p_1',$value['id'])->select();
                if($list){
                    $my_list=array_merge($my_list,$list);//合并数组
                    $my_list=$this->recursion_team($list,$my_list);//递归
                }
            }
            return $my_list;
        }else{
            return $my_list;
        }
    }
    /*
     * 三倍出局随机选取某个值
     */
    public function triple_out($balance_give_integral,$double_percent,$triple_out,$luck_time,$goods_money=20){
        if($luck_time!=0){
            $where['add_time']=['gt',$luck_time];
        }
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
        $money=sprintf("%.2f",($goods_money*3)-($goods_money*3*$double_percent/100));//三倍、扣除手续费
        Db::startTrans();
        $res=Db::name('users')->where(['id'=>$ranking['user_id']])->setInc('lock_balance',$money);
        $data=[];
        $data['rank_status']=1;//出局
        $data['out_source']='T_'.$triple_out;//出局源
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
        //代理分佣
        if(!$this->agent_money($ranking['user_id'],$money)){
            Db::rollback();
            return false;
        }
        Db::commit();
        return false;
    }

    /*
     * 两倍出局
     */
    public function double_out($balance_give_integral,$double_percent,$double_out,$goods_money=20){
        $where['out_source']=0;//没有出局源
        $where['rank_status']=0;//没有出局
        $ranking=Db::name('ranking')->where($where)->limit(1)->order('id')->find();
        if(!$ranking){
            return true;//已经没有符合条件的数据了。返回已经完成
        }
        $user=Db::name('users')->where(['id'=>$ranking['user_id']])->find();
        if(!$user){
            return false;
        }
        $money=sprintf("%.2f",($goods_money*2)-($goods_money*2*$double_percent/100));//两倍、扣除手续费
        Db::startTrans();
        $res=Db::name('users')->where(['id'=>$ranking['user_id']])->setInc('lock_balance',$money);
        $data=[];
        $data['rank_status']=1;//出局
        $data['out_source']='D_'.$double_out;//出局源
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
        //代理分佣
        if(!$this->agent_money($ranking['user_id'],$money)){
            Db::rollback();
            return false;
        }
        Db::commit();
        return false;
    }
    /*
     * 抽奖
     */
    public function reward($user_id,$money,$double_percent,$rank_id,$rank_time,$bonus_time){
        $user_money=sprintf("%.2f",$money-($money*$double_percent/100));
        $today_time= strtotime(date("Y-m-d"),time());//今天的日期
        $reward=Db::name('reward')->where(['user_id'=>$user_id,'reward_day'=>$today_time])->find();
        if($reward){
            return true;//第二次抽奖时，如果已经抽过了，则跳过
        }
        Db::startTrans();
        $r=Db::name('jackpot')->where('id',1)->setDec('integral_num',$money/2);
        $res=Db::name('users')->where('id',$user_id)->setInc('balance',$user_money);
        if(!$res){
            Db::rollback();
            return false;
        }
        $data=[];
        $data['user_id']=$user_id;
        $data['ranking_id']=$rank_id;
        $data['rank_time']=$rank_time;//排位时间
        $data['reward_day']=$today_time;//中奖日期
        $data['reward_time']=$bonus_time;//中奖时间
        $data['reward_num']=$user_money;//中奖金额
        $data['add_time']=time();
        $ids=Db::name('reward')->insertGetId($data);
        if(!$ids){
            Db::rollback();
            return false;
        }
        $detail['user_id']=$user_id;
        $detail['type']=9;//中奖
        $detail['money']=$user_money;
        $detail['createtime']=time();
        $detail['intro']='中奖获得余额';
        $id=Db::name('moneydetail')->insertGetId($detail);
        if(!$id){
            Db::rollback();
            return false;
        }
        //代理分佣
        if(!$this->agent_money($user_id,$money)){
            Db::rollback();
            return false;
        }
        Db::commit();
        return true;
    }
    /*
     * 返佣+代理计算
     */
    public function agent_money($user_id,$money){
        $users=Db::name('users')->where('id',$user_id)->find();
        $p_1=Db::name('users')->where('id',$users['p_1'])->find();
        //返佣
        if($p_1){
            if(!$this->user_balance($p_1['id'],$money*2/100,'返佣')){
                return false;
            }
            $list=Db::name('user_level')->select();
            $level_list=[];
            foreach ($list as $key=>$value){
                $level_list[$value['id']]=$value['percent'];
            }
            $percent=$level_list[6];//矿场主返点百分百
            $percent_one=$level_list[1];//矿队长返点百分百
            //用上级作为循环查找对象
            if($p_1['level']==6){//上级是矿场主
                if(!$this->user_balance($p_1['id'],$money*($percent+$percent_one)/100,'代理返点')){
                    return false;
                }
                if($p_1['p_1']){
                    $data=$this->above($p_1['p_1'],$p_1['level']);
                    if($data){
                        if(!$this->user_balance($data['data']['id'],$money*1/100,'代理返点')){//平级奖
                            return false;
                        }
                    }
                }
            }elseif($p_1['level']==1){//上级是矿队长
                if(!$this->user_balance($p_1['id'],$money*$percent_one/100,'代理返点')){//上级
                    return false;
                }
                if($p_1['p_1']){
                    $data=$this->above($p_1['p_1'],$p_1['level']);
                    if($data){
                        if($data['type']==0){//平级
                            if(!$this->user_balance($data['data']['id'],$money*1/100,'代理返点')){//平级奖
                                return false;
                            }
                            $data_fl=$this->above($data['data']['p_1'],$data['data']['level']);
                            if($data_fl){
                                if ($data_fl['type']==1){
                                    if(!$this->user_balance($data_fl['data']['id'],$money*$percent/100,'代理返点')){//顶级
                                        return false;
                                    }
                                    $data_fl=$this->above($data_fl['data']['p_1'],$data_fl['data']['level']);
                                    if($data_fl){
                                        if(!$this->user_balance($data_fl['data']['id'],$money*1/100,'代理返点')){//平级奖
                                            return false;
                                        }
                                    }
                                }
                            }
                        }elseif($data['type']==1){
                            if(!$this->user_balance($data['data']['id'],$money*$percent/100,'代理返点')){//顶级
                                return false;
                            }
                            $data=$this->above($data['data']['p_1'],$data['data']['level']);
                            if($data){
                                if(!$this->user_balance($data['data']['id'],$money*1/100,'代理返点')){//平级奖
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
                            if(!$this->user_balance($data['data']['id'],$money*$percent_one/100,'代理返点')){//上级
                                return false;
                            }
                            $data_level=$this->above($data['data']['p_1'],$data['data']['level']);
                            if($data_level){
                                if($data_level['type']==0){
                                    if(!$this->user_balance($data_level['data']['id'],$money*1/100,'代理返点')){//平级奖
                                        return false;
                                    }
                                    $data_fl=$this->above($data_level['data']['p_1'],$data_level['data']['level']);
                                    if($data_fl){
                                        if ($data_fl['type']==1){
                                            if(!$this->user_balance($data_fl['data']['id'],$money*$percent/100,'代理返点')){//顶级
                                                return false;
                                            }
                                            $data_fl=$this->above($data_fl['data']['p_1'],$data_fl['data']['level']);
                                            if($data_fl){
                                                if(!$this->user_balance($data_fl['data']['id'],$money*1/100,'代理返点')){//平级奖
                                                    return false;
                                                }
                                            }
                                        }
                                    }
                                }elseif ($data_level['type']==1){
                                    if(!$this->user_balance($data['data']['id'],$money*$percent/100,'代理返点')){//顶级
                                        return false;
                                    }
                                    $data=$this->above($data['data']['p_1'],$data['data']['level']);
                                    if($data){
                                        if(!$this->user_balance($data['data']['id'],$money*1/100,'代理返点')){//平级奖
                                            return false;
                                        }
                                    }
                                }
                            }
                        }elseif($data['type']==1){
                            if(!$this->user_balance($data['data']['id'],$money*($percent+$percent_one)/100,'代理返点')){//顶级
                                return false;
                            }
                            $data=$this->above($data['data']['p_1'],$data['data']['level']);
                            if($data){
                                if(!$this->user_balance($data['data']['id'],$money*1/100,'代理返点')){//平级奖
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
                if(!$this->user_balance($p_2['id'],$money*2/100,'返佣')){
                    return false;
                }
            }
        }
        return true;

    }
    /*
     * 查找上级代理等级
     */
    public function above($user_id,$level){
        $user=Db::name('users')->field('id,p_1,level')->where('id',$user_id)->find();
        if($user['level']>$level){
            return ['data'=>$user,'type'=>1];//上级
        }
        if($level==$user['level']){
            return ['data'=>$user,'type'=>0];//平级
        }
        if($user['p_1']){//还有上级
            $this->above($user['p_1'],$level);//递归寻找上级或平级
        }else{
            return false;
        }
    }
    /*
     * 代理返佣
     */
    public function user_balance($user_id,$money,$intro){
        $re=Db::name('users')->where(['id'=>$user_id])->setDec('balance',$money);
        if(!$re){
            return false;
        }else{
            $detail=[];
            $detail['user_id']=$user_id;
            $detail['type']=1;//返利
            $detail['money']=$money;
            $detail['createtime']=time();
            $detail['intro']=$intro;
            $ids=Db::name('moneydetail')->insertGetId($detail);
            if(!$ids){
                return false;
            }
        }
        return true;
    }
}