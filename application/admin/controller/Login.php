<?php
namespace app\admin\controller;
use think\Controller;
use app\common\model\Admin;

class Login extends Controller
{

    public function _initialize(){
      
    }

  
    public function index(){
       
        if(request()->isPost()) {

            $admin = new Admin();
            $data = input('post.');
            // if(!$this->check($data['captcha'])){
            //     return json(array('code' => 0, 'msg' => '验证码错误'));
            // }
            $num = $admin->login($data);
            if($num == 1){
                return json(['code' => 1, 'msg' => '登录成功!', 'url' => url('index/index')]);
            }elseif($num == 2){
                return json(['code' => 2, 'msg' => '该用户被禁用!', 'url' => url('index/index')]);
            }else {
                return json(array('code' => 0, 'msg' => '用户名或者密码错误，重新输入!'));
            }

        }else{

            // $this->cache_model=array('Module','Role','Category','Posid','Field','System');
            // $this->system = F('System');
            // if(empty($this->system)){
            //     foreach($this->cache_model as $r){
            //         savecache($r);
            //     }
            // }
            return $this->fetch();
        }


        return $this->fetch();
    }


    
}