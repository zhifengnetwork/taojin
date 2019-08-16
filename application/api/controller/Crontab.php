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
            $where['out_source']=$triple_out;
            $triple_num=Db::name('ranking')->where($where)->count();
            $luck_time = Db::name('config')->where(['name'=>'luck_time','inc_type'=>'taojin'])->value('value');
            $double_percent = Db::name('config')->where(['name'=>'double_percent','inc_type'=>'taojin'])->value('value');
            $balance_give_integral = Db::name('config')->where(['name'=>'balance_give_integral','inc_type'=>'taojin'])->value('value');
            $luck_time=strtotime(date($luck_time));
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
        $bonus_time = Db::name('config')->where(['name'=>'bonus_time','inc_type'=>'taojin'])->value('value');
        $reward_time = Db::name('config')->where(['name'=>'reward_time','inc_type'=>'taojin'])->value('value');
        $bonus_time=strtotime(date("Y-m-d")." ".$bonus_time);//今天开奖时间
        $today_time= strtotime(date("Y-m-d"),time());
        $where['reward_day']=$today_time;
        $reward=Db::name('reward')->where($where)->find();//已经抽过奖励
        if((time()>$bonus_time)&&!$reward){
            $double_percent = Db::name('config')->where(['name'=>'double_percent','inc_type'=>'taojin'])->value('value');
            if($reward_time==0){//随机抽取一分钟
                $where=[];
                $start=strtotime(date("Y-m-d",strtotime("-1 day"))." ".'00:00:00');
                $end=strtotime(date("Y-m-d")." ".'00:00:00');
                $where['rank_time']=['between',[$start,$end]];
                //随机抽取一条昨天的数据
                $ranking=Db::name('ranking')->where($where)->limit(1)->orderRaw('rand()')->find();
                $start_time=strtotime(date('Y-m-d H:i',$ranking['rank_time']));
                $end_time=$start_time+60;
                $where=[];
                $where['rank_time']=['between',[$start_time,$end_time]];
                $reward_ranking_list=Db::name('ranking')->where($where)->select();
                $num=count($reward_ranking_list);//多少排位中奖
                $jackpot=Db::name('jackpot')->where('id',1)->value('integral_num');//奖池金额
                $all_money=$jackpot*10/100;
                $everyone_money=sprintf("%.2f",$all_money/$num);
                $RankingLogic = new RankingLogic();
                foreach ($reward_ranking_list as $key=>$value){
                    $RankingLogic->reward($value["user"],$everyone_money,$double_percent);
                }
            }else{
                $reward_time=strtotime(date("Y-m-d",strtotime("-1 day"))." ".$reward_time);//今天中奖时间
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
                    $RankingLogic->reward($value["user"],$everyone_money,$double_percent);
                }
            }
        }else{
            return;
        }
    }
}
