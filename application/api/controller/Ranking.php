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
        $num=I('num',1);
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
     * 赠送余额
     */
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
        $is_int=$balance/100;
        if(ceil($is_int)!=$is_int){
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
            $detail['type']=2;//被赠送
            $detail['money']=$balance;
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
}
