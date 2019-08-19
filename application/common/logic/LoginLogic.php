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
        $res = Db::name('captcha')->where('phone',$phone)->order('id DESC')->find();
        if(!$res){
            return ['status' => -1, 'msg' => '请先获取验证码！'];
        }
        
        if ( time() > $res['expires'] ) { 
            return ['status' => -1, 'msg' => time().'验证码已过期！'.$res['expires'] ];
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