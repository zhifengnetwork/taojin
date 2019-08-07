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

        $data = Db::name("users")->where('phone',$phone)
            ->field('password,id')
            ->find();

        if(!$data){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'手机phone不存在或错误','data'=>null]);
        }
        if (password_verify($password,$data['password'])) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'登录密码错误'.$password,'data'=>$data['password']]);
        }
        unset($data['password']);
        //重写
        $data['token'] = $this->create_token($data['user_id']);
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
        if (!$pwd || !$pwd2) {
            $this->ajaxReturn(['status' => -2, 'msg' => '密码不能为空！']);
        }
        if ($pwd != $pwd2) {
            $this->ajaxReturn(['status' => -2, 'msg' => '两次密码输入不一样！请重新输入！']);
        }
        if (!checkMobile($phone)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机格式错误！']);
        }
        $data = Db::name('users')->where('phone', $phone)->find();
        if ($data) {
            $this->ajaxReturn(['status' => -1, 'msg' => '此手机号已注册，请直接登录！']);
        }

        $loginLogic = new LoginLogic();
        $res = $loginLogic->phoneAuth($phone, $code);

        if ($res['status'] == -1 ) {
            $this->ajaxReturn(['status' => -1, 'msg' => $res['msg']]);
        }

        $data['password'] = password_hash($pwd,PASSWORD_DEFAULT);
        $data['phone'] = $phone;
        $data['add_time'] = time();
        $id = Db::name('users')->insertGetId($data);
        if (!$id) {
            $this->ajaxReturn(['status' => -2, 'msg' => '注册失败，请重试！', 'data' => '']);
        }
        $data_user['token'] = $this->create_token($id);
        $data_user['id'] = $id;
        $this->ajaxReturn(['status' => 1, 'msg' => '注册成功！', 'data' => $data_user]);

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


    public function test(){

    }

}