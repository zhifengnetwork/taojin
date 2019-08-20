<?php
namespace app\api\controller;

use think\Db;
use Captcha;
use think\Loader;
use think\Request;
use think\Session;
use think\Page;
use app\common\logic\Message;
use app\common\model\UserMessage;
use app\common\logic\wechat\WechatUtil;
use app\common\logic\ShareLogic;
use app\common\logic\LoginLogic;

class User extends ApiBase
{

    /**
     * 个人资料
     */
    public function userinfo(){
        $user_id = $this->get_user_id();
        if(!empty($user_id)){
            $data = Db::name("users")
                ->field('id,phone,nick_name,avatar')
                ->where(['id' => $user_id ,'status'=>1])
                ->find();
            if(empty($data)){
                $this->ajaxReturn(['status' => -2 , 'msg'=>'会员不存在！','data'=>'']);
            }

            if(empty($data['phone'])){
                $this->ajaxReturn(['status' => -2 , 'msg'=>'未绑定手机！','data'=>$data]);
            }
        }else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$data]);

    }



    /**
     * 上传头像
     */
    public function updata_head_img()
    {
        $user_id = $this->get_user_id();
        if (!$user_id) {
            $this->ajaxReturn(['status' => -1, 'msg' => '用户不存在', 'data' => '']);
        }
        $head_img = input('head_img');

            if(empty($head_img)){
                $this->ajaxReturn(['code'=>0,'msg'=>'上传图片不能为空','data'=>'']);
            }
            $saveName       = request()->time().rand(0,99999) . '.png';
            $base64_string  = explode(',', $head_img);
            $imgs           = base64_decode($base64_string[1]);
            //生成文件夹
            $names = "public/head";
            $name  = "public/head/" .date('Ymd',time());
            if (!file_exists(ROOT_PATH .Config('c_pub.img').$names)){
                mkdir(ROOT_PATH .Config('c_pub.img').$names,0777,true);
            }
            //保存图片到本地
            $r   = file_put_contents(ROOT_PATH .Config('c_pub.img').$name.$saveName,$imgs);
            if(!$r){
                $this->ajaxReturn(['status'=>-2,'msg'=>'上传出错','data' =>'']);
            }
            Db::name('users')->where(['id' => $user_id])->update(['avatar' => SITE_URL.'/'.$name.$saveName]);

            $this->ajaxReturn(['status'=>1,'msg'=>'上传成功','data'=>SITE_URL.'/'.$name.$saveName]);

        }


    /**
     * 修改用户名
     */
    public function update_username()
    {
//        $user_id = $this->get_user_id();
        $user_id = 4;
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $username = input('username');
        $data = Db::name('users')->where('nick_name', $username)->find();
        if ($data){
            $this->ajaxReturn(['status' => -1, 'msg' => '此用户名太受欢迎,请更换一个']);
        }
            $res = Db::name('users')->where(['id'=>$user_id])->update(['nick_name' => $username]);
        if (!$res){
            $this->ajaxReturn(['status' => -1, 'msg' => '修改失败请重试']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '修改成功','data'=>'']);
    }


    /**
     * 修改密码
     */



    /**
     * 支付密码
     */

    function paypwd()
    {
//        $user_id = $this->get_user_id();
        $user_id = 4;
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $phone = input('phone');
        $code = input('code');
        $pwd = trim(input('pwd'));
        $pwd1 = trim(input('pwd1'));
        if (!checkMobile($phone)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机格式错误！']);
        }
        $data = Db::name('users')->field('phone')->where('id', $user_id)->find();;
        if ($phone != $data['phone']) {
            $this->ajaxReturn(['status' => -1, 'msg' => '手机号码不正确，请输入注册时的手机号']);
        }
        if (strlen($pwd) == 0) $this->ajaxReturn(['status' => -2, 'msg' => '密码不能为空！']);
        if (strlen($pwd) < 6) {
            $this->ajaxReturn(['status' => -2, 'msg' => '密码长度为6！']);
        }
        if (strlen($pwd1) == 0) $this->ajaxReturn(['status' => -2, 'msg' => '确认密码不能为空！']);
        if (strlen($pwd1) < 6) {
            $this->ajaxReturn(['status' => -2, 'msg' => '确认密码长度为6！']);
        }
        if ($pwd != $pwd1) {
            $this->ajaxReturn(['status' => -2, 'msg' => '两次密码不一致', 'data' => '']);

        }
        $loginLogic = new LoginLogic();
        $res = $loginLogic->phoneAuth($phone, $code);

        if ($res['status'] == -1 ) {
            $this->ajaxReturn(['status' => -1, 'msg' => $res['msg']]);
        }

        $code = trim(input('code/d'));
        if (!$code) {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码必填！']);
        }

        $paypwd = password_hash($pwd,PASSWORD_DEFAULT);
        $res = Db::name('users')->where(['id'=>$user_id])->update(['paypwd'=>$paypwd]);
        if (!$res) {
           $this->ajaxReturn(['status' => -2, 'msg' => '设置失败！']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '设置成功！']);
    }

}