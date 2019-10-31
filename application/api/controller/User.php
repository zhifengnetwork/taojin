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
                ->field('id,phone,nick_name,avatar,balance,recharge_balance,lock_balance,integral,currency,add_time,level,p_1')
                ->where(['id' => $user_id ,'status'=>1])
                ->find();
            if(empty($data)){
                $this->ajaxReturn(['status' => -2 , 'msg'=>'会员不存在！','data'=>'']);
            }
            if($data['p_1']){
                $data['p_1_phone']=Db::name('users')->where('id',$data['p_1'])->value('phone');
            }else{
                $data['p_1_phone']='无';
            }
            switch ($data['level']){
                case 0:
                    $data['level']='矿工';
                    break;
                case 1:
                    $data['level']='矿队长';
                    break;
                case 6:
                    $data['level']='矿场主';
                    break;
                default:
                    $data['level']='矿工';
                    break;
            }
            if(!$data['avatar']){
                $data['avatar']=SITE_URL.'/public/head/20190807156516165734848.png';
            }
            if(!$data['nick_name']){
                $data['nick_name']='未命名';
            }
            if($data['add_time']){
                $data['add_time']=date('Y.m.d',$data['add_time']);
            }
            if(empty($data['phone'])){
                $this->ajaxReturn(['status' => -2 , 'msg'=>'未绑定手机！','data'=>$data]);
            }
            $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$data]);
        }else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }

    }
    /*
     * 中奖音乐
     */
    public function music(){
        $data['music_url'] =  Db::name('config')->where(['inc_type'=>'taojin','name'=>'music_url'])->value('value');
        if($data['music_url']){
            $data['music_url']=SITE_URL.'/public'.$data['music_url'];
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$data]);
    }
    // “我的”
    public function index()
    {
        $user_id = $this->get_user_id();
        if (!$user_id) {
            $this->ajaxReturn(['status' => -1, 'msg' => '用户不存在', 'data' => '']);
        }
        $user = \app\common\model\Users::get($user_id);
        if(!$user){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在']);
        }

        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>[
            'id'=>$user->id,
            'name'=>$user->name,
            'level'=>$user->level_name?:'',
            'avatar'=>$user->avatar?:'',
            'register_time'=>$user->add_time,
            'balance'=>$user->balance,
            'sha'=>$user->integral,
            'bi'=>$user->currency
        ]]);
    }
    /*
     * 上传头像
     */
    public function updata_head_img2()
    {
        $user_id = $this->get_user_id();
        if (!$user_id) {
            $this->ajaxReturn(['status' => -1, 'msg' => '用户不存在', 'data' => '']);
        }
        if ($file = request()->file('file')) {
            $dir = UPLOAD_PATH . DS;
            if (!file_exists(ROOT_PATH . $dir)) mkdir(ROOT_PATH . $dir, 0777);
            if ($info = $file->validate(['size' => 2000000, 'ext' => 'jpg,png,jpeg'])->move(ROOT_PATH . $dir)) {
                Db::name('users')->where(['id' => $user_id])->update(['avatar' => SITE_URL . DS . $dir . $info->getSaveName()]);
                $this->ajaxReturn([
                    'status' => 1,
                    'msg' => '上传成功',
                    'data' => SITE_URL . DS . $dir . $info->getSaveName()
                ]);
            } else {
                $this->ajaxReturn(['status' => -2, 'msg' => $file->getError(), 'data' => $file->getInfo()]);
            }
        }
        $this->ajaxReturn(['status' => 2, 'msg' => '上传文件不存在']);
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
        $user_id = $this->get_user_id();
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
        $member = Db::name('users')->where(['id' => $user_id])->field('id,password,paypwd,phone')->find();
        $type = input('type',1);//1登录密码 2支付密码
        $code = input('code');
        $phone = $member['phone'];
        $loginLogic = new LoginLogic();
        $res = $loginLogic->phoneAuth($phone, $code);
        if ($res['status'] == -1 ) {
            $this->ajaxReturn(['status' => -1, 'msg' => $res['msg']]);
        }
        if ($type == 1) {
            $stri = 'password';
        } else {
            $stri = 'paypwd';
        }
        $password = password_hash($password2,PASSWORD_DEFAULT);
        if ($password == $member[$stri]) {
            $this->ajaxReturn(['status' => -2, 'msg' => '新密码和旧密码不能相同']);
        } else {
            $data = array($stri => $password);
            $update = Db::name('users')->where('id', $user_id)->update($data);
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
        $user_id = $this->get_user_id();
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
        if (!$code) {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码必填！']);
        }
        $loginLogic = new LoginLogic();
        $res = $loginLogic->phoneAuth($phone, $code);

        if ($res['status'] == -1 ) {
            $this->ajaxReturn(['status' => -1, 'msg' => $res['msg']]);
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
                'egg_num'=>$user->egg_num,
                'rate_percent' => Withdraw::getWDRate(),
                'rate_decimals' => Withdraw::getWDRate('decimals'),
                'alipay'=>[
                    'number'=>$user->ali_account,
                    'name'=>$user->name
                ],
                'card'=>Db::name('card')->field('id,bank,name,number')->where(['user_id'=>$user_id,'status'=>1])->order('id DESC')->select()
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
        $pay_type=I('pay_type',1);//1:淘金金沙  2:鸡蛋收益
        $paypwd=I('paypwd');
        if(!$paypwd){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请输入支付密码']);
        }
        $verify = password_verify($paypwd,$user['paypwd']);
        if ($verify == false) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'支付密码错误','data'=>null]);
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
        if($this->verify($money)){//判断是否为100的整数倍
            $this->ajaxReturn(['status' => -2, 'msg' => '提现数量必须是100的倍数！']);
        }
        if($pay_type==1){//淘金金沙
            $yu = bcsub($user->balance,$money);
            if ($yu < 0) {
                $this->ajaxReturn(['status' => -2, 'msg' => '超过可提现金额！']);
            }
        }else{//鸡蛋收益
            $yu = bcsub($user->egg_num,$money);
            if ($yu < 0) {
                $this->ajaxReturn(['status' => -2, 'msg' => '超过可提现收益！']);
            }
        }
        //提现申请
        Db::startTrans();
        $rate = Withdraw::getWDRate('decimals');
        $taxfee = bcmul($money, $rate, 2);//向下取整
        $withdraw_id = Db::name('withdraw')->insertGetId([
            'user_id' => $user_id,
            'type' => $type,
            'info' => $type == 2?$card_id:$user->ali_account,
            'rate' => $rate,
            'fee' => $taxfee,
            'money' => $money,
            'actual_money'=>($money-$taxfee),
            'status' => 0,
            'create_time' => time(),
        ]);
        if (!$withdraw_id) {
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '申请失败,请稍后再试！']);
        }
        if($pay_type==1){//淘金金沙
            $balance = $user->balance;
            $res = $user->save(['balance' => $yu]);
            if (!$res) {
                Db::rollback();
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
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '申请失败,请稍后再试！']);
            }
        }else{//鸡蛋收益
            $balance = $user->egg_num;
            $res = $user->save(['egg_num' => $yu]);
            if (!$res) {
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '申请失败,请稍后再试！']);
            }
            $res = Db::name('egg_log')->insert([
                'user_id' => $user_id,
                'type' => 9,
                'money' => $money,
                'balance' => $balance,
                'add_time' => time(),
                'desc' => '申请提现'
            ]);
            if (!$res) {
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '申请失败,请稍后再试！']);
            }
        }



        Db::commit();
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
            'status' => 1,
            'number' => $number,
            'create_time' => time()
        ]);
        if ($res !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '操作失败']);
    }

    /*
     * 进入实名认证
     */
    public function id_card_add(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在']);
        }
        $idcard=Db::name('idcard')->where('user_id',$user_id)->find();
        if($idcard){
//            if($idcard['status']==1){
//
//            }
            $idcard['idcard_front']=SITE_URL.__PUBLIC__.$idcard['idcard_front'];
            $idcard['idcard_back']=SITE_URL.__PUBLIC__.$idcard['idcard_back'];
            $this->ajaxReturn(['status' => 1, 'msg' => '成功','data'=>$idcard]);
        }else{
            $this->ajaxReturn(['status' => 1, 'msg' => '填写资料','data'=>'']);
        }
    }
    /*
     * 身份证上传
     */
    public function id_card(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在']);
        }
        $id=input('id');
        $name=input('name');
        $phone=input('phone');
        $ID_card=input('id_card');
        $idcard_front=input('idcard_front');
        $idcard_back=input('idcard_back');
        if (empty($name)) $this->ajaxReturn(['status' => -2, 'msg' => '姓名不能为空！']);
        if (mb_strlen($name, 'UTF8') > 5) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请填写正确的姓名！']);
        }
        if (empty($phone)) $this->ajaxReturn(['status' => -2, 'msg' => '手机号码不能为空！']);
        if (mb_strlen($phone, 'UTF8') != 11) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请填写正确的手机号码！']);
        }
        if (empty($ID_card)) $this->ajaxReturn(['status' => -2, 'msg' => '身份证号码不能为空！']);
        if (!$this->is_idcard($ID_card)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请填写正确的身份证！']);
        }
        if (empty($idcard_front)) $this->ajaxReturn(['status' => -2, 'msg' => '身份证正面不能为空！']);
        if (empty($idcard_back)) $this->ajaxReturn(['status' => -2, 'msg' => '身份证反面不能为空！']);
        if($id){
            $data=[];
            $data['id']=$id;
            $data['user_id']=$user_id;
            $data['user_name']=$name;
            $data['phone']=$phone;
            $data['id_card']=$ID_card;
            $data['idcard_front']=$idcard_front;
            $data['idcard_back']=$idcard_back;
            $data['status']=0;
            $data['up_time']=time();
            $res=Db::name('idcard')->update($data);
            $this->ajaxReturn(['status' => 1, 'msg' => '成功']);
        }else{
            $idcard=Db::name('idcard')->where('user_id',$user_id)->find();
            if($idcard){
                $this->ajaxReturn(['status' => -2, 'msg' => '已经提交过了，请不要重复提交']);
            }
            $data=[];
            $data['user_id']=$user_id;
            $data['user_name']=$name;
            $data['phone']=$phone;
            $data['id_card']=$ID_card;
            $data['idcard_front']=$idcard_front;
            $data['idcard_back']=$idcard_back;
            $data['up_time']=time();
            $data['add_time']=time();
            $ids=Db::name('idcard')->insertGetId($data);
            if($ids){
                $this->ajaxReturn(['status' => 1, 'msg' => '成功']);
            }else{
                $this->ajaxReturn(['status' => -2, 'msg' => '提交失败']);
            }
        }


    }
    function is_idcard($id)
    {
        $id = strtoupper($id);
        $regx = "/(^\d{15}$)|(^\d{17}([0-9]|X)$)/";
        $arr_split = array();
        if(!preg_match($regx, $id))
        {
            return FALSE;
        }
        if(15==strlen($id)) //检查15位
        {
            $regx = "/^(\d{6})+(\d{2})+(\d{2})+(\d{2})+(\d{3})$/";

            @preg_match($regx, $id, $arr_split);
            //检查生日日期是否正确
            $dtm_birth = "19".$arr_split[2] . '/' . $arr_split[3]. '/' .$arr_split[4];
            if(!strtotime($dtm_birth))
            {
                return FALSE;
            } else {
                return TRUE;
            }
        }
        else      //检查18位
        {
            $regx = "/^(\d{6})+(\d{4})+(\d{2})+(\d{2})+(\d{3})([0-9]|X)$/";
            @preg_match($regx, $id, $arr_split);
            $dtm_birth = $arr_split[2] . '/' . $arr_split[3]. '/' .$arr_split[4];
            if(!strtotime($dtm_birth)) //检查生日日期是否正确
            {
                return FALSE;
            }
            else
            {
                //检验18位身份证的校验码是否正确。
                //校验位按照ISO 7064:1983.MOD 11-2的规定生成，X可以认为是数字10。
                $arr_int = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);
                $arr_ch = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2');
                $sign = 0;
                for ( $i = 0; $i < 17; $i++ )
                {
                    $b = (int) $id{$i};
                    $w = $arr_int[$i];
                    $sign += $b * $w;
                }
                $n = $sign % 11;
                $val_num = $arr_ch[$n];
                if ($val_num != substr($id,17, 1))
                {
                    return FALSE;
                } //phpfensi.com
                else
                {
                    return TRUE;
                }
            }
        }

    }
    /**
     * 保存图片
     */
    public function uploads()
    {
        $param = input('image');
        $up_dir = ROOT_PATH . 'public' . DS . 'uploads/'.date('Ymd').'/';//存放在当前目录的upload文件夹下
        $base64_img = trim($param);
        if(preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_img, $result)){
            $type = $result[2];
            if(in_array($type,array('pjpeg','jpeg','jpg','gif','bmp','png'))){
                if(!is_dir($up_dir)) $res = mkdir($up_dir,0777,true);
                $img_path=time().'.'.$type;
                $new_file = $up_dir.$img_path;
                if(file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_img)))){
                    $this->ajaxReturn(['status' => 1, 'msg' => '上传成功','data'=> '/uploads/'.date('Ymd').$img_path]);
                }else{
                    $this->ajaxReturn(['status' =>-2, 'msg' => '图片上传失败']);
                }
            }else{
                //文件类型错误
                $this->ajaxReturn(['status' =>-2, 'msg' => '图片上传类型错误']);
            }
        }

    }
    public function verify($money){
        $is_int=$money/100;
        if(ceil($is_int)!=$is_int){
            return true;
        }else{
            return false;
        }
    }
}