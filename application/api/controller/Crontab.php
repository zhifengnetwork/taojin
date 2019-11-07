<?php
namespace app\api\controller;
use think\Db;
use app\common\logic\RankingLogic;
use app\common\logic\ChickenLogic;
class Crontab extends ApiBase
{
    public function ranking_crontab(){
        set_time_limit(0);
        $start_time=time();
        while ((time()-$start_time)<58){
            
//        }
//        for ($i=0;$i<60;$i++){
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
                $triple_num=Db::name('ranking')->where($where)->count();//这次抽奖出局的总人数
                $luck_time = Db::name('config')->where(['name'=>'luck_time','inc_type'=>'taojin'])->value('value');
                $double_percent = Db::name('config')->where(['name'=>'double_percent','inc_type'=>'taojin'])->value('value');
                $balance_give_integral = Db::name('config')->where(['name'=>'balance_give_integral','inc_type'=>'taojin'])->value('value');
            	if(!$luck_time){
            		 $luck_time=time();
            	}else{
            		$luck_time=strtotime($luck_time);
            	}
                
                if($triple_num<100){
            	    /*-----------------------------------后面添加需求修改---------------------------------------*/
                    /*-----------------------------------start---------------------------------------*/
            	    $set_reward=Db::name('set_reward')->select();//设置固定抽奖
            	    if($set_reward){//有设置时
                        foreach ($set_reward as $k=>$v){
                            $where_a=[];
                            $where_a['out_source']='T_'.$triple_out;//三倍出局标志源
                            $where_a['user_id']=$v['user_id'];
                            $user_reward=Db::name('ranking')->where($where_a)->count();//查找当前用户已经抽奖出局多少个
                            if($user_reward<$v['num']){//如果小于要抽奖的出局数
                                if($v['num']>100){
                                    $user_reward_count=100;//一次抽奖只能抽100
                                }else{
                                    $user_reward_count=$v['num']-$user_reward;//当前用户抽奖出局人数
                                }
                                for($j=0;$j<$user_reward_count;$j++){
                                    $res=$RankingLogic->user_triple_out($balance_give_integral,$double_percent,$triple_out,$v['user_id']);//执行抽奖方法
                                    if($res){
                                        break;//如果是true则跳出循环
                                    }else{
                                        $triple_num=$triple_num+1;//出局人数累计
                                        if($triple_num>99)
                                        {
                                            break;//如果是100人，则抽奖结束
                                        }
                                    }
                                }
                            }
                        }
                    }
                    /*-----------------------------------end---------------------------------------*/
                    $for_count=100-$triple_num;//抽奖未完成，则按照随机抽取
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
        $where['u.p_1']=45;
        $chicken=Db::name('chicken')->alias('c')
            ->join('users u','u.id=c.user_id','LEFT')
            ->field('c.user_id,count(*) count')->where($where)->group('user_id')->select();
        var_dump($chicken);
       //$RankingLogic = new RankingLogic();
       //$res=$RankingLogic->user_balance(84,6026.78*4/100,'代理返点',4,10);
       //$re=$RankingLogic->user_balance(72,6026.78*1/100,'平级代理返点',3,10);
       // if(!$res||!$re){
       //     return '失败';
       // }else{
       //     return '成功';
       // }

    }
    //测试2
    public function txxxx(){
        $ids='1,2,3';
        $re=Db::name('give')->where('id','in',$ids)->update(['status'=>0]);
        return $re;

//        $data['name']='美国';
//        $data['pid']=0;
//        $re=Db::name('area')->insertGetId($data);
////        $re=Db::name('goods')->where('id',12)->update(['shop_title'=>'条码超市3']);
//        $res=Db::name('goods')->where('id',2)->update(['shop_title'=>'天猫超市']);
//        if($res&&$re){
//            return true;
//        }else{
//            return false;
//        }
    }
    /*
     * 抽奖定时任务
     */
    public function reward_crontab(){
        set_time_limit (0);
        $crontab_start_time=time();
        while ((time()-$crontab_start_time)<58) {
            if((time()-$crontab_start_time)>58){//运行时间大于59秒，退出
                die;
            }
            $bonus_time = Db::name('config')->where(['name' => 'bonus_time', 'inc_type' => 'taojin'])->value('value');//开奖时间
            $reward_time = Db::name('config')->where(['name' => 'reward_time', 'inc_type' => 'taojin'])->value('value');//中奖时间
            $balance_give_integral = Db::name('config')->where(['name' => 'balance_give_integral', 'inc_type' => 'taojin'])->value('value');//N金沙兑换1糖果
            $yesterday_time = strtotime(date("Y-m-d", strtotime("-1 day")) . " " . $bonus_time);//昨天的开奖时间
            $bonus_time = strtotime(date("Y-m-d") . " " . $bonus_time);//今天开奖时间
            $is_reward_time = true;//是否随机中奖
            if ($reward_time) {
                $reward_time = strtotime($reward_time);//设置的中奖时间转换时间戳
                if ($reward_time > $yesterday_time && $reward_time < $bonus_time) {//设置的中奖时间，要在昨天开奖之后和今天开奖之前的时间段
                    $is_reward_time = false;
                } else {
                    $is_reward_time = true;
                }
            } else {
                $is_reward_time = true;
            }
            $today_time = strtotime(date("Y-m-d"), time());
            $where['reward_day'] = $today_time;
            $reward = Db::name('reward_log')->where($where)->find();//已经抽过奖励
            if (!$reward) {
                $data = [];
                $data['reward_day'] = $today_time;
                $data['status'] = 0;//抽奖是否完成
                $reward_log_id = Db::name('reward_log')->insertGetId($data);
                $is_reward = true;
            } else {
                $reward_log_id = $reward['id'];
                if ($reward['status']) {
                    $is_reward = false;//抽奖完毕
                } else {
                    $is_reward = true;
                }
            }
            if(((time()>$bonus_time)&&time()<$bonus_time+7200)&&$is_reward){//在开奖时间两个小时内
                $double_percent = Db::name('config')->where(['name' => 'double_percent', 'inc_type' => 'taojin'])->value('value');
                if ($is_reward_time) {//随机抽取一分钟
                    $where = [];
                    $start = strtotime(date("Y-m-d", strtotime("-1 day")) . " " . '14:00:00');
                    $end = strtotime(date("Y-m-d") . " " . '14:00:00');
                    $where['rank_time'] = ['between', [$start, $end]];
                    if ($reward['reward_time']) {//抽奖中断
                        $start_time = $reward['reward_time'];
                        $end_time = $start_time + 59;
                    } else {
                        //随机抽取一条昨天的数据
                        $ranking = Db::name('ranking')->where($where)->limit(1)->orderRaw('rand()')->find();
                        $start_time = strtotime(date('Y-m-d H:i', $ranking['rank_time']));
                        $end_time = $start_time + 59;
                    }
                    $where = [];
//                    $reward_ranking_list = Db::name('ranking')->alias('r')
//                        ->join('reward re','re.ranking_id=r.id','LEFT')
//                        ->where('re.ranking_id is null')
//                        ->where($where)->select();
                    $where['r.rank_time'] = ['between', [$start_time, $end_time]];
                    //查一百条数据处理
                    $reward_ranking_list = Db::name('ranking')->alias('r')
                        ->field('r.id,r.user_id,r.rank_time')
                        ->join('reward re','re.ranking_id=r.id','LEFT')
                        ->where('re.ranking_id is null')
                        ->where($where)->limit(100)->select();
                    if (!$reward_ranking_list) {//数据为空，无人中奖则退出
                        Db::name('reward_log')
                            ->where('id', $reward_log_id)->update(['status' => 1, 'reward_time' => $start_time,'num'=>0,'all_money'=>0]);
                        return '随机抽奖数据空，退出';
                    }
                    $where = [];
                    $where['rank_time'] = ['between', [$start_time, $end_time]];
                    $num=Db::name('ranking')->where($where)->count();//多少排位中奖
//                    $num = count($reward_ranking_list);//多少排位中奖
                    $jackpot = Db::name('jackpot')->where('id', 1)->value('integral_num');//奖池金额
                    $all_money = $jackpot * 10 / 100;
                    $everyone_money = sprintf("%.2f", $all_money / $num);
                    $RankingLogic = new RankingLogic();
                    foreach ($reward_ranking_list as $key => $value) {
                        $is_end = $RankingLogic->reward($value['id'], $value["user_id"], $everyone_money, $double_percent, $value['id'], $value['rank_time'], $bonus_time, $balance_give_integral);
                        if (!$is_end) {
                            Db::name('reward_log')->where('id', $reward_log_id)->update(['reward_time' => $start_time]);//出错，记录随机抽取的中奖时间段
                            return 'id为：' . $value["user_id"] . '出错';//如果某一条出错，则退出任务
                        }
                    }
                } else {
                    //今天中奖时间
//                $reward_time=strtotime(date("Y-m-d",strtotime("-1 day"))." ".$reward_time);
                    $start_time = strtotime(date('Y-m-d H:i', $reward_time));//中奖开始时间戳
                    $end_time = $start_time + 59;//中奖结束时间戳
                    $where = [];
                    $where['r.rank_time'] = ['between', [$start_time, $end_time]];
                    $reward_ranking_list = Db::name('ranking')->alias('r')
                        ->field('r.id,r.user_id,r.rank_time')
                        ->join('reward re','re.ranking_id=r.id','LEFT')
                        ->where('re.ranking_id is null')
                        ->where($where)->limit(100)->select();
                    if (!$reward_ranking_list) {//数据为空，无人中奖,则退出
                        Db::name('reward_log')
                            ->where('id', $reward_log_id)->update(['status' => 1, 'reward_time' => $start_time,'num'=>0,'all_money'=>0]);
                        return '固定时间抽奖数据空，退出';
                    }
                    $where = [];
                    $where['rank_time'] = ['between', [$start_time, $end_time]];
                    $num=Db::name('ranking')->where($where)->count();//多少排位中奖
//                    $num = count($reward_ranking_list);//多少排位中奖
                    $jackpot = Db::name('jackpot')->where('id', 1)->value('integral_num');//奖池金额
                    $all_money = $jackpot * 10 / 100;
                    $everyone_money = sprintf("%.2f", $all_money / $num);//每人得到多少
                    $RankingLogic = new RankingLogic();
                    foreach ($reward_ranking_list as $key => $value) {
                        //判断是否抽过奖
                        $is_end = $RankingLogic->reward($value['id'], $value["user_id"], $everyone_money, $double_percent, $value['id'], $value['rank_time'], $bonus_time, $balance_give_integral);
                        if (!$is_end) {
                            Db::name('reward_log')->where('id', $reward_log_id)->update(['reward_time' => $start_time,'num'=>$num,'all_money'=>$all_money]);//出错，记录中奖时间段
                            return 'id为：' . $value["user_id"] . '出错';//如果某一条出错，则退出任务
                        }
                    }
                }
                Db::name('reward_log')->where('id', $reward_log_id)->update(['status' => 1, 'reward_time' => $start_time,'num'=>$num,'all_money'=>$all_money]);//如果所以人抽奖完成，则完成
                return '抽奖完成';
                die;
            } else {
                if(time()>$bonus_time+7200){//超过抽奖时间两小时
                    if(!$is_reward){
                        return '不在抽奖时间内并且抽奖已经完成';
                    }else{
                        return '不在抽奖时间内并且抽奖未完成，请管理员查看代码修改！';
                    }
                }else{
                    if(!$is_reward){
                        return '抽奖已经完成';
                    }else{
                        if(time()>$bonus_time){
                            return '抽奖未完成并且在抽奖时间内，请管理员查看代码修改！';
                        }else{
                            return '未到抽奖时间！';
                        }
                    }
                }
                die;
            }
        }
    }
    //下单定时任务
    public function time_slot_crontab(){
        set_time_limit(0);
        $start_time=time();
        while ((time()-$start_time)<58){
//        for ($i=0;$i<60;$i++){
            if((time()-$start_time)>58){//运行时间大于59秒，退出
                return;
            }
            $where['status']=0;
//            $where['type']=0;
            $cro_list=Db::name('crontab')->where($where)->select();//定时任务下单
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
                            if($value['type']==1){//正常下单流程
                                $data['rank_time'] = $today_start;
                            }else{//包时间段
                                $data['rank_time'] = $today_start;
                                $today_start=$today_start+60;//+1分钟
                            }
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
    public function currency(){
        $currency=Db::name('config')->where(['inc_type'=>'taojin','name'=>'currency'])->value('value');
        $res=Db::name('config')->where(['name'=>'yesterday_currency','inc_type'=>'taojin'])->update(['value'=>$currency]);
        if($res){
            return '执行完毕';
        }else{
            return '修改币值失败';
        }
    }
    public function automatic_exit(){
        set_time_limit(0);
        $start_time=time();
        while ((time()-$start_time)<58){
            if((time()-$start_time)>58){//运行时间大于59秒，退出
                die;
            }
            $where['rank_status']=0;
            $before_time=strtotime("-61 day");
            $where['rank_time']=array('egt',$before_time);
            $ranking=Db::name('ranking')->where($where)->limit(1)->order('id')->find();
            if(!$ranking){
                sleep(1);
            }
            $double_percent = Db::name('config')->where(['name'=>'double_percent','inc_type'=>'taojin'])->value('value');
            $balance_give_integral = Db::name('config')->where(['name'=>'balance_give_integral','inc_type'=>'taojin'])->value('value');
            $RankingLogic = new RankingLogic();
            $RankingLogic->automatic_exit($balance_give_integral,$double_percent,$ranking);
        }
        return '运行结束';
    }
//    public function user_buy(){
//        $user_id=89;
//        $start_time=strtotime(date("Y-m-d")." 14:00:00");
//        $end_time=strtotime(date("Y-m-d")." 14:01:00");
//        if(time()>$start_time&&$end_time>time()){//开奖时间段，不能下单
//            $this->ajaxReturn(['status' => -2 , 'msg'=>'开奖时间段，不能下单，请等待'.$end_time-time().'秒']);
//        }
//        $num=6789;
//        if($num<1||(ceil($num)!=$num)){
//            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入正确的购买数量']);
//        }
//        $RankingLogic = new RankingLogic();
//        $res = $RankingLogic->buy_gold_shovel($user_id,$num);
//        return $res['msg'];
//    }
    //清理没有使用的饲料和没有收取的蛋
    public function clean_coop(){
        $t_time=$this->get_time();
        $one_time=$t_time['one_time'];
        $two_time=$t_time['two_time'];
        $three_time=$t_time['three_time'];
        $four_time=$t_time['four_time'];
        $time=time();
        if(($time>$one_time&&$time<$two_time)||($time>$three_time&&$time<$four_time)){
            //在可操作时间内，不做处理
            return '暂不可清理';
        }else{
            $feed_sum=Db::name('feed')->sum('num');//鸡窝饲料
            $chicken_count=Db::name('chicken')->where('status',1)->count();//鸡可收蛋状态
            if($feed_sum>0||$chicken_count>0){
                $data['num']=0;
                $re=Db::name('feed')->where('num>0')->update($data);
                $data_s['status']=0;
                $res=Db::name('chicken')->where('status=1')->update($data_s);
                if(!$re&&!$res){
                    return '饲料和收蛋处理失败';
                }else{
                    return '成功';
                }
            }else{
                return '无需清理';
            }
        }
    }
    /*
     * 每天释放余额
     */
    public function release_balance(){
        //获取每个用户买了多少只鸡
        set_time_limit(0);
        $chicken=Db::name('chicken')->field('user_id,count(*) count')->group('user_id')->select();
        $chickenLogic=new ChickenLogic();
        Db::startTrans();
        foreach ($chicken as $key=>$value){
            $user=Db::name('users')->field('id,is_verification,chicken_recharge_balance,recharge_balance')->where('id',$value['user_id'])->find();//有没有实名认证
            if($user['is_verification']){
                $user_num=$this->release($value['user_id'],$value['count']);
                if($user_num!=0){
                    $user_money=2*$user_num;//获得双倍
                    $user_data=[];
                    if($user['chicken_recharge_balance']!=0){//还有冻结余额
                        if($user_money>$user['chicken_recharge_balance']){//冻结余额不多了
                            $user_money=$user['chicken_recharge_balance'];
                        }
                        $user_data['chicken_recharge_balance']=$user['chicken_recharge_balance']-$user_money;
                        $user_data['recharge_balance']=$user['recharge_balance']+$user_money;
                        $res=Db::name('users')->where('id',$user['id'])->update($user_data);
                        $re=$chickenLogic->recharge_balance_log($user['id'],0,19,$user_money,$user['recharge_balance'],'余额解冻');
                        $r=$chickenLogic->release_balance_log($user['id'],0,-$user_money,$user['chicken_recharge_balance'],'余额解冻');
                        if(!$res||!$re||!$r){
                            Db::rollback();
                            return '释放失败！更新数据失败！';
                        }
                    }
                }
            }

        }
        Db::commit();
        return '释放成功';
    }
    /*
     * 释放万分之几
     */
    public function release($user_id,$num){
        $count=0;
        if($num<3){
            return $count;
        }else{
            $count=2;
            $count=$count+intval($num/10);
            $chicken=Db::name('chicken')->alias('c')
                ->join('users u','u.id=c.user_id','LEFT')
                ->field('c.user_id,count(*) count')->where('u.p_1',$user_id)->group('user_id')->select();
            $chicken_num=0;
            foreach ($chicken as $key=>$value){
                if($value['count']>=3){
                    $chicken_num=$chicken_num+1;
                }
            }
            $count=$count+intval($chicken_num/10);
            return $count;
        }

    }
    public function get_time(){
        $one_time=strtotime(date("Y-m-d")." 12:00:00");
        $two_time=strtotime(date("Y-m-d")." 13:00:00");
        $three_time=strtotime(date("Y-m-d")." 18:00:00");
        $four_time=strtotime(date("Y-m-d")." 19:00:00");
        $data['one_time']=$one_time;
        $data['two_time']=$two_time;
        $data['three_time']=$three_time;
        $data['four_time']=$four_time;
        return $data;
    }
}
