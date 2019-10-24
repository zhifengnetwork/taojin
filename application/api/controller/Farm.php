<?php
namespace app\api\controller;

use think\Db;
use Captcha;
use think\Loader;
use think\Request;
use think\Session;
use app\common\logic\ChickenLogic;

class Farm extends ApiBase
{
    /*
     * 养殖场首页
     */
    public function index(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $user=Db::name('users')->field('avatar,nick_name,recharge_balance,chicken_balance,egg_num,chicken_integral')->where('id',$user_id)->find();
        if(!$user['avatar']){
            $user['avatar']=SITE_URL.'/public/head/20190807156516165734848.png';
        }
        if(!$user['nick_name']){
            $user['nick_name']='未命名';
        }
        $time=time();
        $t_time=$this->get_time();
        $one_time=$t_time['one_time'];
        $two_time=$t_time['two_time'];
        $three_time=$t_time['three_time'];
        $four_time=$t_time['four_time'];
        $user['is_chicken']=0;//收取蛋不可用
        $user['is_feed']=0;//领取饲料不可用
        $user['is_feed_chicken']=0;//喂养不可用
        if(($time>$one_time&&$time<$two_time)||($time>$three_time&&$time<$four_time)){
            $chickenM=Db::name('chicken');
            $chicken_num=$chickenM->where('user_id',$user_id)->count();
            if($chicken_num){
                $feed_logM=Db::name('feed_log');
                $source=strtotime(date("Y-m-d")." 00:00:00");
                $where['source']=$source;
                if(($time>$one_time&&$time<$two_time)){
                    $where['type']=1;
                }else{
                    $where['type']=2;
                }
                $where['user_id']=$user_id;
                $feed_log=$feed_logM->where($where)->find();
                if(!$feed_log){
                    $user['is_feed']=1;//可用
                }
            }
            $feed=Db::name('feed');
            $user_feed=$feed->where('user_id',$user_id)->find();
            if($user_feed){
                $user['is_feed_chicken']=1;//可用
            }
            $chicken_list=$chickenM->where($where)->select();//当前用户已经喂养过的所有鸡
            if($chicken_list){
                $user['is_chicken']=1;//可用
            }
        }
        $chicken_coop_count=Db::name('chicken_coop')->where('user_id',$user_id)->count();
        if($chicken_coop_count==0){
            $data['user_id']=$user_id;
            $data['add_time']=time();
            Db::name('chicken_coop')->insertGetId($data);
        }
        $where['user_id']=$user_id;
        $user['egg_num']=Db::name('chicken')->where($where)->sum('num');
        $where['chicken_status']=0;
        $user['chicken_num']=Db::name('chicken')->where($where)->count();
        $user['notice']=$this->set_value('notice');
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$user]);

    }
    /*
     * 鸡窝列表
     */
    public function chicken_coop_list(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $page=I('page',1);
        $limit=I('limit',10);
        $start= ($page-1)*$limit;
        $coop_list=Db::name('chicken_coop')
            ->where('user_id',$user_id)
            ->order('coop_id')
            ->limit($start,$limit)
            ->select();
        foreach ($coop_list as $key=>$value){
            $coop_list[$key]['add_time']=date('Y-m-d',$value['add_time']);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$coop_list]);
    }
    public function chicken_list(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $coop_id=I('coop_id');
        if(!$coop_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入coop_id']);
        }
        $where['user_id']=$user_id;
        $where['coop_id']=$coop_id;
        $chicken_list=Db::name('chicken')
            ->where($where)
            ->order('add_time desc')
            ->select();
        foreach ($chicken_list as $key=>$value){
            $chicken_list[$key]['add_time']=date('Y-m-d',$value['add_time']);
            if($value['chicken_status']==1){
                $chicken_list[$key]['chicken_status_text']='死亡';
            }else{
                $chicken_list[$key]['chicken_status_text']='存活';
            }
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$chicken_list]);
    }
    public function purchase(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $page=I('page',1);
        $limit=I('limit',10);
        $start= ($page-1)*$limit;
        $purchase_list=Db::name('chicken_log')->where('user_id',$user_id)
            ->limit($start,$limit)->select();
        foreach ($purchase_list as $key=>$value){
            $purchase_list[$key]['add_time']=date('Y-m-d H:i:s',$value['add_time']);
            if($value['pay_type']==1){
                $purchase_list[$key]['pay_text']='金沙';
            }else{
                $purchase_list[$key]['pay_text']='余额';
            }
            if($value['type']==1){
                $purchase_list[$key]['type_text']='鸡窝';
            }else{
                $purchase_list[$key]['type_text']='鸡';
            }
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$purchase_list]);
    }
    public function set_value($key){
        $taojin =  Db::name('config')->where(['inc_type'=>'chicken'])->select();
        $info = convert_arr_kv($taojin,'name','value');
        return $info[$key];
    }
    public function verify($money){
        $is_int=$money/100;
        if(ceil($is_int)!=$is_int){
            return true;
        }else{
            return false;
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