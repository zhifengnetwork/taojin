<?php
namespace app\api\controller;

use think\Db;
use Captcha;
use think\Loader;
use think\Request;
use think\Session;
use app\common\logic\ChickenLogic;

class Egg extends ApiBase
{
    /*
     * 购买鸡
     */
    public function buy_chicken(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        if(time()<1572408000){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'10月30号12点开放购买']);
        }
        $type=I('type');//支付类型  1  金沙  2 余额
        $money=I('money');
//        $paypwd=I('paypwd');
        $num=I('num');
//        if(!$paypwd){
//            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入支付密码']);
//        }
        $user=Db::name('users')->where(['id'=>$user_id])->find();
//        $verify = password_verify($paypwd,$user['paypwd']);
//        if ($verify == false) {
//            $this->ajaxReturn(['status' => -2 , 'msg'=>'支付密码错误','data'=>null]);
//        }
        if(!$type){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入类型']);
        }
        if(!$money){
            $this->ajaxReturn(['status' => -2, 'msg' => '请输入金额！']);
        }
        if(!$num){
            $this->ajaxReturn(['status' => -2, 'msg' => '请输入购买数量！']);
        }
        if($type==1&&$money>$user['chicken_balance']){
            $this->ajaxReturn(['status' => -2, 'msg' => '金沙不足，请重新选择！']);
        }
        if($type==2&&$money>$user['recharge_balance']){
            $this->ajaxReturn(['status' => -2, 'msg' => '余额不足，请充值！']);
        }
        $chickenLogic=new ChickenLogic();
        $this->ajaxReturn($chickenLogic->buy_chicken($user_id,$type,$money,$num));
    }
    /*
     * 购买鸡窝
     */
    public function buy_chicken_coop(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        if(time()<1572408000){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'10月30号12点开放购买']);
        }
        $type=I('type');//支付类型  1  金沙  2 余额
        $money=I('money');
//        $paypwd=I('paypwd');
        $num=I('num');
//        if(!$paypwd){
//            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入支付密码']);
//        }
        $user=Db::name('users')->where(['id'=>$user_id])->find();
//        $verify = password_verify($paypwd,$user['paypwd']);
//        if ($verify == false) {
//            $this->ajaxReturn(['status' => -2 , 'msg'=>'支付密码错误','data'=>null]);
//        }
        if(!$type){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入类型']);
        }
        if(!$money){
            $this->ajaxReturn(['status' => -2, 'msg' => '请输入金额！']);
        }
        if(!$num){
            $this->ajaxReturn(['status' => -2, 'msg' => '请输入购买数量！']);
        }
        if($type==1&&$money>$user['chicken_balance']){
            $this->ajaxReturn(['status' => -2, 'msg' => '金沙不足，请重新选择！']);
        }
        if($type==2&&$money>$user['recharge_balance']){
            $this->ajaxReturn(['status' => -2, 'msg' => '余额不足，请充值！']);
        }
        $chickenLogic=new ChickenLogic();
        $this->ajaxReturn($chickenLogic->buy_chicken_coop($user_id,$type,$money,$num));
    }
    /*
     * 抢饲料
     */
    public function rob_feed(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        if(time()<1572408000){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'10月30号12点开放']);
        }
        $time=time();

        $t_time=$this->get_time();
        $one_time=$t_time['one_time'];
        $two_time=$t_time['two_time'];
        $three_time=$t_time['three_time'];
        $four_time=$t_time['four_time'];
        $chickenLogic=new ChickenLogic();
        if(($time>$one_time&&$time<$two_time)){
            $this->ajaxReturn($chickenLogic->rob_feed($user_id,1));
        }elseif(($time>$three_time&&$time<$four_time)){
            $this->ajaxReturn($chickenLogic->rob_feed($user_id,2));
        }
        else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请在12:00-13:00、18:00-19:00，两个时间段抢饲料！']);
        }
    }
    /*
     * 喂养鸡
     */
    public function feed_chicken(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $time=time();

        $t_time=$this->get_time();
        $one_time=$t_time['one_time'];
        $two_time=$t_time['two_time'];
        $three_time=$t_time['three_time'];
        $four_time=$t_time['four_time'];
        $chickenLogic=new ChickenLogic();
        if(($time>$one_time&&$time<$two_time)){
            $this->ajaxReturn($chickenLogic->feed_chicken($user_id));
        }elseif(($time>$three_time&&$time<$four_time)){
            $this->ajaxReturn($chickenLogic->feed_chicken($user_id));
        }
        else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请在12:00-13:00、18:00-19:00，两个时间段喂养鸡！']);
        }
    }
    /*
     * 收取蛋
     */
    public function harvest_egg(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $time=time();
        $t_time=$this->get_time();
        $one_time=$t_time['one_time'];
        $two_time=$t_time['two_time'];
        $three_time=$t_time['three_time'];
        $four_time=$t_time['four_time'];
        $chickenLogic=new ChickenLogic();
        if(($time>$one_time&&$time<$two_time)){
            $this->ajaxReturn($chickenLogic->harvest_egg($user_id));
        }elseif(($time>$three_time&&$time<$four_time)){
            $this->ajaxReturn($chickenLogic->harvest_egg($user_id));
        }
        else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请在12:00-13:00、18:00-19:00，两个时间段收取鸡蛋！']);
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
    //养殖场金沙转账
    public function give_chicken_balance(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $balance=I('money');
        $phone=I('phone');
        $paypwd=I('paypwd');
        if(!$paypwd){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入支付密码']);
        }

        if(!$phone){
            $this->ajaxReturn(['status' => -2, 'msg' => '手机号不能为空！']);
        }
        if(!$balance){
            $this->ajaxReturn(['status' => -2, 'msg' => 'money不能为空！']);
        }
        if($balance<1){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入正确的金额']);
        }
        if($this->verify($balance)){//判断是否为100的整数倍
            $this->ajaxReturn(['status' => -2, 'msg' => '金沙必须是100的倍数！']);
        }
        $user=Db::name('users')->where(['id'=>$user_id])->find();
        $give_user=Db::name('users')->where(['phone'=>$phone])->find();
        if($give_user['id']==$user_id){
            $this->ajaxReturn(['status' => -2, 'msg' => '不能转账给自己！']);
        }
        $verify = password_verify($paypwd,$user['paypwd']);
        if ($verify == false) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'支付密码错误','data'=>null]);
        }
        $user_all_balance=$user['chicken_balance'];
        if($user_all_balance<$balance){
            $this->ajaxReturn(['status' => -2, 'msg' => '您的金沙不足，不能转账！']);
        }
        if(!$give_user){
            $this->ajaxReturn(['status' => -2, 'msg' => '赠送用户不存在，请输入正确的用户手机号！']);
        }
        Db::startTrans();
        $res=Db::name('users')->where(['phone'=>$phone])->setInc('recharge_balance',$balance);
        if($res){
            $detail['user_id']=$give_user['id'];
            $detail['type']=5;//被赠送
            $detail['be_user_id']=$user_id;//赠送者
            $detail['money']=$balance;
            $detail['createtime']=time();
            $detail['intro']=$user['phone'].'赠送';
            $id=Db::name('moneydetail')->insertGetId($detail);//用户之间交易，无需处理
            if(!$id){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
            }
        }
//        if($res){
//            $data['user_id']=$give_user['id'];
//            $data['to_user_id']=$user_id;
//            $data['type']=2;
//            $data['money']=$balance;
//            $data['desc']=$user['phone'].'赠送';
//            $data['add_time']=time();
//            $id=Db::name('chicken_balance_log')->insertGetId($data);
//            if(!$id){
//                Db::rollback();
//                $this->ajaxReturn(['status' => -2, 'msg' => '转账失败！']);
//            }
//        }
        $re=Db::name('users')->where(['id'=>$user_id])->setDec('chicken_balance',$balance);
        if($re){
            $detail=[];
            $detail['user_id']=$user_id;
            $detail['to_user_id']=$give_user['id'];
            $detail['type']=1;
            $detail['money']=-$balance;
            $detail['desc']='赠送给'.$give_user['phone'];
            $detail['add_time']=time();
            $id=Db::name('chicken_balance_log')->insertGetId($detail);
            if(!$id){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '转账失败！']);
            }
        }
        if(!$res||!$re){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '转账失败！']);
        }else{
            Db::commit();
            $this->ajaxReturn(['status' => 1, 'msg' => '转账成功！']);
        }

    }
    //养殖场余额转账
    public function give_recharge_balance(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $balance=I('money');
        $phone=I('phone');
        $paypwd=I('paypwd');
        if(!$paypwd){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入支付密码']);
        }
        if(!$phone){
            $this->ajaxReturn(['status' => -2, 'msg' => '手机号不能为空！']);
        }
        if(!$balance){
            $this->ajaxReturn(['status' => -2, 'msg' => 'money不能为空！']);
        }
        if($balance<1){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入正确的金额']);
        }
        if($this->verify($balance)){//判断是否为100的整数倍
            $this->ajaxReturn(['status' => -2, 'msg' => '余额必须是100的倍数！']);
        }
        $user=Db::name('users')->where(['id'=>$user_id])->find();
        $give_user=Db::name('users')->where(['phone'=>$phone])->find();
        if($give_user['id']==$user_id){
            $this->ajaxReturn(['status' => -2, 'msg' => '不能转账给自己！']);
        }
        $verify = password_verify($paypwd,$user['paypwd']);
        if ($verify == false) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'支付密码错误','data'=>null]);
        }
        $user_all_balance=$user['recharge_balance'];
        if($user_all_balance<$balance){
            $this->ajaxReturn(['status' => -2, 'msg' => '您的余额不足，不能转账！']);
        }
        if(!$give_user){
            $this->ajaxReturn(['status' => -2, 'msg' => '赠送用户不存在，请输入正确的用户手机号！']);
        }
        Db::startTrans();
        $res=Db::name('users')->where(['phone'=>$phone])->setInc('recharge_balance',$balance);
        if($res){
            $detail['user_id']=$give_user['id'];
            $detail['type']=5;//被赠送
            $detail['be_user_id']=$user_id;//赠送者
            $detail['money']=$balance;
            $detail['createtime']=time();
            $detail['intro']=$user['phone'].'赠送';
            $id=Db::name('moneydetail')->insertGetId($detail);//用户之间交易，无需处理
            if(!$id){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '转账失败！']);
            }
        }
        $re=Db::name('users')->where(['id'=>$user_id])->setDec('recharge_balance',$balance);
        if($res){
            $detail['user_id']=$user_id;
            $detail['type']=2;//赠送
            $detail['be_user_id']=$give_user['id'];//赠送者
            $detail['money']=-$balance;
            $detail['createtime']=time();
            $detail['intro']='赠送给'.$give_user['phone'];
            $id=Db::name('moneydetail')->insertGetId($detail);//用户之间交易，无需处理
            if(!$id){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '转账失败！']);
            }
        }
        if(!$res||!$re){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '转账失败！']);
        }else{
            Db::commit();
            $this->ajaxReturn(['status' => 1, 'msg' => '转账成功！']);
        }

    }
    //糖果转账
    public function give_chicken_integral(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $integral=I('money');
        $phone=I('phone');
        $paypwd=I('paypwd');
        $type=I('type',1);//转账类型  1：养殖场  2：淘金

        if(!$paypwd){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入支付密码']);
        }
        if(!$phone){
            $this->ajaxReturn(['status' => -2, 'msg' => '手机号不能为空！']);
        }
        if(!$integral){
            $this->ajaxReturn(['status' => -2, 'msg' => 'money不能为空！']);
        }
        if($integral<1){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入正确的糖果']);
        }
        if($this->verify($integral)){//判断是否为100的整数倍
            $this->ajaxReturn(['status' => -2, 'msg' => '糖果数量必须是100的倍数！']);
        }
        $user=Db::name('users')->where(['id'=>$user_id])->find();
        $give_user=Db::name('users')->where(['phone'=>$phone])->find();

        $verify = password_verify($paypwd,$user['paypwd']);
        if ($verify == false) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'支付密码错误','data'=>null]);
        }
        if($user['chicken_integral']<$integral){
            $this->ajaxReturn(['status' => -2, 'msg' => '您的糖果不足，不能赠送！']);
        }
        if(!$give_user){
            $this->ajaxReturn(['status' => -2, 'msg' => '赠送用户不存在，请输入正确的用户id！']);
        }
        if($this->verify($integral)){//判断是否为100的整数倍
            $this->ajaxReturn(['status' => -2, 'msg' => '糖果必须是100的倍数！']);
        }
        Db::startTrans();
        $chickenLogic=new ChickenLogic();
        if($type==1){//转养殖场
            if($give_user['id']==$user_id){
                $this->ajaxReturn(['status' => -2, 'msg' => '不能赠送给自己！']);
            }
            $res=Db::name('users')->where(['phone'=>$phone])->setInc('chicken_integral',$integral);
            if($res){
                $id=$chickenLogic->chicken_integral_log($give_user['id'],$user_id,0,$integral,$give_user['chicken_integral'],$user['phone'].'转入');
                if(!$id){
                    Db::rollback();
                    $this->ajaxReturn(['status' => -2, 'msg' => '转账失败！']);
                }
            }
        }else{//转淘金
            $res=Db::name('users')->where(['phone'=>$phone])->setInc('integral',$integral);
            if($res){
                $detail['u_id']=$give_user['id'];
                $detail['u_name']=$give_user['nick_name'];
                $detail['for_user_id']=$user_id;//赠送者
                $detail['for_user_name']=$user['nick_name'];//赠送者
                $detail['integral']=$integral;
                $detail['type']=2;//被赠与
                $detail['then_integral']=$give_user['integral'];
                $detail['createtime']=time();
                $id=Db::name('integral')->insertGetId($detail);
                if(!$id){
                    Db::rollback();
                    $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
                }
            }
        }
        $re=Db::name('users')->where(['id'=>$user_id])->setDec('chicken_integral',$integral);
        if($re){
            $ids=$chickenLogic->chicken_integral_log($user_id,$give_user['id'],2,$integral,$user['chicken_integral'],'转账给'.$give_user['phone']);
            if(!$ids){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '转账失败！']);
            }
        }
        if(!$res||!$re){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '转账失败！']);
        }else{
            Db::commit();
            $this->ajaxReturn(['status' => 1, 'msg' => '转账成功！']);
        }
    }
    //鸡蛋收益转账
    public function give_egg_num(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $egg_num=I('money');
        $phone=I('phone');
        $paypwd=I('paypwd');
        if(!$paypwd){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入支付密码']);
        }
        if(!$phone){
            $this->ajaxReturn(['status' => -2, 'msg' => '手机号不能为空！']);
        }
        if(!$egg_num){
            $this->ajaxReturn(['status' => -2, 'msg' => 'money不能为空！']);
        }
        if($egg_num<1){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入正确的币']);
        }
        if($this->verify($egg_num)){//判断是否为100的整数倍
            $this->ajaxReturn(['status' => -2, 'msg' => '鸡蛋收益必须是100的倍数！']);
        }
        $user=Db::name('users')->where(['id'=>$user_id])->find();
        $give_user=Db::name('users')->where(['phone'=>$phone])->find();
        if($give_user['id']==$user_id){
            $this->ajaxReturn(['status' => -2, 'msg' => '不能转账给自己！']);
        }
        $verify = password_verify($paypwd,$user['paypwd']);
        if ($verify == false) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'支付密码错误','data'=>null]);
        }
        if($user['egg_num']<$egg_num){
            $this->ajaxReturn(['status' => -2, 'msg' => '您的鸡蛋收益不足，不能转账！']);
        }
        if(!$give_user){
            $this->ajaxReturn(['status' => -2, 'msg' => '转账用户不存在，请输入正确的用户手机号！']);
        }
        Db::startTrans();
        $chickenLogic=new ChickenLogic();
        $res=Db::name('users')->where(['phone'=>$phone])->setInc('recharge_balance',$egg_num);
        if($res){
            $detail['user_id']=$give_user['id'];
            $detail['type']=5;//被赠送
            $detail['be_user_id']=$user_id;//赠送者
            $detail['money']=$egg_num;
            $detail['createtime']=time();
            $detail['intro']=$user['phone'].'赠送';
            $id=Db::name('moneydetail')->insertGetId($detail);//用户之间交易，无需处理
            if(!$id){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
            }
        }
//        $res=Db::name('users')->where(['phone'=>$phone])->setInc('egg_num',$egg_num);
//        if($res){
//            $id=$chickenLogic->egg_log($give_user['id'],$user_id,2,$egg_num,$give_user['egg_num'],$user['phone'].'转账');
//            if(!$id){
//                Db::rollback();
//                $this->ajaxReturn(['status' => -2, 'msg' => '转账失败！']);
//            }
//        }
        $re=Db::name('users')->where(['id'=>$user_id])->setDec('egg_num',$egg_num);
        if($re){
            $ids=$chickenLogic->egg_log($user_id,$give_user['id'],1,-$egg_num,$user['egg_num'],'转账给'.$give_user['phone']);
            if(!$ids){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '转账失败！']);
            }
        }
        if(!$res||!$re){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '转账1失败！']);
        }else{
            Db::commit();
            $this->ajaxReturn(['status' => 1, 'msg' => '转账成功！']);
        }
    }
    public function set_value($key){
        $taojin =  Db::name('config')->where(['inc_type'=>'taojin'])->select();
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