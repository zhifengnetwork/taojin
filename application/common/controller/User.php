<?php

namespace app\common\controller;

//db()用省略表前缀名Db::table()用表全名
use think\Controller;
use think\Db;

class User extends Controller
{
    public function _initialize(){

        parent::_initialize();
    }


    /*
     * 这个地方需要优化,不能获取用户信息就统计
     * leapmary//todo:待优化
     */
    public function getuserinfo($token,$u_id){

        //leapmary//###测试开启####
        /*if(empty($u_id)){
            $token = '665f6916cccd1aa1b91df5da9d7373099657a78f05d29219b7a40424a0acd00a';
        }*/
        //leapmary//###测试开启####
        
        //token还是id寻找
        if(!empty($token)){
            $sqlwhere = " token = '" . $token . "'";
        }else{
            $sqlwhere = " id = " . $u_id;
        }

        $encodekey = config('encodekey');

        $getusersql = "SELECT *,
                                DECODE(
                                    balance,
                                    '" . $encodekey . "'
                                ) as balance_s,
                                DECODE(
                                    integral,
                                    '" . $encodekey . "'
                                ) as integral_s,
                                DECODE(
                                    all_balance,
                                    '" . $encodekey . "'
                                ) as all_balance_s,
                                DECODE(
                                    all_integral,
                                    '" . $encodekey . "'
                                ) as all_integral_s
                            FROM
                                clt_users where " . $sqlwhere;

        $userinfo = db()->query($getusersql);

        if(empty($userinfo)){
            return [];
        }

        /*#####################保留两位小数######################*/
        $userinfo[0]['balance_s'] = floor($userinfo[0]['balance_s']*100)/100;
        $userinfo[0]['all_balance_s'] = floor($userinfo[0]['all_balance_s']*100)/100;
        $userinfo[0]['integral_s'] = floor($userinfo[0]['integral_s']*100)/100;
        $userinfo[0]['all_integral_s'] = floor($userinfo[0]['all_integral_s']*100)/100;
        /*#####################保留两位小数######################*/

        $this->user_id = $userinfo[0]['id'];

        $user_level = db('user_level')->select();
        $user_level = convert_arr_kv($user_level,'level_id','level_name');

        /*print_r($userinfo);exit;*/
        //对接虫虫e购
        //余额
        if(empty($userinfo[0]['balance_s'])){
            $userinfo[0]['balance_s'] = 0;
        }
        //总得到金额
        if(empty($userinfo[0]['all_balance_s'])){
            $userinfo[0]['all_balance_s'] = 0;
        }
        //积分
        if(empty($userinfo[0]['integral_s'])){
            $userinfo[0]['integral_s'] = 0;
        }
        //总积分
        if(empty($userinfo[0]['all_integral_s'])){
            $userinfo[0]['all_integral_s'] = 0;
        }
        //总粉丝数
        if(empty($userinfo[0]['all_integral_s'])){
            $userinfo[0]['all_integral_s'] = 0;
        }

        //待返利计算
        /********************************************
         * 相关统计信息
         * ******************************************/
        //我的一级下级u_id
        $users1arr = [];
        $users_ids_1 = db('users')->where("p_1",$this->user_id)->field('id')->select();
        foreach($users_ids_1 as $k=>$v){
            $users1arr[] = $v['id'];
        }
        //我的二级下级u_id
        $users2arr = [];
        $users_ids_2 = db('users')->where("p_2",$this->user_id)->field('id')->select();
        foreach($users_ids_2 as $k=>$v){
            $users2arr[] = $v['id'];
        }

        //本月本人预估####################################
        $confing = action('common/Alluse/getconfig',['inc_type'=>'app']);
        //leapmary//是否开启角色返利
        if($confing['if_role_rebate']){
            $user_level_info = $this->get_user_level();
            //重新赋值为角色比例
            $confing['selfrebate'] = $user_level_info[$userinfo[0]['level']];
        }

         
	 //如果用户是VIP那么一级返利比例是20,二级返利比例是0
	 if($confing['selfrebate'] == 40){
	 	$confing['onerebate'] = 20;
		$confing['tworebate'] = 0;
	 }
	 //如果用户是VIP那么一级返利比例是35，二级返利比例是15
	 if($confing['selfrebate'] == 50){
	 	$confing['onerebate'] = 35;
		$confing['tworebate'] = 15;
	 }

        //淘宝总统计
        $all_tbk_returns = db('tbkorder')
            ->where("user_id",$this->user_id)
            ->where("returnstatus",0)
            ->where('order_state_num','neq',2)
            ->sum('evaluation');

        $all_tbk_returns = action('common/Goods/getcommission',['money'=>$all_tbk_returns,'rebate'=>$confing['selfrebate']]);

        //淘宝下一级统计
        $all_tbk_returns1 = db('tbkorder')
            ->where("user_id",'in',$users1arr)
            ->where("user_id",'neq',0)
            ->where('order_state_num','neq',2)
            ->where("returnstatus",0)
            ->sum('evaluation');

        $all_tbk_returns1 = action('common/Goods/getcommission',['money'=>$all_tbk_returns1,'rebate'=>$confing['onerebate']]);

        //淘宝下二级统计
        $all_tbk_returns2 = db('tbkorder')
            ->where("user_id",'in',$users2arr)
            ->where("user_id",'neq',0)
            ->where('order_state_num','neq',2)
            ->where("returnstatus",0)
            ->sum('evaluation');

        $all_tbk_returns2 = action('common/Goods/getcommission',['money'=>$all_tbk_returns2,'rebate'=>$confing['tworebate']]);

        //京东//本身京东只收录已支付的
        $all_jd_returns = db('jdapiorder')
            ->where("u_id",$this->user_id)
            ->where("u_id",'neq',0)
            ->where("returnstatus",0)
            ->sum('actualCommission');

        $all_jd_returns = action('common/Goods/getcommission',['money'=>$all_jd_returns,'rebate'=>$confing['selfrebate']]);

        //本月下一级京东
        $all_jd_returns1 = db('jdapiorder')
            ->where("u_id","in",$users1arr)
            ->where("u_id",'neq',0)
            ->where("returnstatus",0)
            ->sum('actualCommission');
        $all_jd_returns1 = action('common/Goods/getcommission',['money'=>$all_jd_returns1,'rebate'=>$confing['onerebate']]);

        //本月下二级京东
        $all_jd_returns2 = db('jdapiorder')
            ->where("u_id","in",$users2arr)
            ->where("u_id",'neq',0)
            ->where("returnstatus",0)
            ->sum('actualCommission');

        $all_jd_returns2 = action('common/Goods/getcommission',['money'=>$all_jd_returns2,'rebate'=>$confing['tworebate']]);

        //待返利=本人预估+下一级预估+下二级预估+京东自己预估+京东下一级预估+京东下二级预估
        $userinfo[0]['ready_rebate'] = ($all_tbk_returns+$all_tbk_returns1+$all_tbk_returns2+$all_jd_returns+$all_jd_returns1+$all_jd_returns2);

        /*file_put_contents("aaaaaaaaaaaaaaaaaaaaa.txt",$all_tbk_returns."_".$all_tbk_returns1."_".$all_tbk_returns2."_".$all_jd_returns."_".$all_jd_returns1."_".$all_jd_returns2);*/
        /*##############################################*/
        //待返利
        if(empty($userinfo[0]['ready_rebate'])){
            $userinfo[0]['ready_rebate'] = 0;
        }

        //控制错误直接赋值0
        //$userinfo[0]['ready_rebate'] = 0;

        //用户二级以上就是合伙人
        if($userinfo[0]['level'] >= 2){
            $userinfo[0]['is_agent'] = 2;
        }

        //leapmary//虫虫e购同步
        $userinfo[0]['gold'] = $userinfo[0]['balance_s'];   //余额
        $userinfo[0]['account_gold'] = $userinfo[0]['all_balance_s']; //总余额
        $userinfo[0]['wait_gold'] = $userinfo[0]['ready_rebate'];  //待返利
        $userinfo[0]['able_score'] = $userinfo[0]['integral_s'];  //现在积分

        $userinfo[0]['etc'] = $userinfo[0]['fans']; //粉丝
        $userinfo[0]['order_num'] = $userinfo[0]['fans_order_num']; //粉丝订单

        $userinfo[0]['grade_name'] = $user_level[$userinfo[0]['level']];  //等级名称

        unset($userinfo[0]['balance']);
        unset($userinfo[0]['all_balance']);
        unset($userinfo[0]['integral']);
        unset($userinfo[0]['all_integral']);
        //针对个人##########
        return $userinfo[0];

    }

    /**
     * 给用户加减积分
     * $type:加积分类型
     * $comment：用户数据，给谁加
     **/
    public function addintegral($type,$data){

        $confing = action('common/Alluse/getconfig',['inc_type' => 'app']);
        $encodekey = config('encodekey');

        $ifcommit = [];
        // 启动事务
        Db::startTrans();
        try{
            switch($type){
                //1晒单加(积分)1
                case 'comment_gold':
                    //晒过的不再加积分
                    if($data['verify'] == 2){
                        echo "已经晒过";
                        exit;
                    }

                    $type_integral = 1;
                    //改评论状态为已经给积分
                    $ifcommit[] = Db::table('clt_comment')->where(['id' => $data['id']])
                        ->update([
                            'verify' => 2
                        ]);
                    $integralnum = $confing[$type];
                    break;
                //2晒单被点赞(积分)2
                case 'zan_integration':
                    $type_integral = 2;
                    $integralnum = $confing[$type];
                    break;
                //3每天首次分享app送(积分)3
                case 'shareappintegral':
                    $type_integral = 3;
                    $integralnum = $confing[$type];
                    break;
                //4签到加(积分)4
                case 'signin_reward':
                    $type_integral = 4;
                    $integralnum = $confing[$type];
                    break;
                //5分享晒单送(积分)5
                case 'cream_gold':
                    $type_integral = 5;
                    $integralnum = $confing[$type];
                    break;
                //6评论送(积分)6
                case 'comment_integral':
                    $type_integral = 6;
                    $integralnum = $confing[$type];
                    break;
                //7兑换积分商品7
                case 'exchangegoods':
                    $type_integral = 7;
                    $integralnum = $data['integralnum'];
                    break;
                //管理员手动触发
                case 'admin_add':
                    $type_integral = 8;
                    $integralnum = $data['integral_s'];
                    break;
                default:
                    echo "系统错误,请联系管理员";
                    exit;
                    break;
            }

            //总额只加不减
            if($integralnum >= 0){
                $integralnumone = $integralnum;
                $integralnumall = $integralnum;
            }else{
                $integralnumone = $integralnum;
                $integralnumall = 0;
            }

            //两名两暗全部更改
            $ifcommit[] = db()->execute("UPDATE clt_users
                                        SET integral = ENCODE(
                                            DECODE(
                                                integral,
                                                '" . $encodekey . "'
                                            ) + " . $integralnumone . ",
                                            '" . $encodekey . "'
                                        ),
                                        all_integral = ENCODE(
                                            DECODE(
                                                all_integral,
                                                '" . $encodekey . "'
                                            ) + " . $integralnumall . ",
                                            '" . $encodekey . "'
                                        ),
                                        able_score = able_score + " . $integralnumone . ",
                                        all_able_score = all_able_score + " . $integralnumall . "
                                        where id = " . $data['user_id']);

            //记录日志
            $info['u_id'] = $data['user_id'];
            $info['createtime'] = date("Y-m-d h:i:s");
            $info['integral'] = $integralnum;
            $info['then_integral'] = $data['integral_s'];
            $info['type'] = $type_integral;

            $ifcommit[] = $logid = $this->addintegrallog($info);

            //有一个0或者空或者null就是0也就是false
            $ifcommitre = 1;
            foreach($ifcommit as $k => $v){
                $ifcommitre = $ifcommitre * $v;
            }
            // 提交事务
            if($ifcommitre){
                Db::commit();
            }else{
                Db::rollback();
            }

        }catch(\Exception $e){
            // 回滚事务
            Db::rollback();
        }

        if($ifcommitre){
            return $logid;
        }else{
            return 0;
        }

    }

    /**
     * 记录用户积分流水
     **/
    public function addintegrallog($info){

        $re = db('integral')->insertGetId($info);
        return $re;

    }

    /**
     * 获取用户等级信息
     **/
    public function get_user_level(){

        $dataconfig = cache('user_level');
        /*print_r($dataconfig);exit;*/
        if(empty($dataconfig)){
            $user_level = db('user_level')->select();
            $user_level = convert_arr_kv($user_level,'level_id','proportion');
            cache('user_level',$user_level);
        }

        return $dataconfig;

    }

    /**
     * 根据用户当前等级返利金额
     **/
    public function get_user_fanli_money($user_info,$money){
        $user_level = $this->get_user_level();
        //$user_level[$user_info['level']];
        $good = new Goods();
        return $good->getcommission($money,$user_level[$user_info['level']]);
    }

    /**
     * 获取用户等级信息
     **/
    public function get_user_level_money(){

        $dataconfig = cache('user_level_money');
        /*print_r($dataconfig);exit;*/
        if(empty($dataconfig)){
            $user_level = db('user_level')->select();
            $user_level = convert_arr_kv($user_level,'level_id','money');
            cache('user_level_money',$user_level);
        }

        return $dataconfig;

    }

    /**
     * 给用户加减钱//这里不写过多的逻辑，只是给谁返钱因为谁，并记录日志
     **/
    public function addmoney($type,$data){

        //print_r($ifcommit);exit;
        //获取两级返利信息
        $confing = action('common/Alluse/getconfig',['inc_type' => 'app']);

        //leapmary//是否开启角色返利
        if($confing['if_role_rebate']){
            $user_level = $this->get_user_level();
            //重新赋值为角色比例
            $confing['selfrebate'] = $user_level[$data['user_info']['level']];
        }

        $encodekey = config('encodekey');
        $time = date("Y-m-d h:i:s");

        $ifcommit = [];

        // 启动事务
        Db::startTrans();
        try{
            switch($type){
                //1淘宝订单返利
                case 1:
                    //必须是未结算的才能返利
                    if($data['tbkorder']['returnstatus'] == 1){
                        return 0;
                        exit;
                    }
                    //必须是订单结算的才能返利
                    if($data['tbkorder']['order_state_num'] != 3){
                        return 0;
                        exit;
                    }

                    $type_money = 1; //淘宝订单
                    //改订单状态为已经返利
                    $ifcommit[] = Db::table('clt_tbkorder')->where('order_sn',$data['tbkorder']['order_sn'])
                        ->update([
                            'returnstatus' => 1,
                            //'order_state_num' => 4
                        ]);

                    //leapmary//用户的钱#######################################
                    $info0['createtime'] = $time;
                    $info0['user_id'] = $data['user_info']['id'];  //给谁返利
                    $info0['be_user_id'] = $data['user_info']['id'];  //因为谁执行返利
                    $info0['order_id'] = $data['tbkorder']['id'];  //那一个订单号
                    $info0['money'] = action('common/Goods/getcommission',['money' => $data['tbkorder']['estimate'],'rebate' => $confing['selfrebate']]);  //返利多少钱
                    $info0['intro'] = "自买淘宝返利" . $info0['money'];  //旁白
                    $info0['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                    $info0['typefrom'] = 1;  // 1自身订单返利2下一级订单返利3下二级订单返利
                    $info0['balance'] = $data['user_info']['balance_s'];  //没执行之前用户金额
                    $info0['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                    //leapmary//用户的钱#######################################

                    //给自己加钱
                    $ifcommit[] = $this->addmoneyandlog($info0,$encodekey);
                    //给上一级加钱
                    if($data['user_info']['p_1'] != 0){
                        $p_1info = action('common/User/getuserinfo',['token' => '','u_id' => $data['user_info']['p_1']]);
                        if(!empty($p_1info)){
                            //leapmary//用户的钱#######################################
                            $info1['createtime'] = $time;
                            $info1['user_id'] = $data['user_info']['p_1'];  //给谁返利
                            $info1['be_user_id'] = $data['user_info']['id'];  //因为谁执行返利
                            $info1['order_id'] = $data['tbkorder']['id'];  //那一个订单号
                            $info1['money'] = action('common/Goods/getcommission',['money' => $data['tbkorder']['estimate'],'rebate' => $confing['onerebate']]);  //返利多少钱
                            $info1['intro'] = "下一级" . $data['user_info']['nick_name'] . '给您返利' . $info1['money'];  //旁白
                            $info1['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                            $info1['typefrom'] = 2;  // 1自身订单返利2下一级订单返利3下二级订单返利
                            $info1['balance'] = $p_1info['balance_s'];  //没执行之前用户金额
                            $info1['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                            //leapmary//用户的钱#######################################
                            $ifcommit[] = $this->addmoneyandlog($info1,$encodekey);
                        }

                    }
                    //给给上二级加钱
                    if($data['user_info']['p_2'] != 0){
                        $p_2info = action('common/User/getuserinfo',['token' => '','u_id' => $data['user_info']['p_2']]);
                        if(!empty($p_2info)){
                            //leapmary//用户的钱#######################################
                            $info2['createtime'] = $time;
                            $info2['user_id'] = $data['user_info']['p_2'];  //给谁返利
                            $info2['be_user_id'] = $data['user_info']['id'];  //因为谁执行返利
                            $info2['order_id'] = $data['tbkorder']['id'];  //那一个订单号
                            $info2['money'] = action('common/Goods/getcommission',['money' => $data['tbkorder']['estimate'],'rebate' => $confing['tworebate']]);  //返利多少钱
                            $info2['intro'] = "下二级" . $data['user_info']['nick_name'] . '给您返利' . $info2['money'];  //旁白
                            $info2['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                            $info2['typefrom'] = 3;  // 1自身订单返利2下一级订单返利3下二级订单返利
                            $info2['balance'] = $p_2info['balance_s'];  //没执行之前用户金额
                            $info2['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                            //leapmary//用户的钱#######################################
                            $ifcommit[] = $this->addmoneyandlog($info2,$encodekey);
                        }
                    }
                    break;
                //2京东订单返利
                case 2:

                    $type_money = 2; //京东订单
                    //必须是未结算的才能返利
                    if($data['jdapiorder']['returnstatus'] == 1){
                        return 0;
                        exit;
                    }
                    //必须是订单结算的才能返利
                    if($data['jdapiorder']['validcode'] != 18){
                        return 0;
                        exit;
                    }

                    //改订单状态为已经返利
                    $ifcommit[] = db('jdapiorder')->where('orderid',$data['jdapiorder']['orderid'])
                        ->update([
                            'returnstatus' => 1,
                        ]);

                    //leapmary//用户的钱#######################################
                    $info0['createtime'] = $time;
                    $info0['user_id'] = $data['user_info']['id'];  //给谁返利
                    $info0['be_user_id'] = $data['user_info']['id'];  //因为谁执行返利
                    $info0['order_id'] = $data['jdapiorder']['id'];  //那一个订单号
                    $info0['money'] = action('common/Goods/getcommission',['money' => $data['jdapiorder']['actualCommission'],'rebate' => $confing['selfrebate']]);  //返利多少钱
                    $info0['intro'] = "自买京东返利" . $info0['money'];  //旁白
                    $info0['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                    $info0['typefrom'] = 1;  // 1自身订单返利2下一级订单返利3下二级订单返利
                    $info0['balance'] = $data['user_info']['balance_s'];  //没执行之前用户金额
                    $info0['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                    //leapmary//用户的钱#######################################

                    //给自己加钱
                    $ifcommit[] = $this->addmoneyandlog($info0,$encodekey);
                    //给上一级加钱
                    if($data['user_info']['p_1'] != 0){
                        $p_1info = action('common/User/getuserinfo',['token' => '','u_id' => $data['user_info']['p_1']]);
                        //leapmary//用户的钱#######################################
                        $info1['createtime'] = $time;
                        $info1['user_id'] = $data['user_info']['p_1'];  //给谁返利
                        $info1['be_user_id'] = $data['user_info']['id'];  //因为谁执行返利
                        $info1['order_id'] = $data['jdapiorder']['id'];  //那一个订单号
                        $info1['money'] = action('common/Goods/getcommission',['money' => $data['jdapiorder']['actualCommission'],'rebate' => $confing['onerebate']]);  //返利多少钱
                        $info1['intro'] = "下一级" . $data['user_info']['nick_name'] . '给您返利' . $info1['money'];  //旁白
                        $info1['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                        $info1['typefrom'] = 2;  // 1自身订单返利2下一级订单返利3下二级订单返利
                        $info1['balance'] = $p_1info['balance_s'];  //没执行之前用户金额
                        $info1['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                        //leapmary//用户的钱#######################################
                        $ifcommit[] = $this->addmoneyandlog($info1,$encodekey);
                    }
                    //给给上二级加钱
                    if($data['user_info']['p_2'] != 0){
                        $p_2info = action('common/User/getuserinfo',['token' => '','u_id' => $data['user_info']['p_2']]);
                        //leapmary//用户的钱#######################################
                        $info2['createtime'] = $time;
                        $info2['user_id'] = $data['user_info']['p_2'];  //给谁返利
                        $info2['be_user_id'] = $data['user_info']['id'];  //因为谁执行返利
                        $info2['order_id'] = $data['jdapiorder']['id'];  //那一个订单号
                        $info2['money'] = action('common/Goods/getcommission',['money' => $data['jdapiorder']['actualCommission'],'rebate' => $confing['tworebate']]);  //返利多少钱
                        $info2['intro'] = "下二级" . $data['user_info']['nick_name'] . '给您返利' . $info2['money'];  //旁白
                        $info2['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                        $info2['typefrom'] = 3;  // 1自身订单返利2下一级订单返利3下二级订单返利
                        $info2['balance'] = $p_2info['balance_s'];  //没执行之前用户金额
                        $info2['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                        //leapmary//用户的钱#######################################
                        $ifcommit[] = $this->addmoneyandlog($info2,$encodekey);
                    }
                    break;
                //3蘑菇街订单返利
                case 3:
                    $type_money = 3;
                    //造假的蘑菇街
                    echo 0;
                    exit;
                    break;
                //4提现
                case 4:
                    $type_money = 4;
                    //leapmary//用户的钱#######################################
                    $info0['createtime'] = $time;
                    $info0['user_id'] = $data['user_info']['id'];  //谁提现
                    $info0['be_user_id'] = $data['user_info']['id'];  //因为谁提现
                    $info0['order_id'] = 0;  //那一个订单号
                    $info0['money'] = -$data['gold'];  //提现多少是减去
                    $info0['intro'] = $data['user_info']['nick_name'] . "申请提现" . $info0['money'];  //旁白
                    $info0['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                    $info0['typefrom'] = 1;  // 1自身订单返利2下一级订单返利3下二级订单返利
                    $info0['balance'] = $data['user_info']['balance_s'];  //没执行之前用户金额
                    $info0['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                    //leapmary//用户的钱#######################################

                    //给自己加钱
                    $ifcommit[] = $this->addmoneyandlog($info0,$encodekey);
                    break;
                //5注册留记录
                case 5:
                    /*print_r($data);exit;*/
                    $type_money = 5; //注册
                    //改订单状态为已经返利
                    //leapmary//用户的钱#######################################
                    $info0['createtime'] = $time;
                    $info0['user_id'] = $data['selfid'];  //给谁返利
                    $info0['be_user_id'] = $data['selfid'];  //因为谁执行返利
                    $info0['order_id'] = 0;  //那一个订单号
                    $info0['money'] = 0;  //返利多少钱
                    $info0['intro'] = "注册的0";  //旁白
                    $info0['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现5注册
                    $info0['typefrom'] = 1;  // 1自身订单返利2下一级订单返利3下二级订单返利
                    $info0['balance'] = 0;  //没执行之前用户金额
                    $info0['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                    //leapmary//用户的钱#######################################

                    //给自己加钱
                    $ifcommit[] = $this->addmoneyandlog($info0,$encodekey);
                    //给上一级加钱
                    if($data['p_1'] != 0){
                        $p_1info = action('common/User/getuserinfo',['token' => '','u_id' => $data['p_1']]);
                        if(!empty($p_1info)){
                            //leapmary//用户的钱#######################################
                            $info1['createtime'] = $time;
                            $info1['user_id'] = $data['p_1'];  //给谁返利
                            $info1['be_user_id'] = $data['selfid'];  //因为谁执行返利
                            $info1['order_id'] = 0;  //那一个订单号
                            $info1['money'] = 0;  //返利多少钱
                            $info1['intro'] = "下一级注册给您返利0";  //旁白
                            $info1['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                            $info1['typefrom'] = 2;  // 1自身订单返利2下一级订单返利3下二级订单返利
                            $info1['balance'] = $p_1info['balance_s'];  //没执行之前用户金额
                            $info1['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                            //leapmary//用户的钱#######################################
                            $ifcommit[] = $this->addmoneyandlog($info1,$encodekey);
                        }

                    }
                    //给给上二级加钱
                    if($data['p_2'] != 0){
                        $p_2info = action('common/User/getuserinfo',['token' => '','u_id' => $data['p_2']]);
                        if(!empty($p_2info)){
                            //leapmary//用户的钱#######################################
                            $info2['createtime'] = $time;
                            $info2['user_id'] = $data['p_2'];  //给谁返利
                            $info2['be_user_id'] = $data['selfid'];  //因为谁执行返利
                            $info2['order_id'] = 0;  //那一个订单号
                            $info2['money'] = 0;  //返利多少钱
                            $info2['intro'] = "下二级注册给您返利0";  //旁白
                            $info2['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                            $info2['typefrom'] = 3;  // 1自身订单返利2下一级订单返利3下二级订单返利
                            $info2['balance'] = $p_2info['balance_s'];  //没执行之前用户金额
                            $info2['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                            //leapmary//用户的钱#######################################
                            $ifcommit[] = $this->addmoneyandlog($info2,$encodekey);
                        }

                    }
                    break;
                //管理员手动操作
                case 6:
                    $type_money = 6;
                    //leapmary//用户的钱#######################################
                    $info0['createtime'] = $time;
                    $info0['user_id'] = $data['user_info']['id'];  //给谁操作
                    $info0['be_user_id'] = $data['user_info']['id'];  //给谁操作
                    $info0['order_id'] = 0;  //那一个订单号
                    $info0['money'] = $data['num'];  //操作多少是减去
                    $info0['intro'] = $data['user_info']['nick_name'] . "管理员操作" . $info0['money'];  //旁白
                    $info0['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                    $info0['typefrom'] = 1;  // 1自身订单返利2下一级订单返利3下二级订单返利
                    $info0['balance'] = $data['user_info']['balance_s'];  //没执行之前用户金额
                    $info0['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                    //leapmary//用户的钱#######################################

                    //给自己加钱
                    $ifcommit[] = $this->addmoneyandlog($info0,$encodekey);
                    break;
                    break;
                default:
                    echo "系统错误,请联系管理员";
                    exit;
                    break;
            }

            //有一个0或者空或者null就是0也就是false
            $ifcommitre = 1;
            foreach($ifcommit as $k => $v){
                $ifcommitre = $ifcommitre * $v;
            }

            // 提交事务
            if($ifcommitre){
                Db::commit();
            }else{
                Db::rollback();
            }

        }catch(\Exception $e){
            // 回滚事务
            Db::rollback();
        }

        if($ifcommitre){
            return 1;
        }else{
            return 0;
        }

    }



    /**
     * 记录用户money流水并且加钱
     **/
    public function addmoneyandlog($info,$encodekey){
        //去掉无限循环小数,舍去取整
        $info['money'] = floor($info['money'] * 100) / 100;

        //记录日志
        $relog = db('moneydetail')->insertGetId($info);

        //总额只加不减
        if($info['money'] >= 0){
            $moneynumone = $info['money'];
            $moneynumall = $info['money'];
        }else{
            $moneynumone = $info['money'];
            $moneynumall = 0;
        }

        if($info['money'] == 0){
            $one = 1;
        }else{
            //开始加钱
            $one = db()->execute("UPDATE clt_users
                                        SET balance = ENCODE(
                                            DECODE(
                                                balance,
                                                '" . $encodekey . "'
                                            ) + " . $moneynumone . ",
                                            '" . $encodekey . "'
                                        ),
                                        all_balance = ENCODE(
                                            DECODE(
                                                all_balance,
                                                '" . $encodekey . "'
                                            ) + " . $moneynumall . ",
                                            '" . $encodekey . "'
                                        ),
                                        gold = gold + " . $moneynumone . ",
                                        account_gold = account_gold + " . $moneynumall . "
                                        where id = " . $info['user_id']);
        }

        if(($relog * $one) != 0){
            return 1;
        }else{
            return 0;
        }

    }

    public function ceshi(){
        echo "common/controller/User.php";
    }

    //以下为所修改的提现 by lee 20180718
    //在后台审核后，会通过程序将钱打到用户支付宝账户里

    /**
     * 给用户加减钱//这里不写过多的逻辑，只是给谁返钱因为谁，并记录日志
     **/
    public function addmoney2($type,$data){

        //print_r($ifcommit);exit;
        //获取两级返利信息
        $confing = action('common/Alluse/getconfig',['inc_type' => 'app']);

        //leapmary//是否开启角色返利
        if($confing['if_role_rebate']){
            $user_level = $this->get_user_level();
            //重新赋值为角色比例
            $confing['selfrebate'] = $user_level[$data['user_info']['level']];
        }

        $encodekey = config('encodekey');
        $time = date("Y-m-d h:i:s");

        $ifcommit = [];

        // 启动事务
        Db::startTrans();
        try{
            switch($type){
                //1淘宝订单返利
                case 1:
                    //必须是未结算的才能返利
                    if($data['tbkorder']['returnstatus'] == 1){
                        return 0;
                        exit;
                    }
                    //必须是订单结算的才能返利
                    if($data['tbkorder']['order_state_num'] != 3){
                        return 0;
                        exit;
                    }

                    $type_money = 1; //淘宝订单
                    //改订单状态为已经返利
                    $ifcommit[] = Db::table('clt_tbkorder')->where('order_sn',$data['tbkorder']['order_sn'])
                        ->update([
                            'returnstatus' => 1,
                            //'order_state_num' => 4
                        ]);

                    //leapmary//用户的钱#######################################
                    $info0['createtime'] = $time;
                    $info0['user_id'] = $data['user_info']['id'];  //给谁返利
                    $info0['be_user_id'] = $data['user_info']['id'];  //因为谁执行返利
                    $info0['order_id'] = $data['tbkorder']['id'];  //那一个订单号
                    $info0['money'] = action('common/Goods/getcommission',['money' => $data['tbkorder']['estimate'],'rebate' => $confing['selfrebate']]);  //返利多少钱
                    $info0['intro'] = "自买淘宝返利" . $info0['money'];  //旁白
                    $info0['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                    $info0['typefrom'] = 1;  // 1自身订单返利2下一级订单返利3下二级订单返利
                    $info0['balance'] = $data['user_info']['balance_s'];  //没执行之前用户金额
                    $info0['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                    //leapmary//用户的钱#######################################

                    //给自己加钱
                    $ifcommit[] = $this->addmoneyandlog($info0,$encodekey);
                    //给上一级加钱
                    if($data['user_info']['p_1'] != 0){
                        $p_1info = action('common/User/getuserinfo',['token' => '','u_id' => $data['user_info']['p_1']]);
                        if(!empty($p_1info)){
                            //leapmary//用户的钱#######################################
                            $info1['createtime'] = $time;
                            $info1['user_id'] = $data['user_info']['p_1'];  //给谁返利
                            $info1['be_user_id'] = $data['user_info']['id'];  //因为谁执行返利
                            $info1['order_id'] = $data['tbkorder']['id'];  //那一个订单号
                            $info1['money'] = action('common/Goods/getcommission',['money' => $data['tbkorder']['estimate'],'rebate' => $confing['onerebate']]);  //返利多少钱
                            $info1['intro'] = "下一级" . $data['user_info']['nick_name'] . '给您返利' . $info1['money'];  //旁白
                            $info1['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                            $info1['typefrom'] = 2;  // 1自身订单返利2下一级订单返利3下二级订单返利
                            $info1['balance'] = $p_1info['balance_s'];  //没执行之前用户金额
                            $info1['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                            //leapmary//用户的钱#######################################
                            $ifcommit[] = $this->addmoneyandlog($info1,$encodekey);
                        }

                    }
                    //给给上二级加钱
                    if($data['user_info']['p_2'] != 0){
                        $p_2info = action('common/User/getuserinfo',['token' => '','u_id' => $data['user_info']['p_2']]);
                        if(!empty($p_2info)){
                            //leapmary//用户的钱#######################################
                            $info2['createtime'] = $time;
                            $info2['user_id'] = $data['user_info']['p_2'];  //给谁返利
                            $info2['be_user_id'] = $data['user_info']['id'];  //因为谁执行返利
                            $info2['order_id'] = $data['tbkorder']['id'];  //那一个订单号
                            $info2['money'] = action('common/Goods/getcommission',['money' => $data['tbkorder']['estimate'],'rebate' => $confing['tworebate']]);  //返利多少钱
                            $info2['intro'] = "下二级" . $data['user_info']['nick_name'] . '给您返利' . $info2['money'];  //旁白
                            $info2['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                            $info2['typefrom'] = 3;  // 1自身订单返利2下一级订单返利3下二级订单返利
                            $info2['balance'] = $p_2info['balance_s'];  //没执行之前用户金额
                            $info2['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                            //leapmary//用户的钱#######################################
                            $ifcommit[] = $this->addmoneyandlog($info2,$encodekey);
                        }
                    }
                    break;
                //2京东订单返利
                case 2:

                    $type_money = 2; //京东订单
                    //必须是未结算的才能返利
                    if($data['jdapiorder']['returnstatus'] == 1){
                        return 0;
                        exit;
                    }
                    //必须是订单结算的才能返利
                    if($data['jdapiorder']['validcode'] != 18){
                        return 0;
                        exit;
                    }

                    //改订单状态为已经返利
                    $ifcommit[] = db('jdapiorder')->where('orderid',$data['jdapiorder']['orderid'])
                        ->update([
                            'returnstatus' => 1,
                        ]);

                    //leapmary//用户的钱#######################################
                    $info0['createtime'] = $time;
                    $info0['user_id'] = $data['user_info']['id'];  //给谁返利
                    $info0['be_user_id'] = $data['user_info']['id'];  //因为谁执行返利
                    $info0['order_id'] = $data['jdapiorder']['id'];  //那一个订单号
                    $info0['money'] = action('common/Goods/getcommission',['money' => $data['jdapiorder']['actualCommission'],'rebate' => $confing['selfrebate']]);  //返利多少钱
                    $info0['intro'] = "自买京东返利" . $info0['money'];  //旁白
                    $info0['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                    $info0['typefrom'] = 1;  // 1自身订单返利2下一级订单返利3下二级订单返利
                    $info0['balance'] = $data['user_info']['balance_s'];  //没执行之前用户金额
                    $info0['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                    //leapmary//用户的钱#######################################

                    //给自己加钱
                    $ifcommit[] = $this->addmoneyandlog($info0,$encodekey);
                    //给上一级加钱
                    if($data['user_info']['p_1'] != 0){
                        $p_1info = action('common/User/getuserinfo',['token' => '','u_id' => $data['user_info']['p_1']]);
                        //leapmary//用户的钱#######################################
                        $info1['createtime'] = $time;
                        $info1['user_id'] = $data['user_info']['p_1'];  //给谁返利
                        $info1['be_user_id'] = $data['user_info']['id'];  //因为谁执行返利
                        $info1['order_id'] = $data['jdapiorder']['id'];  //那一个订单号
                        $info1['money'] = action('common/Goods/getcommission',['money' => $data['jdapiorder']['actualCommission'],'rebate' => $confing['onerebate']]);  //返利多少钱
                        $info1['intro'] = "下一级" . $data['user_info']['nick_name'] . '给您返利' . $info1['money'];  //旁白
                        $info1['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                        $info1['typefrom'] = 2;  // 1自身订单返利2下一级订单返利3下二级订单返利
                        $info1['balance'] = $p_1info['balance_s'];  //没执行之前用户金额
                        $info1['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                        //leapmary//用户的钱#######################################
                        $ifcommit[] = $this->addmoneyandlog($info1,$encodekey);
                    }
                    //给给上二级加钱
                    if($data['user_info']['p_2'] != 0){
                        $p_2info = action('common/User/getuserinfo',['token' => '','u_id' => $data['user_info']['p_2']]);
                        //leapmary//用户的钱#######################################
                        $info2['createtime'] = $time;
                        $info2['user_id'] = $data['user_info']['p_2'];  //给谁返利
                        $info2['be_user_id'] = $data['user_info']['id'];  //因为谁执行返利
                        $info2['order_id'] = $data['jdapiorder']['id'];  //那一个订单号
                        $info2['money'] = action('common/Goods/getcommission',['money' => $data['jdapiorder']['actualCommission'],'rebate' => $confing['tworebate']]);  //返利多少钱
                        $info2['intro'] = "下二级" . $data['user_info']['nick_name'] . '给您返利' . $info2['money'];  //旁白
                        $info2['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                        $info2['typefrom'] = 3;  // 1自身订单返利2下一级订单返利3下二级订单返利
                        $info2['balance'] = $p_2info['balance_s'];  //没执行之前用户金额
                        $info2['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                        //leapmary//用户的钱#######################################
                        $ifcommit[] = $this->addmoneyandlog($info2,$encodekey);
                    }
                    break;
                //3蘑菇街订单返利
                case 3:
                    $type_money = 3;
                    //造假的蘑菇街
                    echo 0;
                    exit;
                    break;
                //4提现
                case 4:
                    $type_money = 4;
                    //leapmary//用户的钱#######################################
                    $info0['createtime'] = $time;
                    $info0['user_id'] = $data['user_info']['id'];  //谁提现
                    $info0['be_user_id'] = $data['user_info']['id'];  //因为谁提现
                    $info0['order_id'] = 0;  //那一个订单号
                    $info0['money'] = -$data['gold'];  //提现多少是减去
                    $info0['intro'] = $data['user_info']['nick_name'] . "申请提现" . $info0['money'];  //旁白
                    $info0['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                    $info0['typefrom'] = 1;  // 1自身订单返利2下一级订单返利3下二级订单返利
                    $info0['balance'] = $data['user_info']['balance_s'];  //没执行之前用户金额
                    $info0['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                    //leapmary//用户的钱#######################################

                    //给自己加钱
                    $ifcommit[] = $this->addmoneyandlog($info0,$encodekey);
                    break;
                //5注册留记录
                case 5:
                    /*print_r($data);exit;*/
                    $type_money = 5; //注册
                    //改订单状态为已经返利
                    //leapmary//用户的钱#######################################
                    $info0['createtime'] = $time;
                    $info0['user_id'] = $data['selfid'];  //给谁返利
                    $info0['be_user_id'] = $data['selfid'];  //因为谁执行返利
                    $info0['order_id'] = 0;  //那一个订单号
                    $info0['money'] = 0;  //返利多少钱
                    $info0['intro'] = "注册的0";  //旁白
                    $info0['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现5注册
                    $info0['typefrom'] = 1;  // 1自身订单返利2下一级订单返利3下二级订单返利
                    $info0['balance'] = 0;  //没执行之前用户金额
                    $info0['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                    //leapmary//用户的钱#######################################

                    //给自己加钱
                    $ifcommit[] = $this->addmoneyandlog($info0,$encodekey);
                    //给上一级加钱
                    if($data['p_1'] != 0){
                        $p_1info = action('common/User/getuserinfo',['token' => '','u_id' => $data['p_1']]);
                        if(!empty($p_1info)){
                            //leapmary//用户的钱#######################################
                            $info1['createtime'] = $time;
                            $info1['user_id'] = $data['p_1'];  //给谁返利
                            $info1['be_user_id'] = $data['selfid'];  //因为谁执行返利
                            $info1['order_id'] = 0;  //那一个订单号
                            $info1['money'] = 0;  //返利多少钱
                            $info1['intro'] = "下一级注册给您返利0";  //旁白
                            $info1['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                            $info1['typefrom'] = 2;  // 1自身订单返利2下一级订单返利3下二级订单返利
                            $info1['balance'] = $p_1info['balance_s'];  //没执行之前用户金额
                            $info1['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                            //leapmary//用户的钱#######################################
                            $ifcommit[] = $this->addmoneyandlog($info1,$encodekey);
                        }

                    }
                    //给给上二级加钱
                    if($data['p_2'] != 0){
                        $p_2info = action('common/User/getuserinfo',['token' => '','u_id' => $data['p_2']]);
                        if(!empty($p_2info)){
                            //leapmary//用户的钱#######################################
                            $info2['createtime'] = $time;
                            $info2['user_id'] = $data['p_2'];  //给谁返利
                            $info2['be_user_id'] = $data['selfid'];  //因为谁执行返利
                            $info2['order_id'] = 0;  //那一个订单号
                            $info2['money'] = 0;  //返利多少钱
                            $info2['intro'] = "下二级注册给您返利0";  //旁白
                            $info2['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                            $info2['typefrom'] = 3;  // 1自身订单返利2下一级订单返利3下二级订单返利
                            $info2['balance'] = $p_2info['balance_s'];  //没执行之前用户金额
                            $info2['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                            //leapmary//用户的钱#######################################
                            $ifcommit[] = $this->addmoneyandlog($info2,$encodekey);
                        }

                    }
                    break;
                //管理员手动操作
                case 6:
                    $type_money = 6;
                    //leapmary//用户的钱#######################################
                    $info0['createtime'] = $time;
                    $info0['user_id'] = $data['user_info']['id'];  //给谁操作
                    $info0['be_user_id'] = $data['user_info']['id'];  //给谁操作
                    $info0['order_id'] = 0;  //那一个订单号
                    $info0['money'] = $data['num'];  //操作多少是减去
                    $info0['intro'] = $data['user_info']['nick_name'] . "管理员操作" . $info0['money'];  //旁白
                    $info0['type'] = $type_money;   //1淘宝订单返利2京东订单返利3蘑菇街订单返利4提现
                    $info0['typefrom'] = 1;  // 1自身订单返利2下一级订单返利3下二级订单返利
                    $info0['balance'] = $data['user_info']['balance_s'];  //没执行之前用户金额
                    $info0['v_state'] = 0;  //0提现申请1已经提现,如果type=4这个才有意义
                    //leapmary//用户的钱#######################################

                    //给自己加钱
                    $ifcommit[] = $this->addmoneyandlog2($info0,$encodekey);
                    break;
                    break;
                default:
                    echo "系统错误,请联系管理员";
                    exit;
                    break;
            }

            //有一个0或者空或者null就是0也就是false
            $ifcommitre = 1;
            foreach($ifcommit as $k => $v){
                $ifcommitre = $ifcommitre * $v;
            }

            // 提交事务
            if($ifcommitre){
                Db::commit();
            }else{
                Db::rollback();
            }

        }catch(\Exception $e){
            // 回滚事务
            Db::rollback();
        }

        if($ifcommitre){
            return 1;
        }else{
            return 0;
        }

    }

    /**
     * 记录用户money流水并且加钱
     **/
    public function addmoneyandlog2($info,$encodekey){
        //去掉无限循环小数,舍去取整
        $info['money'] = floor($info['money'] * 100) / 100;

        //记录日志
        $relog = db('moneydetail')->insertGetId($info);

        //总额只加不减
        if($info['money'] >= 0){
            $moneynumone = $info['money'];
            $moneynumall = $info['money'];
        }else{
            $moneynumone = $info['money'];
            $moneynumall = 0;
        }

        if($info['money'] == 0){
            $one = 1;
        }else{
            //开始加钱
            $one = db()->execute("UPDATE clt_users
                                        SET balance = ENCODE(
                                            DECODE(
                                                balance,
                                                '" . $encodekey . "'
                                            ) + " . $moneynumone . ",
                                            '" . $encodekey . "'
                                        ),
                                        all_balance = ENCODE(
                                            DECODE(
                                                all_balance,
                                                '" . $encodekey . "'
                                            ) + " . $moneynumall . ",
                                            '" . $encodekey . "'
                                        ),
                                        gold = gold + " . $moneynumone . ",
                                        account_gold = account_gold + " . $moneynumall . "
                                        where id = " . $info['user_id']);
        }

        if(($relog * $one) != 0){
            return 1;
        }else{
            return 0;
        }

    }



}







