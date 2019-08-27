<?php
namespace app\api\controller;
use think\Db;
use app\common\logic\RankingLogic;
class Crontab extends ApiBase
{
    public function ranking_crontab(){
        $start_time=time();
        for ($i=0;$i<60;$i++){
            if((time()-$start_time)>58){//运行时间大于59秒，退出
                die;
            }
            $where=[];
            $where['rank_status']=0;
            $num=Db::name('ranking')->where($where)->count();
            $double_out = Db::name('config')->where(['name'=>'double_out','inc_type'=>'taojin'])->value('value');
            $triple_out = Db::name('config')->where(['name'=>'triple_out','inc_type'=>'taojin'])->value('value');
            $RankingLogic = new RankingLogic();
            $is_s=true;//睡
            if($num>=$triple_out){
                //三倍出局
                $where=[];
                $where['out_source']='T_'.$triple_out;//三倍出局标志源
                $triple_num=Db::name('ranking')->where($where)->count();
                $luck_time = Db::name('config')->where(['name'=>'luck_time','inc_type'=>'taojin'])->value('value');
                $double_percent = Db::name('config')->where(['name'=>'double_percent','inc_type'=>'taojin'])->value('value');
                $balance_give_integral = Db::name('config')->where(['name'=>'balance_give_integral','inc_type'=>'taojin'])->value('value');
                $luck_time=strtotime($luck_time);
                if($luck_time>time()){
                    $luck_time=0;
                }
                if($triple_num<100){
                    $for_count=100-$triple_num;
                    for($i=0;$i<$for_count;$i++){
                        $res=$RankingLogic->triple_out($balance_give_integral,$double_percent,$triple_out,$luck_time);
                        if($res){
                            break;//如果是true则跳出循环
                        }
                    }
                }else{
                    Db::name('config')->where(['name'=>'triple_out','inc_type'=>'taojin'])->setInc('value',1000);//抽奖完成，则抽奖准备数量增加
                }
                $is_s=false;
            }
            if($num>=$double_out){
                //两倍出局
                $where=[];
                $where['out_source']='D_'.$double_out;//两倍出局标志源
                $double_num=Db::name('ranking')->where($where)->count();
                $double_percent = Db::name('config')->where(['name'=>'double_percent','inc_type'=>'taojin'])->value('value');
                $balance_give_integral = Db::name('config')->where(['name'=>'balance_give_integral','inc_type'=>'taojin'])->value('value');
                if(!$double_num){
                    $RankingLogic->double_out($balance_give_integral,$double_percent,$double_out);
                }else{
                    Db::name('config')->where(['name'=>'double_out','inc_type'=>'taojin'])->setInc('value',10);//两倍出局数量增加
                }
                $is_s=false;
            }
            if($is_s){
                sleep(1);
            }
        }
        return '运行结束';
    }
    //测试
    public function text(){
        sleep(3);
        $this->ajaxReturn(['status' => -2 , 'msg'=>'测试睡眠']);
    }
    /*
     * 抽奖定时任务
     */
    public function reward_crontab(){
        $bonus_time = Db::name('config')->where(['name'=>'bonus_time','inc_type'=>'taojin'])->value('value');//开奖时间
        $reward_time = Db::name('config')->where(['name'=>'reward_time','inc_type'=>'taojin'])->value('value');//中奖时间
        $yesterday_time=strtotime(date("Y-m-d",strtotime("-1 day"))." ".$bonus_time);//昨天的开奖时间
        $bonus_time=strtotime(date("Y-m-d")." ".$bonus_time);//今天开奖时间
        $is_reward_time=true;//是否随机中奖
        if($reward_time){
            $reward_time=strtotime($reward_time);//设置的中奖时间转换时间戳
            if($reward_time>$yesterday_time&&$reward_time<$bonus_time){//设置的中奖时间，要在昨天开奖之后和今天开奖之前的时间段
                $is_reward_time=false;
            }else{
                $is_reward_time=true;
            }
        }else{
            $is_reward_time=true;
        }
        $today_time= strtotime(date("Y-m-d"),time());
        $where['reward_day']=$today_time;
        $reward=Db::name('reward_log')->where($where)->find();//已经抽过奖励
        if(!$reward){
            $data=[];
            $data['reward_day']=$today_time;
            $data['status']=0;//抽奖是否完成
            $reward_log_id=Db::name('reward_log')->insertGetId($data);
            $is_reward=true;
        }else{
            $reward_log_id=$reward['id'];
            if($reward['status']){
                $is_reward=false;//抽奖完毕
            }else{
                $is_reward=true;
            }
        }
        if(((time()>$bonus_time)&&time()<$bonus_time+3600)&&$is_reward){//在开奖时间一个小时内
            $double_percent = Db::name('config')->where(['name'=>'double_percent','inc_type'=>'taojin'])->value('value');
            if($is_reward_time){//随机抽取一分钟
                $where=[];
                $start=strtotime(date("Y-m-d",strtotime("-1 day"))." ".'14:00:00');
                $end=strtotime(date("Y-m-d")." ".'14:00:00');
                $where['rank_time']=['between',[$start,$end]];
                if($reward['reward_time']){//抽奖中断
                    $start_time=$reward['reward_time'];
                    $end_time=$start_time+60;
                }else{
                    //随机抽取一条昨天的数据
                    $ranking=Db::name('ranking')->where($where)->limit(1)->orderRaw('rand()')->find();
                    $start_time=strtotime(date('Y-m-d H:i',$ranking['rank_time']));
                    $end_time=$start_time+60;
                }
                $where=[];
                $where['rank_time']=['between',[$start_time,$end_time]];
                $reward_ranking_list=Db::name('ranking')->where($where)->select();
                if(!$reward_ranking_list){//数据为空，则退出
                    return '数据空，退出';
                }
                $num=count($reward_ranking_list);//多少排位中奖
                $jackpot=Db::name('jackpot')->where('id',1)->value('integral_num');//奖池金额
                $all_money=$jackpot*10/100;
                $everyone_money=sprintf("%.2f",$all_money/$num);
                $RankingLogic = new RankingLogic();
                foreach ($reward_ranking_list as $key=>$value){
                    $is_end=$RankingLogic->reward($value["user_id"],$everyone_money,$double_percent,$value['id'],$value['rank_time'],$bonus_time);
                    if(!$is_end){
                        Db::name('reward_log')->where('id',$reward_log_id)->update(['reward_time'=>$start_time]);//出错，记录随机抽取的中奖时间段
                        return 'id为：'.$value["user_id"].'出错';//如果某一条出错，则退出任务
                    }
                }
            }else{
                //今天中奖时间
//                $reward_time=strtotime(date("Y-m-d",strtotime("-1 day"))." ".$reward_time);
                $start_time=strtotime(date('Y-m-d H:i',$reward_time));//中奖开始时间戳
                $end_time=$start_time+60;//中奖结束时间戳
                $where=[];
                $where['rank_time']=['between',[$start_time,$end_time]];
                $reward_ranking_list=Db::name('ranking')->where($where)->select();
                if(!$reward_ranking_list){//数据为空，则退出
                    return '数据空，退出';
                }
                $num=count($reward_ranking_list);//多少排位中奖
                $jackpot=Db::name('jackpot')->where('id',1)->value('integral_num');//奖池金额
                $all_money=$jackpot*10/100;
                $everyone_money=sprintf("%.2f",$all_money/$num);//每人得到多少
                $RankingLogic = new RankingLogic();
                foreach ($reward_ranking_list as $key=>$value){
                    //判断是否抽过奖
                    $is_end=$RankingLogic->reward($value["user_id"],$everyone_money,$double_percent,$value['id'],$value['rank_time'],$bonus_time);
                    if(!$is_end){
                        Db::name('reward_log')->where('id',$reward_log_id)->update(['reward_time'=>$start_time]);//出错，记录中奖时间段
                        return 'id为：'.$value["user_id"].'出错';//如果某一条出错，则退出任务
                    }
                }
            }
            Db::name('reward_log')->where('id',$reward_log_id)->update(['status'=>1]);//如果所以人抽奖完成，则完成
            return '抽奖完成';
        }else{
            return '不在抽奖时间内';
        }
    }
    public function time_slot_crontab(){
        $start_time=time();
        for ($i=0;$i<60;$i++){
            if((time()-$start_time)>58){//运行时间大于59秒，退出
                return;
            }
            $where['status']=0;
            $where['type']=0;
            $cro_list=Db::name('crontab')->where($where)->select();//包时间段
            if($cro_list){
                foreach ($cro_list as $key=>$value){
                    $num=$value['num'];
                    $user_id=$value['user_id'];
                    $user_name=$value['user_name'];
                    $today_start=$value['start_time'];
                    $money=$value['money'];
                    Db::startTrans();
                    for ($i=0;$i<$num;$i++){
                        $data['user_id'] = $user_id;
                        $data['user_name'] = $user_name;
                        if($today_start!=0){
                            $data['rank_time'] = $today_start;
                            $today_start=$today_start+60;//+1分钟
                        }else{
                            $data['rank_time'] = time();
                        }
                        $data['money']=$money;
                        $data['add_time'] = time();
                        $res = Db::name('ranking')->insertGetId($data);
                        if(!$res){
                            Db::rollback();
                            return 'ranking生成失败';
                        }
                    }
                    $res=Db::name('crontab')->where('id',$value['id'])->update(['status'=>1,'updata_time'=>time()]);//更新
                    if(!$res){
                        Db::rollback();
                        return 'crontab更新失败';
                    }
                    Db::commit();
                }
                return '生成订单完毕';
            }else{
                sleep(1);
            }
        }
        return '执行完毕';
    }
}
