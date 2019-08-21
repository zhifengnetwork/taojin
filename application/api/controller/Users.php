<?php
namespace app\api\controller;

use think\Db;
use Captcha;
use think\Loader;
use think\Request;
use think\Session;
use app\common\logic\LoginLogic;

class Users extends ApiBase
{

    public function give_balance(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $u_id=I('u_id');
        $balance=I('money');
        if(!$u_id){
            $this->ajaxReturn(['status' => -2, 'msg' => 'u_id不能为空！']);
        }
        if(!$balance){
            $this->ajaxReturn(['status' => -2, 'msg' => 'money不能为空！']);
        }

        if($this->verify($balance)){//判断是否为100的整数倍
            $this->ajaxReturn(['status' => -2, 'msg' => '金额必须是100的倍数！']);
        }

        $user=Db::name('users')->where(['id'=>$user_id])->find();
        $give_user=Db::name('users')->where(['id'=>$u_id])->find();
        if($user['balance']<$balance){
            $this->ajaxReturn(['status' => -2, 'msg' => '您的余额不足，不能赠送！']);
        }
        if(!$give_user){
            $this->ajaxReturn(['status' => -2, 'msg' => '赠送用户不存在，请输入正确的用户id！']);
        }
        Db::startTrans();
        $res=Db::name('users')->where(['id'=>$u_id])->setInc('balance',$balance);
        if($res){
            $detail['user_id']=$u_id;
            $detail['type']=5;//被赠送
            $detail['money']=$balance;
            $detail['createtime']=time();
            $detail['intro']=$user['nick_name'].'赠送';
            $id=Db::name('moneydetail')->insertGetId($detail);
            if(!$id){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
            }
        }
        $re=Db::name('users')->where(['id'=>$user_id])->setDec('balance',$balance);
        if($re){
            $detail=[];
            $detail['user_id']=$user_id;
            $detail['type']=2;//赠送
            $detail['money']=-$balance;
            $detail['createtime']=time();
            $detail['intro']='赠送给'.$give_user['nick_name'];
            $ids=Db::name('moneydetail')->insertGetId($detail);
            if(!$ids){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
            }
        }
        if(!$res||!$re){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
        }else{
            Db::commit();
            $this->ajaxReturn(['status' => 1, 'msg' => '赠送成功！']);
        }
    }
    public function give_integral(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $u_id=I('u_id');
        $integral=I('integral');
        if(!$u_id){
            $this->ajaxReturn(['status' => -2, 'msg' => 'u_id不能为空！']);
        }
        if(!$integral){
            $this->ajaxReturn(['status' => -2, 'msg' => 'money不能为空！']);
        }

        if($this->verify($integral)){//判断是否为100的整数倍
            $this->ajaxReturn(['status' => -2, 'msg' => '糖果数量必须是100的倍数！']);
        }
        $user=Db::name('users')->where(['id'=>$user_id])->find();
        $give_user=Db::name('users')->where(['id'=>$u_id])->find();
        if($user['integral']<$integral){
            $this->ajaxReturn(['status' => -2, 'msg' => '您的糖果不足，不能赠送！']);
        }
        if(!$give_user){
            $this->ajaxReturn(['status' => -2, 'msg' => '赠送用户不存在，请输入正确的用户id！']);
        }
        Db::startTrans();
        $res=Db::name('users')->where(['id'=>$u_id])->setInc('integral',$integral);
        if($res){
            $detail['u_id']=$u_id;
            $detail['u_name']=$give_user['nick_name'];
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
        $re=Db::name('users')->where(['id'=>$user_id])->setDec('integral',$integral);
        if($re){
            $detail=[];
            $detail['u_id']=$user_id;
            $detail['u_name']=$user['nick_name'];
            $detail['integral']=-$integral;
            $detail['then_integral']=$user['integral'];
            $detail['createtime']=time();
            $ids=Db::name('integral')->insertGetId($detail);
            if(!$ids){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
            }
        }
        if(!$res||!$re){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
        }else{
            Db::commit();
            $this->ajaxReturn(['status' => 1, 'msg' => '赠送成功！']);
        }
    }
    public function give_currency(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $u_id=I('u_id');
        $currency=I('currency');
        if(!$u_id){
            $this->ajaxReturn(['status' => -2, 'msg' => 'u_id不能为空！']);
        }
        if(!$currency){
            $this->ajaxReturn(['status' => -2, 'msg' => 'currency不能为空！']);
        }

        if($this->verify($currency)){//判断是否为100的整数倍
            $this->ajaxReturn(['status' => -2, 'msg' => '币数量必须是100的倍数！']);
        }
        $user=Db::name('users')->where(['id'=>$user_id])->find();
        $give_user=Db::name('users')->where(['id'=>$u_id])->find();
        if($user['currency']<$currency){
            $this->ajaxReturn(['status' => -2, 'msg' => '您的币不足，不能赠送！']);
        }
        if(!$give_user){
            $this->ajaxReturn(['status' => -2, 'msg' => '赠送用户不存在，请输入正确的用户id！']);
        }
        Db::startTrans();
        $res=Db::name('users')->where(['id'=>$u_id])->setInc('currency',$currency);
        if($res){
            $detail['user_id']=$u_id;
            $detail['user_name']=$give_user['nick_name'];
            $detail['type']=3;//被赠与
            $detail['currency']=$currency;
            $detail['old_currency']=$give_user['currency'];
            $detail['desc']=$user['nick_name'].'赠送';
            $detail['add_time']=time();
            $id=Db::name('users_currency')->insertGetId($detail);
            if(!$id){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
            }
        }
        $re=Db::name('users')->where(['id'=>$user_id])->setDec('currency',$currency);
        if($re){
            $detail=[];
            $detail['user_id']=$user_id;
            $detail['user_name']=$user['nick_name'];
            $detail['type']=1;//赠送
            $detail['currency']=-$currency;
            $detail['old_currency']=$user['currency'];
            $detail['desc']='赠送'.$give_user['nick_name'];
            $detail['add_time']=time();
            $ids=Db::name('users_currency')->insertGetId($detail);
            if(!$ids){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
            }
        }
        if(!$res||!$re){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
        }else{
            Db::commit();
            $this->ajaxReturn(['status' => 1, 'msg' => '赠送成功！']);
        }
    }
    public function exchange_currency(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $currency=I('currency');
        if(!$currency||intval($currency)<0){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入正确的兑换币数量！','data'=>'']);
        }
        $integral_num=$this->set_value('exchange_integral');
        $balance_num=$this->set_value('exchange_money');
        $integral=$currency*$integral_num;
        $balance=$currency*$balance_num;
        $user=Db::name('users')->where(['id'=>$user_id])->find();
        if($user['integral']<$integral){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'糖果数量不足'.$integral.'，请重新兑换！','data'=>'']);
        }
        if($user['balance']<$balance){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'金沙数量不足'.$balance.'，请重新兑换！','data'=>'']);
        }
        Db::startTrans();
        $res=Db::name('users')->where(['id'=>$user_id])->setInc('currency',$currency);
        if($res){
            $detail['user_id']=$user_id;
            $detail['user_name']=$user['nick_name'];
            $detail['currency']=$currency;
            $detail['old_currency']=$user['currency'];
            $detail['desc']='兑换币';
            $detail['add_time']=time();
            $id=Db::name('users_currency')->insertGetId($detail);
            if(!$id){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '兑换失败！']);
            }
        }
        $re=Db::name('users')->where(['id'=>$user_id])->setDec('integral',$integral);
        if($re){
            $detail=[];
            $detail['u_id']=$user_id;
            $detail['u_name']=$user['nick_name'];
            $detail['integral']=-$integral;
            $detail['then_integral']=$user['integral'];
            $detail['type']=1;//兑换
            $detail['createtime']=time();
            $id=Db::name('integral')->insertGetId($detail);
            if(!$id){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '兑换失败！']);
            }
        }
        $r=Db::name('users')->where(['id'=>$user_id])->setDec('balance',$balance);
        if($r){
            $detail=[];
            $detail['user_id']=$user_id;
            $detail['type']=6;//兑换
            $detail['money']=-$balance;
            $detail['createtime']=time();
            $detail['intro']='兑换币'.$currency.'个';
            $ids=Db::name('moneydetail')->insertGetId($detail);
            if(!$ids){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '兑换失败！']);
            }
        }
        if(!$res||!$re||!$r){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '兑换失败！']);
        }else{
            Db::commit();
            $this->ajaxReturn(['status' => 1, 'msg' => '兑换成功！']);
        }
    }
    public function exchange_currency123(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $integral=I('integral');
        $balance=I('money');
        $integral_num=$this->set_value('exchange_integral');
        $balance_num=$this->set_value('exchange_money');
        $in_num=intval($integral/$integral_num);
        $ba_num=intval($balance/$balance_num);
        $user=Db::name('users')->where(['id'=>$user_id])->find();
        if($in_num==$ba_num){
            Db::startTrans();
            $res=Db::name('users')->where(['id'=>$user_id])->setInc('currency',$in_num);
            if($res){
                $detail['user_id']=$user_id;
                $detail['user_name']=$user['nick_name'];
                $detail['currency']=$in_num;
                $detail['old_currency']=$user['currency'];
                $detail['desc']='兑换币';
                $detail['add_time']=time();
                $id=Db::name('users_currency')->insertGetId($detail);
                if(!$id){
                    Db::rollback();
                    $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
                }
            }
            $integral=$in_num*$integral_num;
            $re=Db::name('users')->where(['id'=>$user_id])->setDec('integral',$integral);
            if($re){
                $detail['u_id']=$user_id;
                $detail['u_name']=$user['nick_name'];
                $detail['integral']=-$integral;
                $detail['then_integral']=$user['integral'];
                $detail['createtime']=time();
                $id=Db::name('integral')->insertGetId($detail);
                if(!$id){
                    Db::rollback();
                    $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
                }
            }
            $balance=$ba_num*$balance_num;
        }elseif($in_num>$ba_num){

        }else{

        }
    }
    public function auction(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $currency=I('currency');
        if(!$currency||intval($currency)<0){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入正确的币数量！','data'=>'']);
        }
        $user=Db::name('users')->where(['id'=>$user_id])->find();
        if($user['currency']<$currency){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'币数量不足，请重新挂卖！','data'=>'']);
        }
        $currency_money=$this->set_value('currency');
        $balance=$currency_money*$currency;
        Db::startTrans();

        $res=Db::name('users')->where(['id'=>$user_id])->setDec('currency',$currency);
        if($res){
            $data['user_id']=$user_id;
            $data['currency_num']=$currency;
            $data['currency_money']=$currency_money;
            $data['status']=1;
            $data['all_money']=$balance;
            $data['user_balance']=$user['currency'];
            $data['add_time']=time();
            $ide=Db::name('auction')->insertGetId($data);
            $detail['user_id']=$user_id;
            $detail['user_name']=$user['nick_name'];
            $detail['currency']=-$currency;
            $detail['old_currency']=$user['currency'];
            $detail['desc']='挂卖';
            $detail['add_time']=time();
            $id=Db::name('users_currency')->insertGetId($detail);
            if(!$id||!$ide){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '挂卖失败！']);
            }
        }
        $re=Db::name('users')->where(['id'=>$user_id])->setInc('balance',$balance);
        if($re){
            $detail=[];
            $detail['user_id']=$user_id;
            $detail['type']=7;//挂卖
            $detail['money']=$balance;
            $detail['createtime']=time();
            $detail['intro']='挂卖币'.$currency.'个，获得'.$balance;
            $ids=Db::name('moneydetail')->insertGetId($detail);
            if(!$ids){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '挂卖失败！']);
            }
        }
        if(!$res||!$re){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '挂卖失败！']);
        }else{
            Db::commit();
            $this->ajaxReturn(['status' => 1, 'msg' => '挂卖成功！']);
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