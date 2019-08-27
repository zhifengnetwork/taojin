<?php
namespace app\api\controller;
use think\Db;
use app\common\logic\RankingLogic;
class Ranking extends ApiBase
{
    /**
     * 获取金铲子
     */
    public function get_gold_shovel(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $system = Db::name('system')->field('id,name,title,money,logo')->where('id=1')->find();
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！', 'data' => $system]);
    }
    /**
     * 购买铲子
     */
    public function buy_gold_shovel(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $start_time=strtotime(date("Y-m-d")." 14:00:00");
        $end_time=strtotime(date("Y-m-d")." 14:01:00");
        if(time()>$start_time&&$end_time>time()){//开奖时间段，不能下单
            $this->ajaxReturn(['status' => -2 , 'msg'=>'开奖时间段，不能下单，请等待'.$end_time-time().'秒']);
        }
        $num=I('num',1);
        if($num<1||!is_numeric($num)){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入正确的购买数量']);
        }
        $RankingLogic = new RankingLogic();
        $res = $RankingLogic->buy_gold_shovel($user_id,$num);
        $this->ajaxReturn($res);
//        if ($res['status'] == -1 ) {
//            $this->ajaxReturn(['status' => -2, 'msg' => $res['msg']]);
//        }else{
//            $this->ajaxReturn(['status' => 1, 'msg' => '购买成功']);
//        }

    }

    /*
     * 排位
     */
    public function my_ranking(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pageParam=[];
        $where=[];
        $where['rank_status']=0;
        $where['rank_status']=0;
        $where['is_delete']=0;
        $list=Db::name('ranking')->field('id')->where($where)->select();
        $data_list=[];
        foreach ($list as $key=>$value)//二维数组转换
        {
            $data_list[$key]=$value['id'];
        }
        $data_list=array_flip($data_list);//键值翻转
        $where=[];
        $where['user_id']=$user_id;
        $where['rank_status']=0;
        $where['is_delete']=0;
        $rank_list=Db::name('ranking')
            ->field('id,user_id,rank_time')
            ->where($where)
            ->order('id')
            ->paginate(10,false,$pageParam);
        $rank_list=$rank_list->toArray();
        $rank_list=$rank_list['data'];
        foreach ($rank_list as $k=>$v){
            $rank_list[$k]['num']=$data_list[$v['id']];
            $rank_list[$k]['rank']=$data_list[$v['id']]+1;
            $rank_list[$k]['rank_time']=date('Y-m-d H:i:s',$v['rank_time']);
        }
        $where=[];
        $where['rank_status']=1;
        $count=Db::name('ranking')->where($where)->count();//出局人数
        $data['rank_list']=$rank_list;
        $data['count']=$count;
//        $subQuery = Db::name('ranking')
//            ->fieldRaw('*, @rownum := @rownum+1 AS rownum')
//            ->where($where)
//            ->buildSql();
//        $where=[];
//        $where['a.user_id']=$user_id;
//        $rank_list=Db::table($subQuery.' a')
//            ->where($where)
//            ->order('a.add_time')
//            ->paginate(10,false,$pageParam);
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$data]);

    }
    /*
     * 挂卖
     */
    public function user_auction(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pageParam=[];
        $where=[];
        $where['user_id']=$user_id;
        $list=Db::name('auction')
            ->field('id,user_id,currency_num,all_money,add_time')
            ->where($where)
            ->paginate(10,false,$pageParam);
        $list=$list->toArray();
        $list=$list['data'];
        foreach ($list as $k=>$v){
            $list[$k]['add_time']=date('Y-m-d H:i:s',$v['add_time']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$list]);
    }
    /*
     * 道具详情
     */
    public function goods_details(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $goods=Db::name('system')
            ->field('id,name,money,title,logo')
            ->find();
        $goods['logo']=SITE_URL.'/public'.$goods['logo'];
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$goods]);
    }
    /*
     * 我的订单
     */
    public function order(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $goods=Db::name('system')
            ->field('id,name,money,title,logo')
            ->find();
        $goods['logo']=SITE_URL.'/public'.$goods['logo'];
        $data['goods']=$goods;
        $pageParam=[];
        $where=[];
        $where['user_id']=$user_id;
        $where['rank_status']=0;
        $where['is_delete']=0;
        $rank_list=Db::name('ranking')
            ->field('id,user_id,rank_time')
            ->where($where)
            ->order('add_time')
            ->paginate(10,false,$pageParam);
        $rank_list=$rank_list->toArray();
        $rank_list=$rank_list['data'];
        foreach ($rank_list as $k=>$v){
            $rank_list[$k]['rank_time']=date('Y-m-d H:i:s',$v['rank_time']);
        }
        $data['ranking']=$rank_list;
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$data]);
    }
    /*
     * 删除订单
     */
    public function order_del(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $id=I('id');
        if(!$id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'id不能为空','data'=>'']);
        }
        $where['id']=$id;
        $where['user_id']=$user_id;
        $res=Db::name('ranking')->where($where)->update(['is_delete'=>1]);
        if($res){
            $this->ajaxReturn(['status' => 1, 'msg'=>'删除成功']);
        }else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'删除失败']);
        }
    }
    /*
     * 包时间段列表
     */
    public function tiem_list(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $time_list=Db::name('time_slot')->where('status',1)->order('id')->select();
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$time_list]);
    }
    /*
     * 过时方法
     * 包时间段
     */
    public function order_time_slot111(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $id=I('id');
        $where['id']=$id;
        $where['status']=1;
        $time_list=Db::name('time_slot')->where($where)->find();
        if(!$time_list){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'该时间段不存在，请重新选择']);
        }
        $data_time=explode(' - ',$time_list['time']);
        $today_start=strtotime(date('Y-m-d ').$data_time[0]);
        $today_end=strtotime(date('Y-m-d ').$data_time[1]);
        $bonus_time = Db::name('config')->where(['name'=>'bonus_time','inc_type'=>'taojin'])->value('value');//开奖时间
        $bonus_time=strtotime(date("Y-m-d")." ".$bonus_time);//今天开奖时间
        if($today_start>$today_end){//跨天
            $today_end=strtotime(date('Y-m-d ',strtotime('+1 day')).$data_time[1]);//结束时间+1天
            if($today_start>time()){
                if($bonus_time>time()){//未开奖
                    $num=($today_end-$today_start)/60;
                }else{
                    $today_start=strtotime(date('Y-m-d ',strtotime('+1 day')).$data_time[0]);
                    $today_end=strtotime(date('Y-m-d ',strtotime('+2 day')).$data_time[1]);
                    $num=($today_end-$today_start)/60;
                }
            }else{
                $today_start=strtotime(date('Y-m-d ',strtotime('+1 day')).$data_time[0]);
                $today_end=strtotime(date('Y-m-d ',strtotime('+2 day')).$data_time[1]);
                $num=($today_end-$today_start)/60;
            }

        }else{//未跨天
            if($today_start>time()){//当前时间段还没到
                if($bonus_time>time()){//未开奖
                    $num=($today_end-$today_start)/60;
                }else{//已经开奖，第二天
                    $today_start=strtotime(date('Y-m-d ',strtotime('+1 day')).$data_time[0]);
                    $today_end=strtotime(date('Y-m-d ',strtotime('+1 day')).$data_time[1]);
                    $num=($today_end-$today_start)/60;
                }
            }else{
                $today_start=strtotime(date('Y-m-d ',strtotime('+1 day')).$data_time[0]);
                $today_end=strtotime(date('Y-m-d ',strtotime('+1 day')).$data_time[1]);
                $num=($today_end-$today_start)/60;
            }
        }
        $RankingLogic = new RankingLogic();
        $res = $RankingLogic->buy_gold_shovel($user_id,$num,$today_start);
        $this->ajaxReturn($res);
    }
    /*
     * 包时间段
     */
    public function order_time_slot(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $start_time=strtotime(date("Y-m-d")." 14:00:00");
        $end_time=strtotime(date("Y-m-d")." 14:01:00");
        if(time()>$start_time&&$end_time>time()){//开奖时间段，不能下单
            $this->ajaxReturn(['status' => -2 , 'msg'=>'开奖时间段，不能下单，请等待'.$end_time-time().'秒']);
        }
        $type=I('type');
        switch ($type){
            case 1:
                $res=$this->time_list($type,$start_time);
                if(!$res){
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'距离开奖时间太近，不能购买']);
                }else{
                    $num=$res['num'];
                    $today_start=$res['time'];
                    $RankingLogic = new RankingLogic();
                    $res = $RankingLogic->buy_gold_shovel($user_id,$num,$today_start);
                    $this->ajaxReturn($res);
                }
                break;
            case 2:
                $res=$this->time_list($type,$start_time);
                if(!$res){
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'距离开奖时间太近，不能购买']);
                }else{
                    $num=$res['num'];
                    $today_start=$res['time'];
                    $RankingLogic = new RankingLogic();
                    $res = $RankingLogic->buy_gold_shovel($user_id,$num,$today_start);
                    $this->ajaxReturn($res);
                }
            case 4:
                $res=$this->time_list($type,$start_time);
                if(!$res){
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'距离开奖时间太近，不能购买']);
                }else{
                    $num=$res['num'];
                    $today_start=$res['time'];
                    $RankingLogic = new RankingLogic();
                    $res = $RankingLogic->buy_gold_shovel($user_id,$num,$today_start);
                    $this->ajaxReturn($res);
                }
            case 6:
                $res=$this->time_list($type,$start_time);
                if(!$res){
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'距离开奖时间太近，不能购买']);
                }else{
                    $num=$res['num'];
                    $today_start=$res['time'];
                    $RankingLogic = new RankingLogic();
                    $res = $RankingLogic->buy_gold_shovel($user_id,$num,$today_start);
                    $this->ajaxReturn($res);
                }
            case 24:
                $is_time=strtotime(date("Y-m-d")." 15:00:00");
                if($is_time>time()&&time()>$start_time){
                    $today_start=$end_time+1;
                    $num=1439;
                    $RankingLogic = new RankingLogic();
                    $res = $RankingLogic->buy_gold_shovel($user_id,$num,$today_start);
                    $this->ajaxReturn($res);
                }else{
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'包24小时请在14:00-15:00之间下单']);
                }
            default:
                $this->ajaxReturn(['status' => -2 , 'msg'=>'类型错误，不能购买']);
        }
    }
    public function time_list($type,$start_time){
        if($start_time>time()){//还没开奖
            if((time()+3600*$type)>$start_time){
                return false;
            }else{
                $today_start=time();
                $num=$type*60;
            }
        }else{
            $today_start=time();
            $num=$type*60;
        }
        return ['num'=>$num,'time'=>$today_start];
    }
}

