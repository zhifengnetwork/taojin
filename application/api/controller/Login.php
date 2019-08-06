<?php
namespace app\api\controller;

use think\Db;
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
   

}