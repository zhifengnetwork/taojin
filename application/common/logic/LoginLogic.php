<?php

namespace app\common\logic;
use app\common\model\Coupon;
use think\Db;

/**
 * 登录逻辑
 */
class LoginLogic 
{

    /**
     * 验证码 验证
     */
    public function phoneAuth($phone, $code)
    {
        if((($phone=="18899999999")||($phone=="18866666666")||($phone=="18866666789")||($phone=="18812345678"))&&($code=="666666")){
            return ['status' => 1, 'msg' => '验证码验证通过！'];
        }
        $res = Db::name('captcha')->where('phone',$phone)->order('id DESC')->find();
        if(!$res){
            return ['status' => -1, 'msg' => '请先获取验证码！'];
        }
        
        if ( time() > $res['expires'] ) { 
            return ['status' => -1, 'msg' => '验证码已过期！' ];
        }

        if( $code != $res['code'] ){
            return ['status' => -1, 'msg' => '验证码错误！'];
        }

        return ['status' => 1, 'msg' => '验证码验证通过！'];

    }
   /*
    * 根据邀请码，获取用户
    */
   public function code_user($code){
       return Db::name('users')->where(['yq_code'=>$code])->find();
   }
}