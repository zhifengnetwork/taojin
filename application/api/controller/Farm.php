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
        $user=Db::name('users')->field('avatar,nick_name,recharge_balance,chicken_balance,egg_num,chicken_integral,chicken_recharge_balance,is_give')->where('id',$user_id)->find();
        if(!$user['avatar']){
            $user['avatar']=SITE_URL.'/public/head/20190807156516165734848.png';
        }
        if(!$user['nick_name']){
            $user['nick_name']='未命名';
        }
        if($user['is_give']==0){
            $user_data['is_give']=1;
            $user_data['chicken_recharge_balance']=20000;
            $ids=Db::name('users')->where('id',$user_id)->update($user_data);
            if($ids){
                $user['chicken_recharge_balance']=20000;
            }
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
                $where=[];
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
            if($user_feed['num']>0){
                $user['is_feed_chicken']=1;//可用
            }
            $where=[];
            $where['user_id']=$user_id;
            $where['status']=1;//喂养了
            $where['chicken_status']=0;
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
        $where=[];
        $where['user_id']=$user_id;
        $user['egg']=Db::name('chicken')->where($where)->sum('num');
        $where['chicken_status']=0;
        $user['chicken_num']=Db::name('chicken')->where($where)->count();
        $user['notice']=$this->set_value('notice');
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$user]);

    }
    /*
     * 客服列表
     */
    public function receivables(){
        $chicken =  Db::name('config')->where(['inc_type'=>'chicken'])->select();
        $info = convert_arr_kv($chicken,'name','value');
        $data['qr_code']=SITE_URL.'/public'.$info['qr_code'];
        $data['customer_service_one']=$info['customer_service_one'];
        $data['customer_service_two']=$info['customer_service_two'];
        $data['customer_service_three']=$info['customer_service_three'];
        $data['recharge_link']=$info['recharge_link'];
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$data]);
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
    /*
     * 购买列表
     */
    public function purchase(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $page=I('page',1);
        $limit=I('limit',10);
        $start= ($page-1)*$limit;
        $purchase_list=Db::name('chicken_log')
            ->where('user_id',$user_id)
            ->order('id desc')
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
    /*
     * 养殖场金沙详细
     */
    public function chicken_balance_list(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $page=I('page',1);
        $limit=I('limit',10);
        $start= ($page-1)*$limit;
        $chicken_balance_list=Db::name('chicken_balance_log')->where('user_id',$user_id)
            ->limit($start,$limit)->select();
        foreach ($chicken_balance_list as $key=>$value){
            $chicken_balance_list[$key]['add_time']=date('Y-m-d H:i:s',$value['add_time']);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$chicken_balance_list]);
    }
    /*
     * 养殖场鸡蛋收益详细
     */
    public function egg_num_list(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $page=I('page',1);
        $limit=I('limit',10);
        $start= ($page-1)*$limit;
        $egg_num_list=Db::name('egg_log')->where('user_id',$user_id)
            ->limit($start,$limit)->select();
        foreach ($egg_num_list as $key=>$value){
            $egg_num_list[$key]['add_time']=date('Y-m-d H:i:s',$value['add_time']);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$egg_num_list]);
    }
    /*
     * 养殖场糖果详细
     */
    public function chicken_integral_list(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $page=I('page',1);
        $limit=I('limit',10);
        $start= ($page-1)*$limit;
        $egg_num_list=Db::name('chicken_integral_log')->where('user_id',$user_id)
            ->limit($start,$limit)->select();
        foreach ($egg_num_list as $key=>$value){
            $egg_num_list[$key]['add_time']=date('Y-m-d H:i:s',$value['add_time']);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$egg_num_list]);
    }
    /*
     * 余额明细
     */
    public function recharge_balance_detailed(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pageParam=[];
        $lock_balance_list = Db::name('moneydetail')->alias('m')
            ->join('users u','u.id=m.be_user_id','LEFT')
            ->where('m.type=2 or m.type=5 or m.type=13 or m.type=17 or m.type=18 or m.type=19')
            ->where(['m.user_id'=>$user_id,'m.typefrom'=>0])
            ->field('m.id,m.user_id,m.be_user_id,m.money,m.type,m.typefrom,m.intro,m.createtime,u.phone be_mobile')
            ->order('id DESC')
            ->paginate(10,false,$pageParam)
            ->toArray();
        $lock_balance_list = $lock_balance_list['data'];
        foreach ($lock_balance_list as $key=>$value){
//            $lock_balance_list[$key]['be_mobile'] = Db::name('users')->where(['id'=>$value['be_user_id']])->value('phone');
            $lock_balance_list[$key]['createtime'] = date('Y-m-d H:i:s',$value['createtime']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$lock_balance_list]);
    }
    /*
     * 红包抽奖
     */
    public function random_red(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $chicken_num=Db::name('chicken')->where('user_id',$user_id)->count();
        if($chicken_num==0){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请先购买鸡，再抢红包']);
        }
        $one_time=strtotime(date("Y-m-d")." 12:00:00");
        $two_time=strtotime(date("Y-m-d")." 12:00:10");
        $time=time();
        if($time>$one_time&&$time<$two_time){
            $source=strtotime(date("Y-m-d")." 00:00:00");
            $where['source']=$source;
            $count=Db::name('red_log')->where($where)->count();
            $sys_count=$this->set_value('user_red_num');//红包数量
            if($count>=$sys_count){
                $this->ajaxReturn(['status' => 1 , 'msg'=>'红包是空的，请再接再厉！','data'=>0]);
            }
            $sys_chicken_integral=$this->set_value('red_num');//糖果数量
            $chicken_integral=Db::name('red_log')->where($where)->sum('chicken_integral');
            if($chicken_integral>=$sys_chicken_integral){
                $this->ajaxReturn(['status' => 1 , 'msg'=>'红包是空的，请再接再厉！','data'=>0]);
            }
            $max=($sys_chicken_integral-$chicken_integral)-($sys_count-$count);
            $luck=$max;
            if($max<=0){
                $red_num=$this->red_pack(1,$luck);
            }else{
                $max=ceil(($sys_chicken_integral-$chicken_integral)/($sys_count-$count));
                $red_num=$this->red_pack($max,$luck);
            }
            if($red_num==0){
                $this->ajaxReturn(['status' => 1 , 'msg'=>'红包是空的，请再接再厉！','data'=>0]);
            }else{//抽到红包
                Db::startTrans();
                $data=[];
                $data['user_id']=$user_id;
                $data['source']=$source;
                $data['chicken_integral']=$red_num;
                $data['add_time']=time();
                $ids=Db::name('red_log')->insertGetId($data);
                $data=[];
                $data['user_id']=$user_id;
                $data['type']=1;
                $data['money']=$red_num;
                $data['desc']='抢红包获得'.$red_num;
                $data['add_time']=time();
                $id=Db::name('chicken_integral_log')->insertGetId($data);
                $re=Db::name('users')->where('id',$user_id)->setInc('chicken_integral',$red_num);
                if(!$re||!$id||!$ids){
                    Db::rollback();
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'红包是空的，抢失败了！']);
                }
                Db::commit();
                $this->ajaxReturn(['status' => 1 , 'msg'=>'抢到了','data'=>$red_num]);
            }
        }else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'抢红包时间过了！']);
        }
    }
    //随机抽取糖果
    public function red_pack($max,$luck){
        $code = mt_rand(0,100);
        if($code<50){
            return 0;
        }elseif($code>50&&$code<75){
            $re=mt_rand(0,$max);
            if($code==66){
                return mt_rand(1,$luck);
            }else{
                return $re;
            }
        }elseif($code>95){
            return mt_rand(1,$luck);
        }else{
            return 1;
        }
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
        $two_time=strtotime(date("Y-m-d")." 18:00:00");
        $three_time=strtotime(date("Y-m-d")." 18:00:00");
        $four_time=strtotime(date("Y-m-d")." 19:00:00");
        $data['one_time']=$one_time;
        $data['two_time']=$two_time;
        $data['three_time']=$three_time;
        $data['four_time']=$four_time;
        return $data;
    }
}