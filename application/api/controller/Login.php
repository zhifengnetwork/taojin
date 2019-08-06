<?php
namespace app\api\controller;

use think\Db;
use Captcha;
use think\Loader;
use think\Request;
use think\Session;

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
        $password1 = input('password');
        if(!$password1){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'password为空','data'=>null]);
        }
        $password  = password_hash($password1,PASSWORD_DEFAULT);

        $data = Db::name("users")->where('phone',$phone)
            ->field('password,id')
            ->find();

        if(!$data){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'手机phone不存在或错误','data'=>null]);
        }
        if (password_verify($password,$data['password'])) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'登录密码错误','data'=>null]);
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

        $res = action('Captcha/phoneAuth', [$phone, $code]);
        if ($res === '-1') {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码已过期！', 'data' => '']);
        } else if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码错误！', 'data' => '']);
        }

        $data['salt'] = create_salt();
        $data['password'] = md5($data['salt'] . $pwd);
        $data['phone'] = $phone;
        $data['add_time'] = time();
        $id = Db::name('users')->insertGetId($data);
        if (!$id) {
            $this->ajaxReturn(['status' => -2, 'msg' => '注册失败，请重试！', 'data' => '']);
        }
        $data_user['token'] = $this->create_token($id);
        $data_user['phone'] = $phone;
        $data_user['id'] = $id;
        $this->ajaxReturn(['status' => 1, 'msg' => '注册成功！', 'data' => $data_user]);

    }

}