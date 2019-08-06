<?php
namespace app\api\controller;
use think\Db;

class Captcha extends ApiBase
{
    public function get_code()
    {

        $phone = trim(input('phone'));

        if( !$phone ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'phone参数为空！']);
        }


        $member = Db::table(config('database.prefix').'users')->where('phone',$phone)->find();
        if($member){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'此手机号已注册，请直接登录！']);
        }
        $phone_number = checkMobile($phone);
        if ($phone_number == false) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'手机号码格式不对！']);
        }

        $res = M('captcha')->field('expires')->where('phone','=',$phone)->order('id DESC')->find();
        $time=$res['expires']-time();
        if( $res['expires'] > time() ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请'.$time.'秒后再重试！']);
        }

        $code = mt_rand(111111,999999);

        $data['phone'] = $phone;
        $data['code'] = $code;
        $data['add_time'] = time();
        $data['expires'] = time() + 60;

        $res = M('captcha')->insert($data);
        if(!$res){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'发送失败，请重试！']);
        }

        $ret = send_zhangjun($phone, $code);
        if($ret['message'] == 'ok'){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'发送成功！']);
        }
        $this->ajaxReturn(['status' => -2 , 'msg'=>'发送失败，请重试！']);
    }

    public function phoneAuth($phone, $code)
    {
        $res = Db::name('captcha')->field('expires')->where('phone','=',$phone)->where('code',$code)->order('id DESC')->find();

        if ($res) {
            if ($res['expires'] >= time()) { // 还在有效期就可以验证
                return true;
            } else {
                return '-1';
            }
        }
        return false;
    }

}
