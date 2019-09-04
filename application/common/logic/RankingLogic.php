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
        $balance = $user['recharge_balance']+$user['balance'];
        $money=$system['money'] * $num;
        if($balance < $money  ){
            return ['status' => -2, 'msg' => '余额不足！'];
        }
        //注册奖励开关
        $if_register_reward = Db::name('config')->where(['name'=>'if_register_reward','inc_type'=>'taojin'])->value('value');
        Db::startTrans();
        $system_money=Db::name('system_money')->where('id',1)->find();//系统总额
        $r=Db::name('jackpot')->where('id',1)->setInc('integral_num',$money/2);
        if($user['recharge_balance']>$money){//充值余额足够扣款
            $re=Db::name('users')->where(['id'=>$user_id])->setDec('recharge_balance',$money);
        }else{
            $over_money=$money-$user['recharge_balance'];
            $user_data['balance']=$user['balance']-$over_money;
            $user_data['recharge_balance']=0;
            $re=Db::name('users')->where(['id'=>$user_id])->update($user_data);
        }

        $system_money['balance']=$system_money['balance']+$money;
        $system_data['balance']=$money;
        $system_data['new_balance']=$system_money['balance'];
        $system_data['add_time']=time();
        $system_data['desc']='购买修改系统金额';
        $sys_id=Db::name('system_money_log')->insertGetId($system_data);
        if(!$sys_id){
            Db::rollback();
            return ['status' => -2, 'msg' => '购买失败,生成系统log出错！'];
        }
        $rsss=Db::name('system_money')->update($system_money);//修改
        if(!$re||!$r||!$rsss){
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
            if($balance_unlock!=0&&$balance_unlock>=40){
                $system_money=Db::name('system_money')->where('id',1)->find();//系统总额
                $ress=Db::name('users')->where(['id'=>$user_id])->setInc('balance',$balance_unlock);
                $rs=Db::name('users')->where(['id'=>$user_id])->setDec('lock_balance',$balance_unlock);
                if(!$rs){
                    Db::rollback();
                    return ['status' => -2, 'msg' => '冻结余额解冻失败!'];
                }
                $system_money['balance']=$system_money['balance']-$balance_unlock;
                if($system_money['balance']<0){
                    Db::rollback();
                    return ['status' => -2, 'msg' => '系统金沙不足，请联系管理员！'];
                }
                $system_data['balance']=-$balance_unlock;
                $system_data['new_balance']=$system_money['balance'];
                $system_data['add_time']=time();
                $system_data['desc']='解冻修改系统金额';
                $sys_id=Db::name('system_money_log')->insertGetId($system_data);
                if(!$sys_id){
                    Db::rollback();
                    return ['status' => -2, 'msg' => '购买失败,生成系统log出错！'];
                }
                $res=Db::name('system_money')->update($system_money);//修改
                if(!$ress||!$res){
                    Db::rollback();
                    return ['status' => -2, 'msg' => '修改系统余额失败，请联系管理员！'];
                }
                $detail=[];
                $detail['user_id']=$user_id;
                $detail['type']=3;//解冻
                $detail['money']=$balance_unlock;
                $detail['createtime']=time();
                $detail['intro']='解冻余额';
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
        $user_name=M('users')->where(['id'=>$user_id])->value('nick_name');
        if($today_start!=0&&$num>100){
            $data_c['user_id']=$user_id;
            $data_c['user_name']=$user_name;
            $data_c['add_time']=time();
            $data_c['start_time']=$today_start;
            $data_c['num']=$num;
            $data_c['money']=$system['money'];
            $res_c=Db::name('crontab')->insertGetId($data_c);
            if(!$res_c){
                Db::rollback();
                return ['status' => -2, 'msg' => '订单生成错误！'];
            }
        }else{
            if($num>100){
                $data_u['user_id']=$user_id;
                $data_u['user_name']=$user_name;
                $data_u['add_time']=time();
                $data_u['start_time']=time();
                $data_u['type']=1;//不是包时间段
                $data_u['num']=$num;
                $data_u['money']=$system['money'];
                $res_u=Db::name('crontab')->insertGetId($data_u);
                if(!$res_u){
                    Db::rollback();
                    return ['status' => -2, 'msg' => '订单生成错误！'];
                }
            }else{
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
            }

        }
        Db::commit();
//        if($user['p_1']){
//            $level_one=Db::name('user_level')->where('level_id',1)->find();//矿队长
//            $level_two=Db::name('user_level')->where('level_id',6)->find();//矿场主
//            $is_dl=$this->select_up($user['p_1'],$level_one,$level_two);
//        }
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
        $agent_money=$goods_money*3;
        $money=sprintf("%.2f",($agent_money)-($agent_money*$double_percent/100));//三倍、扣除手续费
        Db::startTrans();
        $res=Db::name('users')->where(['id'=>$ranking['user_id']])->setInc('lock_balance',$money);
        $data=[];
        $data['rank_status']=1;//出局
        $data['out_source']='T_'.$triple_out;//出局源
        $data['out_type']=3;//三倍出局
        $data['out_time']=time();//出局时间
        $r=Db::name('ranking')->where('id',$ranking['id'])->update($data);
        if(!$res||!$r){
            Db::rollback();
            return false;
        }
        //赠送糖果
        $tg_num=sprintf("%.2f",$goods_money*2/$balance_give_integral);//保留两位小数
//        $tg_num=floor($goods_money*3/$balance_give_integral);//赠送糖果取整
//        $data=[];
//        $data['num']=$tg_num;
//        $data['user_id']=$ranking['user_id'];
//        $data['end_time']=time()+24*3600;//24小时过期
//        $data['add_time']=time();
//        $ids=Db::name('give')->insertGetId($data);
        $give=$this->add_give($tg_num,$ranking['user_id'],7,'三倍出局');//糖果生成
        $detail['user_id']=$user['id'];
        $detail['typefrom']=1;
        $detail['type']=10;//出局赠送
        $detail['money']=$money;
        $detail['createtime']=time();
        $detail['intro']='三倍出局获得冻结余额';
        $id=Db::name('moneydetail')->insertGetId($detail);
        if(!$id||!$give){
            Db::rollback();
            return false;
        }
        //代理分佣
        if(!$this->agent_money($ranking['user_id'],$agent_money,$balance_give_integral)){
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
        $agent_money=$goods_money*2;
        $money=sprintf("%.2f",$agent_money-($agent_money*$double_percent/100));//两倍、扣除手续费
        Db::startTrans();
        $res=Db::name('users')->where(['id'=>$ranking['user_id']])->setInc('lock_balance',$money);
        $data=[];
        $data['rank_status']=1;//出局
        $data['out_source']='D_'.$double_out;//出局源
        $data['out_type']=2;//两倍出局
        $data['out_time']=time();//出局时间
        $r=Db::name('ranking')->where('id',$ranking['id'])->update($data);
        if(!$res||!$r){
            Db::rollback();
            return false;
        }
        //赠送糖果
//        $tg_num=floor($goods_money*2/$balance_give_integral);//赠送糖果取整
//        $data=[];
//        $data['num']=$tg_num;
//        $data['user_id']=$ranking['user_id'];
//        $data['end_time']=time()+24*3600;//24小时过期
//        $data['add_time']=time();
//        $ids=Db::name('give')->insertGetId($data);
        $tg_num=sprintf("%.2f",$goods_money*2/$balance_give_integral);//保留两位小数
        $give=$this->add_give($tg_num,$ranking['user_id'],0,'两倍出局');//糖果生成
        $detail['user_id']=$user['id'];
        $detail['typefrom']=1;
        $detail['type']=10;//出局赠送
        $detail['money']=$money;
        $detail['createtime']=time();
        $detail['intro']='双倍出局获得冻结余额';
        $id=Db::name('moneydetail')->insertGetId($detail);
        if(!$id||!$give){
            Db::rollback();
            return false;
        }
        //代理分佣
        if(!$this->agent_money($ranking['user_id'],$agent_money,$balance_give_integral)){//代理费计算
            Db::rollback();
            return false;
        }
        Db::commit();
        return false;
    }
    /*
     * 生成糖果
     */
    public function add_give($num,$user_id,$type,$desc){
        $data=[];
        $data['num']=$num;
        $data['user_id']=$user_id;
        $data['end_time']=time()+24*3600;//24小时过期
        $data['add_time']=time();
        $ids=Db::name('give')->insertGetId($data);
        if($ids){
            return true;
        }else{
            return false;
        }

    }
    /*
     * 抽奖
     */
    public function reward($ranking_id,$user_id,$money,$double_percent,$rank_id,$rank_time,$bonus_time,$balance_give_integral){
        $user_money=sprintf("%.2f",$money-($money*$double_percent/100));
        $today_time= strtotime(date("Y-m-d"),time());//今天的日期
        $reward=Db::name('reward')->where(['ranking_id'=>$ranking_id,'user_id'=>$user_id,'reward_day'=>$today_time])->find();//根据ranking_id确定是否抽奖
        if($reward){
            return true;//第二次抽奖时，如果已经抽过了，则跳过
        }
        Db::startTrans();
        $system_money=Db::name('system_money')->where('id',1)->find();//系统总额
        $integral_num=Db::name('jackpot')->where('id',1)->value('integral_num');
        $integral_num=$integral_num-$money;
        $r=Db::name('jackpot')->where('id',1)->update(['integral_num'=>$integral_num]);//扣取奖金池的钱
//        $r=Db::name('jackpot')->where('id',1)->setDec('integral_num',$money);//扣取奖金池的钱
//        $res=Db::name('users')->where('id',$user_id)->setInc('balance',$user_money);
        $user_balance=Db::name('users')->where('id',$user_id)->value('balance');
        $user_balance=$user_balance+$user_money;
        $res=Db::name('users')->where('id',$user_id)->update(['balance'=>$user_balance]);
        $system_money['balance']=sprintf("%.2f",$system_money['balance']-$user_money);
        if($system_money['balance']<0){
            Db::rollback();
            return false;
        }
        $system_data['balance']=-$user_balance;
        $system_data['add_time']=time();
        $system_data['desc']='抽奖修改系统金额';
        $sys_id=Db::name('system_money_log')->insertGetId($system_data);
        if(!$sys_id){
            Db::rollback();
            return false;
        }
        $re=Db::name('system_money')->update($system_money);//修改
        if(!$res||!$r||!$re){
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
        $tg_num=sprintf("%.2f",$money/$balance_give_integral);//保留两位小数
        $give=$this->add_give($tg_num,$user_id,1,'抽奖赠送');//糖果生成
        $detail['user_id']=$user_id;
        $detail['type']=9;//中奖
        $detail['money']=$user_money;
        $detail['createtime']=time();
        $detail['intro']='中奖获得余额';
        $id=Db::name('moneydetail')->insertGetId($detail);//中奖
        if(!$id||!$give){
            Db::rollback();
            return false;
        }
        //代理分佣
        if(!$this->agent_money($user_id,$money,$balance_give_integral)){
            Db::rollback();
            return false;
        }
        Db::commit();
        return true;
    }
    /*
     * 返佣+代理计算
     */
    public function agent_money($user_id,$money,$balance_give_integral){
        $users=Db::name('users')->where('id',$user_id)->find();
        $p_1=Db::name('users')->where('id',$users['p_1'])->find();
        $user_level=$users['level'];//当前用户级别
        //返佣
        $surplus_money=10;//%10剩余点
        if($p_1){
            if(!$this->user_balance($p_1['id'],$money*2/100,'直推返利',1)){
                return false;
            }
            $surplus_money=$surplus_money-2;//减少见点
            $list=Db::name('user_level')->select();
            $level_list=[];
            foreach ($list as $key=>$value){
                $level_list[$value['level_id']]=$value['percent'];
            }
            $percent=$level_list[6];//矿场主返点百分百
            $percent_one=$level_list[1];//矿队长返点百分百
            //用上级作为循环查找对象
            if($p_1['level']==6){//上级是矿场主
                if(!$this->user_balance($p_1['id'],$money*($percent+$percent_one)/100,'代理返点',4,$balance_give_integral)){//矿场主
                    return false;
                }
                $surplus_money=$surplus_money-($percent+$percent_one);
                if($p_1['p_1']){
                    $data=$this->above($p_1['p_1'],$p_1['level']);
                    if($data){
                        if(!$this->user_balance($data['data']['id'],$money*1/100,'平级代理返点',3,$balance_give_integral)){//平级奖
                            return false;
                        }
                        $surplus_money=$surplus_money-1;
                    }
                }
            }elseif($p_1['level']==1){//上级是矿队长
                if(!$this->user_balance($p_1['id'],$money*$percent_one/100,'代理返点',2,$balance_give_integral)){//上级  矿队长
                    return false;
                }
                $surplus_money=$surplus_money-$percent_one;
                if($p_1['p_1']){
                    $data=$this->above($p_1['p_1'],$p_1['level']);
                    if($data){
                        if($data['type']==0){//平级
                            if(!$this->user_balance($data['data']['id'],$money*1/100,'平级代理返点',3,$balance_give_integral)){//平级奖
                                return false;
                            }
                            $surplus_money=$surplus_money-1;
                            $data_fl=$this->above($data['data']['p_1'],$data['data']['level']);
                            if($data_fl){
                                if ($data_fl['type']==1){
                                    if(!$this->user_balance($data_fl['data']['id'],$money*$percent/100,'代理返点',4,$balance_give_integral)){//顶级   矿场主
                                        return false;
                                    }
                                    $surplus_money=$surplus_money-$percent;
                                    $data_fl=$this->above($data_fl['data']['p_1'],$data_fl['data']['level']);
                                    if($data_fl){
                                        if(!$this->user_balance($data_fl['data']['id'],$money*1/100,'平级代理返点',3,$balance_give_integral)){//平级奖
                                            return false;
                                        }
                                        $surplus_money=$surplus_money-1;
                                    }
                                }
                            }
                        }elseif($data['type']==1){
                            if(!$this->user_balance($data['data']['id'],$money*$percent/100,'代理返点',4,$balance_give_integral)){//顶级  矿场主
                                return false;
                            }
                            $surplus_money=$surplus_money-$percent;
                            $data=$this->above($data['data']['p_1'],$data['data']['level']);
                            if($data){
                                if(!$this->user_balance($data['data']['id'],$money*1/100,'平级代理返点',3,$balance_give_integral)){//平级奖
                                    return false;
                                }
                                $surplus_money=$surplus_money-1;
                            }
                        }

                    }
                }
            }else{//上级没有代理返点
                if($p_1['p_1']){
                    $data=$this->above($p_1['p_1'],1);
                    if($data){
                        if($data['type']==0){
                            if(!$this->user_balance($data['data']['id'],$money*$percent_one/100,'代理返点',2,$balance_give_integral)){//上级  矿队长
                                return false;
                            }
                            $surplus_money=$surplus_money-$percent_one;
                            $data_level=$this->above($data['data']['p_1'],$data['data']['level']);
                            if($data_level){
                                if($data_level['type']==0){
                                    if(!$this->user_balance($data_level['data']['id'],$money*1/100,'平级代理返点',3,$balance_give_integral)){//平级奖
                                        return false;
                                    }
                                    $surplus_money=$surplus_money-1;
                                    $data_fl=$this->above($data_level['data']['p_1'],$data_level['data']['level']);
                                    if($data_fl){
                                        if ($data_fl['type']==1){
                                            if(!$this->user_balance($data_fl['data']['id'],$money*$percent/100,'代理返点',4,$balance_give_integral)){//顶级  矿场主
                                                return false;
                                            }
                                            $surplus_money=$surplus_money-$percent;
                                            $data_fl=$this->above($data_fl['data']['p_1'],$data_fl['data']['level']);
                                            if($data_fl){
                                                if(!$this->user_balance($data_fl['data']['id'],$money*1/100,'平级代理返点',3,$balance_give_integral)){//平级奖
                                                    return false;
                                                }
                                                $surplus_money=$surplus_money-1;
                                            }
                                        }
                                    }
                                }elseif ($data_level['type']==1){
                                    if(!$this->user_balance($data['data']['id'],$money*$percent/100,'代理返点',4,$balance_give_integral)){//顶级  矿场主
                                        return false;
                                    }
                                    $surplus_money=$surplus_money-$percent;
                                    $data=$this->above($data['data']['p_1'],$data['data']['level']);
                                    if($data){
                                        if(!$this->user_balance($data['data']['id'],$money*1/100,'平级代理返点',3,$balance_give_integral)){//平级奖
                                            return false;
                                        }
                                        $surplus_money=$surplus_money-1;
                                    }
                                }
                            }
                        }elseif($data['type']==1){
                            if(!$this->user_balance($data['data']['id'],$money*($percent+$percent_one)/100,'代理返点',4,$balance_give_integral)){//顶级  矿场主
                                return false;
                            }
                            $surplus_money=$surplus_money-($percent_one+$percent);
                            $data=$this->above($data['data']['p_1'],$data['data']['level']);
                            if($data){
                                if(!$this->user_balance($data['data']['id'],$money*1/100,'平级代理返点',3,$balance_give_integral)){//平级奖
                                    return false;
                                }
                                $surplus_money=$surplus_money-1;
                            }
                        }

                    }
                }
            }
            //上上级计算返佣
            $p_2=Db::name('users')->where('id',$users['p_2'])->find();
            if($p_2){
                if(!$this->user_balance($p_2['id'],$money*2/100,'间推返利',5,$balance_give_integral)){
                    return false;
                }
                $surplus_money=$surplus_money-2;
            }
        }
        if($surplus_money>0){//未拿完扣取费用，则钱归系统账号
            $user_admin=Db::name('users')->where('phone',18866666666)->find();
            if($user_admin){
                $surplus=$money*$surplus_money/100;
                $detail=[];
                $detail['user_id']=$user_admin['id'];
                $detail['type']=16;//矿场主
                $detail['money']=$surplus;
                $detail['createtime']=time();
                $detail['intro']='返佣未拿完';
                $ids=Db::name('moneydetail')->insertGetId($detail);
                if(!$ids){
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
            $this->above($user['p_1'],$level);//递归寻找上级或平级
        }else{
            return false;
        }
    }
    /*
     * 代理返佣
     */
    public function user_balance($user_id,$money,$intro,$type,$balance_give_integral){
        $balance=Db::name('users')->where(['id'=>$user_id])->value('balance');
        $balance=$balance+$money;
        $system_money=Db::name('system_money')->where('id',1)->find();//系统总额
        $old_balance=$system_money['balance'];
//        $system_money['balance']=$system_money['balance']-$money;
        $system_money['balance']=sprintf("%.2f",$system_money['balance']-$money);
        if($system_money['balance']<0){
            return false;//系统金额少于0  则返佣失败
        }
        $system_data['balance']=-$money;
        $system_data['old_balance']=$old_balance;
        $system_data['new_balance']=$system_money['balance'];
        $system_data['add_time']=time();
        $system_data['desc']='返佣修改系统金额';
        $sys_id=Db::name('system_money_log')->insertGetId($system_data);
        if(!$sys_id){
            return false;
        }
        $tg_num=sprintf("%.2f",$money/$balance_give_integral);//保留两位小数
        if($type==1){
            $m_type=1;//直推返利
            $give_type=2;
            $desc="直推产生糖果";
        }elseif($type==2){
            $m_type=11;//矿场主
            $give_type=5;
            $desc="矿场主产生糖果";
        }
        elseif($type==3){
            $m_type=12;//平级奖励
            $give_type=6;
            $desc="平级产生糖果";
        }elseif ($type==4){
            $m_type=14;//矿队长
            $give_type=4;
            $desc="矿队长产生糖果";
        }elseif ($type==5){
            $m_type=15;//间推
            $give_type=3;
            $desc="间推产生糖果";
        }else{
            $m_type=1;
            $give_type=1;
            $desc="未知类型";
        }
        $give=$this->add_give($tg_num,$user_id,$give_type,$desc);//糖果生成
        $re=Db::name('users')->where(['id'=>$user_id])->update(['balance'=>$balance]);
        $res=Db::name('system_money')->update($system_money);
        if(!$re||!$res||!$give){
            return false;
        }else{
            $detail=[];
            $detail['user_id']=$user_id;
            $detail['type']=$m_type;
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