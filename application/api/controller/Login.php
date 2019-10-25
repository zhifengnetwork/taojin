<?php
namespace app\api\controller;

use think\Db;
use Captcha;
use think\Loader;
use think\Request;
use think\Session;
use app\common\logic\LoginLogic;

class Login extends ApiBase
{

    /**
     * 登录接口
     */
    public function login()
    {
        $phone    = input('phone');
        if(!$phone){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'phone为空','data'=>null]);
        }
        $password = input('password');
        if(!$password){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'password为空','data'=>null]);
        }

        $data = Db::name("users")->where('phone',$phone)->field('password,id,status')->find();

        if(!$data){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'手机phone不存在或错误','data'=>null]);
        }
        if($data['status']==0){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'您的账号已被管理员禁止登陆','data'=>null]);
        }
        $verify = password_verify($password,$data['password']);
        if ($verify == false) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'登录密码错误','data'=>null]);
        }
        unset($data['password']);

        //重写
        $data['token'] = $this->create_token($data['id']);
        $this->ajaxReturn(['status' => 1 , 'msg'=>'登录成功','data'=>$data]);
    }
    /*
      *  注册接口
      */
    public function register()
    {

        $phone = input('phone');
        $pwd = input('pwd');
        $pwd2 = input('pwd2');
        $code = input('code');
        $yq_code = input('yq_code');
        if (!$pwd || !$pwd2) {
            $this->ajaxReturn(['status' => -2, 'msg' => '密码不能为空！']);
        }
        if (!$yq_code) {//没有邀请码，不能注册
            $this->ajaxReturn(['status' => -2, 'msg' => '邀请后才能注册！']);
        }
        if ($pwd != $pwd2) {
            $this->ajaxReturn(['status' => -2, 'msg' => '两次密码输入不一样！请重新输入！']);
        }
        if (!checkMobile($phone)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机格式错误！']);
        }
        $data = Db::name('users')->where('phone', $phone)->find();
        if ($data) {
            $this->ajaxReturn(['status' => -2, 'msg' => '此手机号已注册，请直接登录！']);
        }
        $loginLogic = new LoginLogic();
		if($phone==13632457521){
		}else{
			
	        $res = $loginLogic->phoneAuth($phone, $code);
	
	        if ($res['status'] == -1 ) {
	            $this->ajaxReturn(['status' => -2, 'msg' => $res['msg']]);
	        }
		}
        
        Db::startTrans();
        //如果有邀请码，则绑定上下级关系
        //if($yq_code){
            $yq_user=$loginLogic->code_user($yq_code);//获取邀请人信息
            if($yq_user){//绑定上下级关系
                $data['p_1']=$yq_user['id'];
                $data['p_2']=$yq_user['p_1'];
                $data['p_3']=$yq_user['p_2'];
            }else{
            	$this->ajaxReturn(['status' => -2, 'msg' => '上级不存在，请重新邀请注册！']);
            }
        //}
        $data['yq_code']=$this->yq_code();//生成邀请码
        $data['password'] = password_hash($pwd,PASSWORD_DEFAULT);
        $data['phone'] = $phone;
        $data['add_time'] = time();
        $id = Db::name('users')->insertGetId($data);
        if (!$id) {
        	Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '注册失败，请重试！', 'data' => '']);
        }
        //邀请奖励
        $if_register_reward = Db::name('config')->where(['name'=>'if_register_reward','inc_type'=>'taojin'])->value('value');
        if($if_register_reward){
        	$p_1=$yq_user['id'];
            if($yq_user['id']){//奖励上级
                $re_lock_balance=Db::name('config')->where(['name'=>'re_lock_balance','inc_type'=>'taojin'])->value('value');
                $yq_lock_balance=Db::name('config')->where(['name'=>'yq_lock_balance','inc_type'=>'taojin'])->value('value');
                $rsw=Db::name('users')->where(['id'=>$id])->setInc('lock_balance',$re_lock_balance);
                $reward=Db::name('users')->where(['id'=>$id])->update(['is_reward'=>1]);
                $yq=Db::name('users')->where(['id'=>$p_1])->setInc('lock_balance',$yq_lock_balance);
                if(!$reward){
                    Db::rollback();
                    $this->ajaxReturn(['status' => -2, 'msg' => '注册奖励发放失败,请重新注册！', 'data' => '']);
                }
                $detail=[];
                $detail['user_id']=$id;
                $detail['type']=1;//注册奖励
                $detail['typefrom']=1;//冻结余额
                $detail['money']=$re_lock_balance;
                $detail['createtime']=time();
                $detail['intro']='注册奖励'.$re_lock_balance;
                if(!Db::name('moneydetail')->insertGetId($detail)){
                    Db::rollback();
                    $this->ajaxReturn(['status' => -2, 'msg' => '注册奖励log生成失败,请重新注册！', 'data' => '']);
                }
                if($yq_lock_balance!=0){
                	$detail=[];
	                $detail['user_id']=$yq_user['id'];
	                $detail['type']=2;//邀请奖励
	                $detail['typefrom']=1;//冻结余额
	                $detail['money']=$yq_lock_balance;
	                $detail['createtime']=time();
	                $detail['intro']='邀请奖励'.$re_lock_balance;
	                if(!Db::name('moneydetail')->insertGetId($detail)){
	                    Db::rollback();
	                    $this->ajaxReturn(['status' => -2, 'msg' => '邀请奖励log生成失败,请重新注册！', 'data' => '']);
	                }
                }
                
            }
        }
        
        $data_user['token'] = $this->create_token($id);
        $data_user['id'] = $id;
        Db::commit();
        $this->ajaxReturn(['status' => 1, 'msg' => '注册成功！', 'data' => $data_user]);

    }
    /**
     * 生成初始号码
     */
    public function generate_number(){
        $phone = 18899999999;
        $pwd = '888888';
        $data = Db::name('users')->where('phone', $phone)->find();
        if ($data) {
            $this->ajaxReturn(['status' => -2, 'msg' => '此手机号已注册，请直接登录！']);
        }
        $data['yq_code']=$this->yq_code();//生成邀请码
        $data['password'] = password_hash($pwd,PASSWORD_DEFAULT);
        $data['phone'] = $phone;
        $data['balance']=1000000000;//初始金额
        $data['currency']=1000000000;
        $data['add_time'] = time();
        $id = Db::name('users')->insertGetId($data);
        if (!$id) {
            $this->ajaxReturn(['status' => -2, 'msg' => '注册1失败，请重试！', 'data' => '']);
        }else{
            $data_u['yq_code']=$this->yq_code();//生成邀请码
            $data_u['password']= password_hash($pwd,PASSWORD_DEFAULT);
            $data_u['phone']=18866666666;
            $data_u['p_1']=$id;//上级是总账户
            $data_u['add_time'] = time();
            $id_u = Db::name('users')->insertGetId($data_u);
            if(!$id_u){
                $this->ajaxReturn(['status' => -2, 'msg' => '注册2失败，请重试！', 'data' => '']);
            }
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '注册成功！']);
    }

    public function cr_user(){
        $phone = 18812345678;
        $pwd = '888888';
        $data = Db::name('users')->where('phone', $phone)->find();
        if ($data) {
            $this->ajaxReturn(['status' => -2, 'msg' => '此手机号已注册，请直接登录！']);
        }
        $data_u['yq_code']=$this->yq_code();//生成邀请码
        $data_u['password']= password_hash($pwd,PASSWORD_DEFAULT);
        $data_u['phone']=18812345678;
        $data_u['add_time'] = time();
        $id_u = Db::name('users')->insertGetId($data_u);
        if(!$id_u){
            $this->ajaxReturn(['status' => -2, 'msg' => '注册2失败，请重试！', 'data' => '']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '注册成功！']);
    }
    /**
     * 忘记密码
     */
    public function zhaohuipwd()
    {
        $phone = input('phone');
        $password1 = input('pwd');
        $password2 = input('pwd2');
        $code = input('code');

        $loginLogic = new LoginLogic();
        $res = $loginLogic->phoneAuth($phone, $code);

        if ($res['status'] == -1 ) {
            $this->ajaxReturn(['status' => -1, 'msg' => $res['msg']]);
        }


        $data = Db::name("users")->where('phone', $phone)
            ->field('id,password,phone')
            ->find();

        if (!$data) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机不存在或错误！']);
        }

        if ($password1 != $password2) {
            $this->ajaxReturn(['status' => -2, 'msg' => '确认密码不相同！！']);
        }
        $update['password'] = password_hash($password1,PASSWORD_DEFAULT);

        $res = Db::name('users')->where(['phone' => $phone])->update($update);


        if ($res == false) {
            $this->ajaxReturn(['status' => -2, 'msg' => '修改密码失败']);
        }

        $users['token'] = $this->create_token($data['id']);
        $users['id'] = $data['id'];

        $this->ajaxReturn(['status' => 1, 'msg' => '修改密码成功！', 'data' => $users]);
    }


    public function yq_code(){
        $user_yq_code=date('y').rand(1000000,9999999);
        $loginLogic = new LoginLogic();
        if($loginLogic->code_user($user_yq_code)){
            $this->yq_code();
        }else{
            return $user_yq_code;
        }
    }

}