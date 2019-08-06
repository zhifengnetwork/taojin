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
        $res = Db::name('captcha')->field('expires')->where('phone','=',$phone)->where('code',$code)->order('id DESC')->find();
        if(!$res){
            return ['status' => -1, 'msg' => '请先获取验证码！'];
        }
        
        if ($res['expires'] > time()) { 
            return ['status' => -1, 'msg' => '验证码已过期！'];
        }

        return ['status' => 1, 'msg' => '验证码验证通过！'];

    }
   
}