<?php
namespace app\api\controller;
use think\Db;
use app\common\logic\RankingLogic;
class Crontab extends ApiBase
{
    public function ranking_crontab(){
        $where=[];
        $where['rank_status']=0;
        $num=Db::name('ranking')->where($where)->count();
        $double_out = Db::name('config')->where(['name'=>'double_out','inc_type'=>'taojin'])->value('value');
        $triple_out = Db::name('config')->where(['name'=>'triple_out','inc_type'=>'taojin'])->value('value');
        $RankingLogic = new RankingLogic();
        if($num>=$triple_out){
            //三倍出局
            $where=[];
            $where['out_source']=$triple_out;

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
        }
        if($num>=$double_out){
            //两倍出局
            $where=[];
            $where['out_source']=$double_out;
            $double_num=Db::name('ranking')->where($where)->count();
            $double_percent = Db::name('config')->where(['name'=>'double_percent','inc_type'=>'taojin'])->value('value');
            $balance_give_integral = Db::name('config')->where(['name'=>'balance_give_integral','inc_type'=>'taojin'])->value('value');
            if(!$double_num){
                $RankingLogic->double_out($balance_give_integral,$double_percent,$double_out);
            }else{
                Db::name('config')->where(['name'=>'double_out','inc_type'=>'taojin'])->setInc('value',10);//两倍出局数量增加
            }
        }
    }
    //测试
    public function text($goods_money=20){
//        $luck_time = Db::name('config')->where(['name'=>'luck_time','inc_type'=>'taojin'])->value('value');
//        $time=strtotime(date($luck_time));
//        $ranking=Db::name('ranking')->limit(1)->orderRaw('rand()')->select();
        $where['out_source']=0;//没有抽奖
        $where['rank_status']=0;//没有出局
        $ranking=Db::name('ranking')->where($where)->limit(1)->order('id')->find();
//        Db::name('ranking')->getLastSql();
//        return $ranking;
        print_r($ranking);
        die;
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
        print_r($reward);
        if(!$reward){
            $data=[];
            $data['reward_day']=$today_time;
            $data['status']=0;//抽奖是否完成
            $reward_log_id=Db::name('reward_log')->insertGetId($data);
            $is_reward=true;
        }else{
            $reward_log_id=$reward['id'];
            if($reward['status']){
                $is_reward=false;
            }else{
                $is_reward=true;
            }
        }
        if((time()>$bonus_time)&&$is_reward){
            $double_percent = Db::name('config')->where(['name'=>'double_percent','inc_type'=>'taojin'])->value('value');
            if($is_reward_time){//随机抽取一分钟
                $where=[];
                $start=strtotime(date("Y-m-d",strtotime("-1 day"))." ".'00:00:00');
                $end=strtotime(date("Y-m-d")." ".'00:00:00');
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
                $num=count($reward_ranking_list);//多少排位中奖
                $jackpot=Db::name('jackpot')->where('id',1)->value('integral_num');//奖池金额
                $all_money=$jackpot*10/100;
                $everyone_money=sprintf("%.2f",$all_money/$num);
                $RankingLogic = new RankingLogic();
                foreach ($reward_ranking_list as $key=>$value){
                    $is_end=$RankingLogic->reward($value["user_id"],$everyone_money,$double_percent,$value['id'],$value['rank_time'],$bonus_time);
                    if(!$is_end){
                        echo 52;
                        Db::name('reward_log')->where('id',$reward_log_id)->update(['reward_time'=>$start_time]);//出错，记录随机抽取的中奖时间段
                        return;//如果某一条出错，则退出任务
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
                $num=count($reward_ranking_list);//多少排位中奖
                $jackpot=Db::name('jackpot')->where('id',1)->value('integral_num');//奖池金额
                $all_money=$jackpot*10/100;
                $everyone_money=sprintf("%.2f",$all_money/$num);
                $RankingLogic = new RankingLogic();
                foreach ($reward_ranking_list as $key=>$value){
                    //判断是否抽过奖
                    $is_end=$RankingLogic->reward($value["user_id"],$everyone_money,$double_percent,$value['id'],$value['rank_time'],$bonus_time);
                    if(!$is_end){
                        Db::name('reward_log')->where('id',$reward_log_id)->update(['reward_time'=>$start_time]);//出错，记录中奖时间段
                        return;//如果某一条出错，则退出任务
                    }
                }
            }
            Db::name('reward_log')->where('id',$reward_log_id)->update(['status'=>1]);//如果所以人抽奖完成，则完成
        }else{
            return;
        }
    }
}
