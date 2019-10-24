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
        $user=Db::name('users')->field('avatar,nick_name,recharge_balance,chicken_balance')->where('id',$user_id)->find();
        if(!$user['avatar']){
            $user['avatar']=SITE_URL.'/public/head/20190807156516165734848.png';
        }
        if(!$user['nick_name']){
            $user['nick_name']='未命名';
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
            $coop_list[$key]['add_time']=date('Y-m-d H:i:s',$value['add_time']);
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
            $chicken_list[$key]['add_time']=date('Y-m-d H:i:s',$value['add_time']);
            if($value['chicken_status']==1){
                $chicken_list[$key]['chicken_status_text']='死亡';
            }else{
                $chicken_list[$key]['chicken_status_text']='存活';
            }
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$chicken_list]);
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
}