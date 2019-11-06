<?php
namespace app\api\controller;
use think\Db;

class Index extends ApiBase
{
    /*
     * 首页
     */
        public function index(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $jackpot=Db::name('jackpot')->where('id',1)->find();
        $start_time=strtotime("2019-09-09 14:01:00");
        if(time()<$start_time){
            $jackpot['integral_num']=0;
        }
        $where['end_time']=['gt',time()];
        $where['user_id']=$user_id;
        $where['status']=0;
        $give=Db::name('give')->where($where)->find();
        if($give){
            $jackpot['is_give']=1;
        }else{
            $jackpot['is_give']=0;
        }
        $where_u=[];
        $where_u['user_id']=$user_id;
        $where_u['status']=0;
        $user_reward_m=Db::name('user_reward');
        $user_reward=$user_reward_m->where($where_u)->find();
        if(!$user_reward){
            $jackpot['update']=1;
            $data_r['user_id']=$user_id;
            $data_r['status']=0;
            $data_r['add_time']=time();
            $user_reward_m->insert($data_r);
        }else{
            $jackpot['update']=0;
        }
        $goods=Db::name('system')
            ->field('id,name,money,title,logo,key')
            ->find();
        $goods['logo']=$goods['logo'];
        $system = Db::name('system')->where('id=1')->value('notice');
        $jackpot['notice']=$system;
        $bonus_time=strtotime(date("Y-m-d")." ".$jackpot['open_time']);
        if($bonus_time>time()){
            $jackpot['surplus_time']=$bonus_time-time();
            $jackpot['data_time']=date("Y-m-d");
        }else{
            $jackpot['data_time']=date("Y-m-d",strtotime('+1 day'));
            $kj_time=strtotime(date("Y-m-d",strtotime('+1 day'))." ".$jackpot['open_time']);
            $jackpot['surplus_time']=$kj_time-time();
        }
        $jackpot['goods_name']=$goods['name'];
        $jackpot['money']=$goods['money'];
        $jackpot['title']=$goods['title'];
        $jackpot['logo']=$goods['logo'];
        $jackpot['version']=$goods['key'];
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$jackpot]);
    }
    /*
     * 首页领取糖果
     */
    public function receive_give(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $where['end_time']=['gt',time()];
        $where['user_id']=$user_id;
        $where['status']=0;
        $give_list=Db::name('give')->where($where)->select();
        $sum_num=Db::name('give')->where($where)->sum('num');
        if($give_list) {
            $ids = $this->get_ids($give_list);
            $this->receive_log($user_id, $sum_num, $ids);
            $data['num'] = $sum_num;
            $this->ajaxReturn(['status' => 1, 'msg' => '领取成功！', 'data' => $data]);
        } else{
            $this->ajaxReturn(['status' => -2, 'msg' => '糖果已领取！']);
        }
//        if($give_list){
//            $number=0;
//            foreach ($give_list as $key=>$value){
//                $num_i=$this->receive_log($user_id,$value['num'],$value['id']);
//                $number=$number+$num_i;
//            }
//            $data['num']=$number;
//            $this->ajaxReturn(['status' => 1, 'msg' => '领取成功！','data'=>$data]);
//        }else{
//            $this->ajaxReturn(['status' => -2, 'msg' => '糖果已过期！']);
//        }
    }
    public function get_ids($give_list){
        $ids="";
        foreach ($give_list as $key=>$value){
            if(!$ids){
                $ids=$value['id'];
            }else{
                $ids=$ids.','.$value['id'];
            }
        }
        return $ids;
    }
    /*
     * 领取增加糖果
     */
    public function receive_log($user_id,$num,$ids){
        $user=Db::name('users')->where(['id'=>$user_id])->find();
        Db::startTrans();
        $system_money=Db::name('system_money')->where('id',1)->find();//系统总额
        $res=Db::name('users')->where(['id'=>$user_id])->setInc('integral',$num);
        $old_integral=$system_money['integral'];
        $system_money['integral']=$system_money['integral']-$num;
        if($system_money['integral']<0){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '系统糖果不足，请联系管理员！']);
        }
        $system_data['integral']=-$num;
        $system_data['new_integral']=$system_money['integral'];
        $system_data['old_integral']=$old_integral;
        $system_data['add_time']=time();
        $system_data['desc']='领取糖果修改系统糖果';
        $sys_id=Db::name('system_money_log')->insertGetId($system_data);
        if(!$sys_id){
            Db::rollback();
            return false;
        }
        $r=Db::name('system_money')->update($system_money);
        if($res){
            $detail['u_id']=$user_id;
            $detail['u_name']=$user['nick_name'];
            $detail['integral']=$num;
            $detail['type']=3;//出局赠送
            $detail['then_integral']=$user['integral'];
            $detail['createtime']=time();
            $id=Db::name('integral')->insertGetId($detail);
            if(!$id){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => 'log生成失败，领取失败！']);
            }
            $re=Db::name('give')->where('id','in',$ids)->update(['status'=>1]);//已领取
            if(!$re){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '领取失败！']);
            }
        }else{
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '领取失败！']);
        }
        Db::commit();
        return $num;
    }
    /*
     * 领取增加糖果
     */
    public function receive_log2($user_id,$num,$give_id){
        $user=Db::name('users')->where(['id'=>$user_id])->find();
        Db::startTrans();
        $system_money=Db::name('system_money')->where('id',1)->find();//系统总额
        $res=Db::name('users')->where(['id'=>$user_id])->setInc('integral',$num);
        $re=Db::name('give')->where('id',$give_id)->update(['status'=>1]);//已领取
        $system_money['integral']=$system_money['integral']-$num;
        if($system_money['integral']<0){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '系统糖果不足，请联系管理员！']);
        }
        $system_data['integral']=-$num;
        $system_data['new_integral']=$system_money['integral'];
        $system_data['add_time']=time();
        $system_data['desc']='领取糖果修改系统糖果';
        $sys_id=Db::name('system_money_log')->insertGetId($system_data);
        if(!$sys_id){
            Db::rollback();
            return false;
        }
        $r=Db::name('system_money')->update($system_money);
        if($res&&$re&&$r){
            $detail['u_id']=$user_id;
            $detail['u_name']=$user['nick_name'];
            $detail['integral']=$num;
            $detail['type']=3;//出局赠送
            $detail['then_integral']=$user['integral'];
            $detail['createtime']=time();
            $id=Db::name('integral')->insertGetId($detail);
            if(!$id){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '领取失败！']);
            }
        }else{
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '领取失败！']);
        }
        Db::commit();
        return $num;
    }
    /*
     * 首页中奖
     */
    public function reward_list(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $today_time = strtotime(date("Y-m-d"), time());
        $is_reward=I('is_reward',0);
        if($is_reward==0){//中奖列表
            $today_time= strtotime(date("Y-m-d",strtotime("-10 day")),time());
            $where=[];
            $pageParam=[];
            $where['r.reward_day']=['gt',$today_time];
            $reward=Db::name('reward')->alias('r')
                ->join('users u','u.id=r.user_id','LEFT')
                ->field('r.rank_time,u.phone')
                ->where($where)
                ->order('r.id DESC')
                ->paginate(10,false,$pageParam)
                ->toArray();
            $reward=$reward['data'];
            foreach ($reward as $key=>$value){
                $reward[$key]['rank_time']=date('Y-m-d H:i',$value['rank_time']);
                $reward[$key]['phone']=shadow($reward[$key]['phone']);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$reward]);
        }
        $where['reward_day'] = $today_time;
        $where['status']=1;
        $reward_log = Db::name('reward_log')->where($where)->find();
        if(!$reward_log){
            $reward=[];
        }else{
            if($user_id&&$is_reward==1){
                $where_u=[];
                $where_u['user_id']=$user_id;
                $today_time = strtotime(date("Y-m-d"), time());
                $where_u['data_time']=$today_time;
                $where_u['status']=1;
                $user_reward_m=Db::name('user_reward');
                $user_reward=$user_reward_m->where($where_u)->find();
                if($user_reward){
                    $reward=[];//发过消息，返回空数据
                }else{
//                $today_time= strtotime(date("Y-m-d"),time());
                    $where=[];
                    $pageParam=[];
                    $where['r.reward_day']=$today_time;
                    $reward=Db::name('reward')->alias('r')
                        ->join('users u','u.id=r.user_id','LEFT')
                        ->field('r.rank_time,u.phone')
                        ->where($where)
                        ->paginate(10,false,$pageParam)
                        ->toArray();
                    $reward=$reward['data'];
                    $data=[];
                    $data['user_id']=$user_id;
                    $data['add_time']=time();
                    $data['data_time']=$today_time;
                    $data['status']=1;
                    $ids=$user_reward_m->insertGetId($data);
                    if(!$reward){//无人中奖
                        $reward_log['reward_time']=date('Y-m-d H:i',$reward_log['reward_time']);
                        $this->ajaxReturn(['status' => 2, 'msg' => '获取成功！','data'=>$reward_log['reward_time']]);
                    }else{
                        foreach ($reward as $key=>$value){
                            $reward[$key]['rank_time']=date('Y-m-d H:i',$value['rank_time']);
                            $reward[$key]['phone']=shadow($reward[$key]['phone']);
                        }
                    }
                }
            }else{
                $today_time= strtotime(date("Y-m-d",strtotime("-10 day")),time());
                $where=[];
                $pageParam=[];
                $where['r.reward_day']=['gt',$today_time];
                $reward=Db::name('reward')->alias('r')
                    ->join('users u','u.id=r.user_id','LEFT')
                    ->field('r.rank_time,u.phone')
                    ->where($where)
                    ->order('r.id DESC')
                    ->paginate(10,false,$pageParam)
                    ->toArray();
                $reward=$reward['data'];
                foreach ($reward as $key=>$value){
                    $reward[$key]['rank_time']=date('Y-m-d H:i',$value['rank_time']);
                    $reward[$key]['phone']=shadow($reward[$key]['phone']);
                }
            }
        }

        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$reward]);
    }
    public function test(){
        $where = [];
        $start_time=1567597020;
        $end_time=1567683480;
        $where['r.rank_time'] = ['between', [$start_time, $end_time]];
        $reward_ranking_list = Db::name('ranking')->alias('r')
            ->join('reward re','re.ranking_id=r.id','LEFT')
            ->where('re.ranking_id is null')
            ->where($where)->limit(100)->select();
        $num=count($reward_ranking_list);
        echo $num."==== ".Db::name('ranking')->getLastSql();
    }
    public function balance_list(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $have=I('have',0);
        $where=[];
        $pageParam=[];
        if($have==2){
            $where['money']=['egt',0];
        }elseif($have==1){
            $where['money']=['lt',0];
        }
        $where['user_id']=$user_id;
        $where['typefrom']=0;//排除冻结余额
        $balance_list=Db::name('moneydetail')
            ->where($where)
            ->order('createtime DESC')
            ->paginate(10,false,$pageParam);
        $balance_list=$balance_list->toArray();
        $balance_list=$balance_list['data'];
        foreach ($balance_list as $key=>$value){
            $balance_list[$key]['createtime']=date('Y-m-d H:i:s',$value['createtime']);
            $balance_list[$key]['type_text']=$this->balance_type($value['type']);
            if($value['be_user_id']){
                if($value['type']==2){
                    $balance_list[$key]['phone']='赠送给'.Db::name('users')->where('id',$value['be_user_id'])->value('phone');
                }elseif ($value['type']==5){
                    $balance_list[$key]['phone']=Db::name('users')->where('id',$value['be_user_id'])->value('phone').'赠送';
                }
            }else{
                $balance_list[$key]['phone']='无';
            }
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$balance_list]);
    }
    public function integral_list(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $have=I('have',0);
        $where=[];
        $pageParam=[];
        if($have==1){
            $where['integral']=['egt',0];
        }elseif($have==2){
            $where['integral']=['lt',0];
        }
        $where['u_id']=$user_id;
        $integral_list=Db::name('integral')
            ->where($where)
            ->order('id DESC')
            ->paginate(10,false,$pageParam);
        $integral_list=$integral_list->toArray();
        $integral_list=$integral_list['data'];
        foreach ($integral_list as $key=>$value){
            $integral_list[$key]['createtime']=date('Y-m-d H:i:s',$value['createtime']);
            $integral_list[$key]['type_text']=$this->integral_type($value['type'],$value['for_user_id']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$integral_list]);
    }
    public function currency_list(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $have=I('have',0);
        $where=[];
        $pageParam=[];
        if($have==1){
            $where['currency']=['egt',0];
        }elseif($have==2){
            $where['currency']=['lt',0];
        }
        $where['user_id']=$user_id;
        $users_currency_list=Db::name('users_currency')
            ->where($where)
            ->order('id DESC')
            ->paginate(10,false,$pageParam);
        $users_currency_list=$users_currency_list->toArray();
        $users_currency_list=$users_currency_list['data'];
        foreach ($users_currency_list as $key=>$value){
            $users_currency_list[$key]['add_time']=date('Y-m-d H:i:s',$value['add_time']);
            $users_currency_list[$key]['type_text']=$this->currency_type($value['type'],$value['for_user_id']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$users_currency_list]);
    }
    public function get_lock_balance(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pageParam=[];
//        $lock_balance_list=Db::name('moneydetail')->where(['type'=>3,'user_id'=>$user_id])->whereOr(['typefrom'=>1])
        $lock_balance_list=Db::name('moneydetail')
            ->where('type=3 or typefrom=1')
            ->where(['user_id'=>$user_id])
            ->field('id,user_id,money,type,typefrom,intro')
            ->order('id DESC')
            ->paginate(10,false,$pageParam)
            ->toArray();
        $lock_balance_list=$lock_balance_list['data'];
        foreach ($lock_balance_list as $key=>$value){
            if($value['type']==3&&$value['typefrom']==0){
                $lock_balance_list[$key]['money']=-$value['money'];
            }
            $lock_balance_list[$key]['intro']=mb_substr($lock_balance_list[$key]['intro'],0,4,'utf-8');
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$lock_balance_list]);
    }
    public function currency_detailed(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $user=Db::name('users')->field('currency,lock_currency')->where('id',$user_id)->find();
        $user['currency_money']=Db::name('config')->where(['inc_type'=>'taojin','name'=>'currency'])->value('value');
        $user['yesterday_money']=Db::name('config')->where(['inc_type'=>'taojin','name'=>'yesterday_currency'])->value('value');
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$user]);
    }
    public function recharge_balance_detailed(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pageParam=[];
        $lock_balance_list = Db::name('moneydetail')->alias('m')
            ->join('users u','u.id=m.be_user_id','LEFT')
            ->where('m.type=2 or m.type=5 or m.type=13')
            ->where(['m.user_id'=>$user_id,'m.typefrom'=>0])
            ->field('m.id,m.user_id,m.be_user_id,m.money,m.type,m.typefrom,m.intro,m.createtime,u.phone be_mobile')
            ->order('id DESC')
            ->paginate(10,false,$pageParam)
            ->toArray();
        $lock_balance_list = $lock_balance_list['data'];
        foreach ($lock_balance_list as $key=>$value){
//            $lock_balance_list[$key]['be_mobile'] = Db::name('users')->where(['id'=>$value['be_user_id']])->value('phone');
            $lock_balance_list[$key]['createtime'] = date('Y-m-d H:i:s',$value['createtime']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$lock_balance_list]);
    }
    public function give_detailed(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pageParam=[];
        $where=[];
        $where['user_id']=$user_id;
        $where['status']=1;
        $give_list=Db::name('give')
            ->where($where)
            ->order('add_time DESC')
            ->paginate(10,false,$pageParam)
            ->toArray();
        $give_list=$give_list['data'];
        foreach ($give_list as $key=>$value){
            $give_list[$key]['type_text']=$this->give_type($value['type']);
            $give_list[$key]['add_time']=date('Y-m-d H:i:s',$value['add_time']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$give_list]);
    }
    function give_type($type){
        switch ($type){
            case 0:
                return '两倍出局';
                break;
            case 1:
                return '抽奖';
                break;
            case 2:
                return '直';
                break;
            case 3:
                return '间';
                break;
            case 4:
                return '队长';
                break;
            case 5:
                return '场主';
                break;
            case 6:
                return '平';
                break;
            case 7:
                return '三倍出局';
                break;
            case 8:
                return '强制出局';
                break;
            default:
                return '参数不对';
                break;
        }
    }
    function balance_type($type){
        switch ($type){
            case 1:
                return '直';
                break;
            case 2:
                return '赠送';
                break;
            case 3:
                return '解冻';
                break;
            case 4:
                return '提现';
                break;
            case 5:
                return '被赠送';
                break;
            case 6:
                return '兑换';
                break;
            case 7:
                return '挂卖';
                break;
            case 8:
                return '购买道具';
                break;
            case 9:
                return '中奖';
                break;
            case 10:
                return '出局';
                break;
            case 11:
                return '场主';
                break;
            case 12:
                return '平';
                break;
            case 13:
                return '充值';
                break;
            case 14:
                return '队长';
                break;
            case 15:
                return '间';
                break;
            case 16:
                return '系统';
                break;
            case 17:
                return '买窝';
                break;
            case 18:
                return '买鸡';
                break;
            case 19:
                return '养殖解冻';
                break;
            case 20:
                return '强制出局';
                break;
            default:
                return '参数不对';
                break;
        }
    }
    function integral_type($type,$user_id){
        switch ($type){
            case 0:
                $phone=Db::name('users')->where('id',$user_id)->value('phone');
                if($phone){
                    return '赠送给'.$phone;
                }else {
                    return '赠送';
                }
                break;
            case 1:
                return '兑换';
                break;
            case 2:
                $phone=Db::name('users')->where('id',$user_id)->value('phone');
                if($phone){
                    return $phone.'赠送';
                }else {
                    return '别人赠送';
                }
                break;
            case 3:
                return '出局奖励';
                break;
            case 8:
                return '管理员操作';
                break;
            default:
                return '参数不对';
                break;
        }
    }
    function currency_type($type,$user_id){
        switch ($type){
            case 0:
                return '挂卖';
                break;
            case 1:
                $phone=Db::name('users')->where('id',$user_id)->value('phone');
                if($phone){
                    return '赠送给'.$phone;
                }else {
                    return '赠送';
                }
                break;
            case 2:
                return '后台操作';
                break;
            case 3:
                $phone=Db::name('users')->where('id',$user_id)->value('phone');
                if($phone){
                    return $phone.'赠送';
                }else {
                    return '别人赠送';
                }
                break;
            case 4:
                return '管理员操作';
                break;
            case 5:
                return '兑换';
                break;
            default:
                return '参数不对';
                break;
        }
    }
}
