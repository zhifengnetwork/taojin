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
            $detail['user_id']=$u_id;
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
            $this->ajaxReturn(['status' => -2, 'msg' => '金沙必须是100的倍数！']);
        }
        $user=Db::name('users')->where(['id'=>$user_id])->find();
        $give_user=Db::name('users')->where(['id'=>$u_id])->find();
        if($user['integral']<$integral){
            $this->ajaxReturn(['status' => -2, 'msg' => '您的金沙不足，不能赠送！']);
        }
        if(!$give_user){
            $this->ajaxReturn(['status' => -2, 'msg' => '赠送用户不存在，请输入正确的用户id！']);
        }
        Db::startTrans();
        $res=Db::name('users')->where(['id'=>$u_id])->setInc('integral',$integral);
        if($res){
            $detail['u_id']=$u_id;
            $detail['u_name']=$user['nick_name'];
            $detail['integral']=$integral;
            $detail['then_integral']=$user['integral'];
            $detail['createtime']=time();
            $id=Db::name('integral')->insertGetId($detail);
            if(!$id){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
            }
        }
        $re=Db::name('users')->where(['id'=>$user_id])->setDec('balance',$balance);
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
            $this->ajaxReturn(['status' => -2, 'msg' => '金沙必须是100的倍数！']);
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
            $detail['user_name']=$user['nick_name'];
            $detail['currency']=$currency;
            $detail['old_currency']=$user['currency'];
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
            $detail['currency']=-$currency;
            $detail['old_currency']=$user['currency'];
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
    public function verify($money){
        $is_int=$money/100;
        if(ceil($is_int)!=$is_int){
            return true;
        }else{
            return false;
        }
    }
}