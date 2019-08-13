<?php
namespace app\api\controller;

use app\common\model\Withdraw;
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
     * 修改密码
     */
    public function reset_pwd()
    {//重置密码
        $user_id = $this->get_user_id();
        if (!$user_id) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在', 'data' => '']);
        }
        $password1 = input('password1');
        $password2 = input('password2');
        if ($password1 != $password2) {
            $this->ajaxReturn(['status' => -2, 'msg' => '确认密码错误', 'data' => '']);
        }
        $member = Db::name('users')->where(['id' => $user_id])->field('id,password,pwd,mobile')->find();
        $type = input('type',1);//1登录密码 2支付密码
        $code = input('code');
        $mobile = $member['mobile'];
        $res = action('PhoneAuth/phoneAuth', [$mobile, $code]);
        if ($res === '-1') {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码已过期！', 'data' => '']);
        } else if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码错误！', 'data' => '']);
        }
        if ($type == 1) {
            $stri = 'password';
        } else {
            $stri = 'pwd';
        }
        $password = password_hash($password2,PASSWORD_DEFAULT);
        if ($password == $member[$stri]) {
            $this->ajaxReturn(['status' => -2, 'msg' => '新密码和旧密码不能相同']);
        } else {
            $data = array($stri => $password);
            $update = Db::name('member')->where('id', $user_id)->data($data)->update();
            if ($update) {
                $this->ajaxReturn(['status' => 1, 'msg' => '修改成功']);
            } else {
                $this->ajaxReturn(['status' => -2, 'msg' => '修改失败']);
            }
        }

    }


    /**
     * 支付密码
     */

    public function paypwd()
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

    //提现明细
    public function withdraw_list()
    {
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在']);
        }
        $user = \app\common\model\Users::get($user_id);
        if(!$user){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在']);
        }
        $page = input('page/d',1);
        $log = M('withdraw')
            ->where(['user_id' => $user_id])
            ->order('id desc')
            ->paginate(array('list_rows' => 20,'page' => $page));
        $res = [];
        foreach ($log as $v) {
            $res [] = [
                'id' => $v['id'],
                'money' => $v['money'],
                'fee' => $v['fee'],
                'status' => $v['status'],
                'status_text' => Withdraw::getStatusTextBy($v['status']) ?: '',
                'create_time' => toDate($v['create_time'], 'Ymd'),
            ];
        }
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => $res
        ]);
    }

    /***
     * 申请提现页面
     * 2微信 3银行卡 4支付宝
     */
    public function withdrawal()
    {
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在']);
        }
        $user = \app\common\model\Users::get($user_id);
        if(!$user){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在']);
        }
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => [
                'money' => $user->balance,
                'rate_percent' => Withdraw::getWDRate(),
                'rate_decimals' => Withdraw::getWDRate('decimals'),
                'alipay'=>[
                    'number'=>$user->ali_account,
                    'name'=>$user->name
                ],
                'card'=>Db::name('card')->field('id,bank,name,number')->where(['user_id'=>$user_id,'status'=>1])->select()
            ]
        ]);
    }

    /***
     * 申请提现提交
     * s3银行卡 4支付宝
     */
    public function withdraw()
    {
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在']);
        }
        $user = \app\common\model\Users::get($user_id);
        if(!$user){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在']);
        }
        $type = input('post.type/d', 0);
        if (!in_array($type, [1, 2])) {
            $this->ajaxReturn(['status' => -2, 'msg' => '提现方式选择错误！']);
        }
        if ($type == 1 && (!$user->ali_account || !$user->name)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请先绑定支付宝账号！']);
        }
        $card_id = input('post.card_id/d', 0);
        if ($type == 2 && (!$card_id || !($card = Db::name('card')->where(['id' => $card_id, 'user_id' => $user_id, 'status' => 1])->find()))) {
            $this->ajaxReturn(['status' => -2, 'msg' => '银行卡信息不存在！']);
        }

        $money = input('post.money', 0);
        if($money<0.01){
            $this->ajaxReturn(['status' => -2, 'msg' => '金额不能小于0.01！']);
        }

        $yu = bcsub($user->balance,$money);
        if ($yu < 0) {
            $this->ajaxReturn(['status' => -2, 'msg' => '超过可提现金额！']);
        }

        //提现申请
        $rate = Withdraw::getWDRate('decimals');
        $taxfee = bcmul($money, $rate, 2);//向下取整
        $withdraw_id = Db::name('withdraw')->insertGetId([
            'user_id' => $user_id,
            'type' => $type,
            'info' => $type == 2?$card_id:$user->ali_account,
            'rate' => $rate,
            'fee' => $taxfee,
            'money' => $money,
            'status' => 0,
            'create_time' => time(),
        ]);
        if (!$withdraw_id) {
            $this->ajaxReturn(['status' => -2, 'msg' => '申请失败,请稍后再试！']);
        }

        $balance = $user->balance;
        $res = $user->save(['balance' => $yu]);
        if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '申请失败,请稍后再试！']);
        }

        $res = Db::name('moneydetail')->insert([
            'user_id' => $user_id,
            'type' => 4,
            'money' => $money,
            'balance' => $balance,
            'createtime' => time(),
            'intro' => '申请提现'
        ]);
        if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '申请失败,请稍后再试！']);
        }

        $this->ajaxReturn(['status' => 1, 'msg' => '申请成功,正在审核中！']);
    }

    // 绑定支付宝
    function bind_alipay()
    {
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在']);
        }
        $user = \app\common\model\Users::get($user_id);
        if(!$user){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在']);
        }
        $alipay_name = input('post.name');
        $alipay_number = input('post.number');
        if (empty($alipay_name) || strlen($alipay_name) < 2 || strlen($alipay_name) > 20) {
            $this->ajaxReturn(['status' => -2, 'msg' => '支付宝真实姓名有误！']);
        }

        if (empty($alipay_number) || strlen($alipay_number) < 8 || strlen($alipay_number) > 30) {
            $this->ajaxReturn(['status' => -2, 'msg' => '支付宝账号不正确！']);
        }

        $res = Db::name('users')->where(['id' => $user_id])->update(['ali_account' => $alipay_number, 'name' => $alipay_name]);
        if ($res !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '操作失败']);
    }

    // 绑定银行卡
    function bind_card()
    {
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在']);
        }
        $user = \app\common\model\Users::get($user_id);
        if(!$user){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在']);
        }
        $bank = input('post.bank', '');
        $name = input('post.name', '');
        $number = input('post.number', '');

        if (empty($bank)) $this->ajaxReturn(['status' => -2, 'msg' => '银行名不能为空！']);
        if (mb_strlen($bank, 'UTF8') > 25) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请填写正确的银行名！']);
        }
        if (empty($name)) $this->ajaxReturn(['status' => -2, 'msg' => '姓名不能为空！']);
        if (mb_strlen($name, 'UTF8') > 5) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请填写正确的姓名！']);
        }
        if (empty($number)) $this->ajaxReturn(['status' => -2, 'msg' => '卡号不能为空！']);
        if (strlen($number) < 16) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请填写正确的卡号！']);
        }
        if (Db::name('card')->where(['number' => $number])->find()) {
            $this->ajaxReturn(['status' => -2, 'msg' => '卡号已存在！']);
        }

        $res = Db::name('card')->insert([
            'user_id' => $user_id,
            'bank' => $bank,
            'name' => $name,
            'number' => $number,
            'create_time' => time()
        ]);
        if ($res !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '操作失败']);
    }

}