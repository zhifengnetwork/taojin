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
//        $u_id=I('u_id');
        $balance=I('money');
        $phone=I('phone');
        $type=I('type',1);
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
//        if($balance<100){
//            $this->ajaxReturn(['status' => -2 , 'msg'=>'金额必须大于100']);
//        }


        $user=Db::name('users')->where(['id'=>$user_id])->find();
        $give_user=Db::name('users')->where(['phone'=>$phone])->find();
        if($user['phone']!=18899999999){
            if($this->verify($balance)){//判断是否为100的整数倍
                $this->ajaxReturn(['status' => -2, 'msg' => '金额必须是100的倍数！']);
            }
        }
        if($type==1){//转淘金
            if($give_user['id']==$user_id){
                $this->ajaxReturn(['status' => -2, 'msg' => '不能赠送给自己！']);
            }
        }
        $verify = password_verify($paypwd,$user['paypwd']);
        if ($verify == false) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'支付密码错误','data'=>null]);
        }
        if($type==1){
            $user_all_balance=$user['balance']+$user['recharge_balance'];
            if($user_all_balance<$balance){
                $this->ajaxReturn(['status' => -2, 'msg' => '您的余额不足，不能赠送！']);
            }
        }else{
            $user_all_balance=$user['balance'];
            if($user_all_balance<$balance){
                $this->ajaxReturn(['status' => -2, 'msg' => '您的金沙不足，不能转账！']);
            }
        }

        if(!$give_user){
            $this->ajaxReturn(['status' => -2, 'msg' => '赠送用户不存在，请输入正确的用户手机号！']);
        }
        Db::startTrans();
        if($type==1){
            if($user['phone']==18899999999){//管理员充值
                $res=Db::name('users')->where(['phone'=>$phone])->setInc('recharge_balance',$balance);
                if($res){
                    $detail['user_id']=$give_user['id'];
                    $detail['type']=13;//充值 被赠与
                    $detail['be_user_id']=$user_id;//赠送者
                    $detail['money']=$balance;
                    $detail['createtime']=time();
                    $detail['intro']=$user['phone'].'转入';
                    $id=Db::name('moneydetail')->insertGetId($detail);//用户之间交易，无需处理
                    if(!$id){
                        Db::rollback();
                        $this->ajaxReturn(['status' => -2, 'msg' => '充值失败！']);
                    }
                }

                $re=Db::name('users')->where(['id'=>$user_id])->setDec('balance',$balance);
                if($re){
                    $detail=[];
                    $detail['user_id']=$user_id;
                    $detail['type']=13;//充值  赠送
                    $detail['be_user_id']=$give_user['id'];//赠与者
                    $detail['money']=-$balance;
                    $detail['createtime']=time();
                    $detail['intro']='转入'.$give_user['phone'];
                    $ids=Db::name('moneydetail')->insertGetId($detail);//用户之间交易，无需处理
                    if(!$ids){
                        Db::rollback();
                        $this->ajaxReturn(['status' => -2, 'msg' => '充值失败！']);
                    }
                }
                $system_money=Db::name('system_money')->where('id',1)->find();//总后台系统总额
                $old_balance=$system_money['balance'];
//            $system_money['balance']=sprintf("%.2f",$system_money['balance']-$balance);
                $system_money['balance']=$system_money['balance']-$balance;
                $system_data['balance']=-$balance;
                $system_data['old_balance']=$old_balance;
                $system_data['new_balance']=$system_money['balance'];
                $system_data['add_time']=time();
                $system_data['desc']='总账户充值修改系统金额';
                $sys_id=Db::name('system_money_log')->insertGetId($system_data);
                if(!$sys_id){
                    return false;
                }
                $r=Db::name('system_money')->update($system_money);
                if(!$res||!$re||!$r){
                    Db::rollback();
                    $this->ajaxReturn(['status' => -2, 'msg' => '充值失败！']);
                }else{
                    Db::commit();
                    $this->ajaxReturn(['status' => 1, 'msg' => '充值成功！']);
                }
            }else{
                if($user['recharge_balance']>$balance){
                    $user_balance['recharge_balance']=$user['recharge_balance']-$balance;//充值余额足够
                }else{
                    $user_balance['recharge_balance']=0;
                    $user_balance['balance']=$user['balance']+$user['recharge_balance']-$balance;//充值余额不足
                }
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
                $re=Db::name('users')->where(['id'=>$user_id])->update($user_balance);
//            $re=Db::name('users')->where(['id'=>$user_id])->setDec('balance',$balance);
                if($re){
                    $detail=[];
                    $detail['user_id']=$user_id;
                    $detail['type']=2;//赠送
                    $detail['be_user_id']=$give_user['id'];//赠与者
                    $detail['money']=-$balance;
                    $detail['createtime']=time();
                    $detail['intro']='赠送给'.$give_user['phone'];
                    $ids=Db::name('moneydetail')->insertGetId($detail);//用户之间交易，无需处理
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
        }else{
            $res=Db::name('users')->where(['phone'=>$phone])->setInc('chicken_balance',$balance);
            if($res){
                $data['user_id']=$give_user['id'];
                $data['to_user_id']=$user_id;
                $data['type']=2;
                $data['money']=$balance;
                $data['desc']=$user['phone'].'赠送';
                $data['add_time']=time();
                $id=Db::name('chicken_balance_log')->insertGetId($data);
                if(!$id){
                    Db::rollback();
                    $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
                }
            }
            $re=Db::name('users')->where(['id'=>$user_id])->setDec('balance',$balance);
//            $re=Db::name('users')->where(['id'=>$user_id])->setDec('balance',$balance);
            if($re){
                $detail=[];
                $detail['user_id']=$user_id;
                $detail['type']=2;//赠送
                $detail['be_user_id']=$give_user['id'];//赠与者
                $detail['money']=-$balance;
                $detail['createtime']=time();
                $detail['intro']='赠送给'.$give_user['phone'];
                $ids=Db::name('moneydetail')->insertGetId($detail);//用户之间交易，无需处理
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
    /**
     * 鸡笼金沙
     * @param $user_id  用户id
     * @param $to_user_id  赠送用户id
     * @param $type    类型
     * @param $money   改变金沙
     * @param $balance  原有金沙
     * @param $desc  说明描述
     * @return boolean
     */
    public function chicken_balance_log($user_id,$to_user_id,$type,$money,$balance,$desc){
        $data['user_id']=$user_id;
        $data['to_user_id']=$to_user_id;
        $data['type']=$type;
        $data['money']=-$money;
        $data['balance']=$balance;
        $data['desc']=$desc;
        $data['add_time']=time();
        $id=Db::name('chicken_balance_log')->insertGetId($data);
        if($id){
            return true;
        }else{
            return false;
        }
    }
    public function text(){
        $phone=I('phone');
        $mb=$phone;
        $num=strlen($phone);
        echo $phone.' ==== '.$num;
        var_dump($mb);
        if($num>11){
            $phone=substr($phone,3,11);
        }
        $give_user=Db::name('users')->where(['phone'=>$phone])->find();
        var_dump($give_user);
        die;
    }
    public function give_integral(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $integral=I('integral');
        $phone=I('phone');
        $paypwd=I('paypwd');
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
        if($give_user['id']==$user_id){
            $this->ajaxReturn(['status' => -2, 'msg' => '不能赠送给自己！']);
        }
        $verify = password_verify($paypwd,$user['paypwd']);
        if ($verify == false) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'支付密码错误','data'=>null]);
        }
        if($user['integral']<$integral){
            $this->ajaxReturn(['status' => -2, 'msg' => '您的糖果不足，不能赠送！']);
        }
        if(!$give_user){
            $this->ajaxReturn(['status' => -2, 'msg' => '赠送用户不存在，请输入正确的用户id！']);
        }
        Db::startTrans();
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
        $re=Db::name('users')->where(['id'=>$user_id])->setDec('integral',$integral);
        if($re){
            $detail=[];
            $detail['u_id']=$user_id;
            $detail['u_name']=$user['nick_name'];
            $detail['for_user_id']=$give_user['id'];//被赠送者
            $detail['for_user_name']=$give_user['nick_name'];//被赠送者
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
        $currency=I('currency');
        $phone=I('phone');
        $paypwd=I('paypwd');
        if(!$paypwd){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入支付密码']);
        }
        if(!$phone){
            $this->ajaxReturn(['status' => -2, 'msg' => '手机号不能为空！']);
        }
        if(!$currency){
            $this->ajaxReturn(['status' => -2, 'msg' => 'currency不能为空！']);
        }
        if($currency<1){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入正确的币']);
        }
        if($this->verify($currency)){//判断是否为100的整数倍
            $this->ajaxReturn(['status' => -2, 'msg' => '币数量必须是100的倍数！']);
        }
        $user=Db::name('users')->where(['id'=>$user_id])->find();
        $give_user=Db::name('users')->where(['phone'=>$phone])->find();
        if($give_user['id']==$user_id){
            $this->ajaxReturn(['status' => -2, 'msg' => '不能赠送给自己！']);
        }
        $verify = password_verify($paypwd,$user['paypwd']);
        if ($verify == false) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'支付密码错误','data'=>null]);
        }
        if($user['currency']<$currency){
            $this->ajaxReturn(['status' => -2, 'msg' => '您的币不足，不能赠送！']);
        }
        if(!$give_user){
            $this->ajaxReturn(['status' => -2, 'msg' => '赠送用户不存在，请输入正确的用户id！']);
        }
        Db::startTrans();
        if($user['phone']==18899999999){
            $res=Db::name('users')->where(['phone'=>$phone])->setInc('lock_currency',$currency);
            if($res){
                $detail['user_id']=$give_user['id'];
                $detail['user_name']=$give_user['nick_name'];
                $detail['type']=4;//管理员操作
                $detail['currency_type']=1;//冻结
                $detail['for_user_id']=$user_id;//赠送者
                $detail['for_user_name']=$user['nick_name'];//赠送者
                $detail['currency']=$currency;
                $detail['old_currency']=$give_user['lock_currency'];
                $detail['desc']=$user['nick_name'].'充值';
                $detail['add_time']=time();
                $id=Db::name('users_currency')->insertGetId($detail);
                if(!$id){
                    Db::rollback();
                    $this->ajaxReturn(['status' => -2, 'msg' => '充值失败！']);
                }
            }
            $re=Db::name('users')->where(['id'=>$user_id])->setDec('currency',$currency);
            if($re){
                $detail=[];
                $detail['user_id']=$user_id;
                $detail['user_name']=$user['nick_name'];
                $detail['type']=1;//赠送
                $detail['for_user_id']=$give_user['id'];//被赠送者
                $detail['for_user_name']=$give_user['nick_name'];//被赠送者
                $detail['currency']=-$currency;
                $detail['old_currency']=$user['currency'];
                $detail['desc']='充值'.$give_user['nick_name'];
                $detail['add_time']=time();
                $ids=Db::name('users_currency')->insertGetId($detail);
                if(!$ids){
                    Db::rollback();
                    $this->ajaxReturn(['status' => -2, 'msg' => '充值失败！']);
                }
            }
            if(!$res||!$re){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '充值失败！']);
            }else{
                Db::commit();
                $this->ajaxReturn(['status' => 1, 'msg' => '充值成功！']);
            }
        }else{
            $res=Db::name('users')->where(['phone'=>$phone])->setInc('currency',$currency);
            if($res){
                $detail['user_id']=$give_user['id'];
                $detail['user_name']=$give_user['nick_name'];
                $detail['type']=3;//被赠与
                $detail['for_user_id']=$user_id;//赠送者
                $detail['for_user_name']=$user['nick_name'];//赠送者
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
                $detail['for_user_id']=$give_user['id'];//被赠送者
                $detail['for_user_name']=$give_user['nick_name'];//被赠送者
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

    }
    public function exchange_currency(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $currency=I('currency');
        $paypwd=I('paypwd');
        if(!$paypwd){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入支付密码']);
        }
        if(!$currency||intval($currency)<0||ceil($currency)!=$currency){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入正确的兑换币数量！','data'=>'']);
        }
        $system_money=Db::name('system_money')->where('id',1)->find();
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
        $verify = password_verify($paypwd,$user['paypwd']);
        if ($verify == false) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'支付密码错误','data'=>null]);
        }
        Db::startTrans();
        $res=Db::name('users')->where(['id'=>$user_id])->setInc('currency',$currency);
        $system_money['currency']=$system_money['currency']-$currency;//释放币
        if($system_money['currency']<0){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '系统币不足，请联系管理员！']);
        }
        if($res){
            $detail['user_id']=$user_id;
            $detail['user_name']=$user['nick_name'];
            $detail['currency']=$currency;
            $detail['type']=5;//兑换币
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
        $system_money['integral']=$system_money['integral']+$integral;//回收糖果
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
        $system_money['balance']=$system_money['balance']+$balance;//回收金沙
        if($r){
            $detail=[];
            $detail['user_id']=$user_id;
            $detail['type']=6;//兑换
            $detail['money']=-$balance;
            $detail['createtime']=time();
            $detail['intro']='兑换币'.$currency.'个';
            $ids=Db::name('moneydetail')->insertGetId($detail);//兑换log
            if(!$ids){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '兑换失败！']);
            }
        }
        $system_data['currency']=-$currency;
        $system_data['integral']=$integral;
        $system_data['balance']=$balance;
        $system_data['new_currency']=$system_money['currency'];
        $system_data['new_integral']=$system_money['integral'];
        $system_data['new_balance']=$system_money['balance'];
        $system_data['add_time']=time();
        $system_data['desc']='兑换修改系统金额';
        $sys_id=Db::name('system_money_log')->insertGetId($system_data);
        if(!$sys_id){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '兑换失败,生成系统log出错！']);
        }
        $res_s=Db::name('system_money')->update($system_money);//修改
        if(!$res||!$re||!$r||!$res_s){
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
        if(!$currency||intval($currency)<0||ceil($currency)!=$currency){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入正确的币数量！','data'=>'']);
        }
        $paypwd=I('paypwd');
        if(!$paypwd){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入支付密码']);
        }
        $user=Db::name('users')->where(['id'=>$user_id])->find();
        $system_money=Db::name('system_money')->where('id',1)->find();
        if($user['currency']<$currency){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'币数量不足，请重新挂卖！','data'=>'']);
        }
        $verify = password_verify($paypwd,$user['paypwd']);
        if ($verify == false) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'支付密码错误','data'=>null]);
        }
        $currency_money=$this->set_value('currency');
        $balance=$currency_money*$currency;
        Db::startTrans();

        $res=Db::name('users')->where(['id'=>$user_id])->setDec('currency',$currency);
        $system_money['currency']=$system_money['currency']+$currency;
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
        $user_money=$user['balance']+$balance;
//        $re=Db::name('users')->where(['id'=>$user_id])->setInc('balance',$balance);
        $re=Db::name('users')->where(['id'=>$user_id])->update(['balance'=>$user_money]);
        $system_money['balance']=sprintf("%.2f",$system_money['balance']-$balance);
        if($system_money['balance']<0){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '系统金沙不足，请联系管理员！']);
        }
        if($re){
            $detail=[];
            $detail['user_id']=$user_id;
            $detail['type']=7;//挂卖
            $detail['money']=$balance;
            $detail['createtime']=time();
            $detail['intro']='挂卖币'.$currency.'个，获得'.$balance;
            $ids=Db::name('moneydetail')->insertGetId($detail);//挂卖log
            if(!$ids){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '挂卖失败！']);
            }
        }
        $system_data['currency']=$currency;
        $system_data['balance']=-$balance;
        $system_data['new_currency']=$system_money['currency'];
        $system_data['new_balance']=$system_money['balance'];
        $system_data['add_time']=time();
        $system_data['desc']='挂卖修改系统金额';
        $sys_id=Db::name('system_money_log')->insertGetId($system_data);
        if(!$sys_id){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '挂卖失败,生成系统log出错！']);
        }
        $r=Db::name('system_money')->update($system_money);//修改
        if(!$res||!$re||!$r){
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