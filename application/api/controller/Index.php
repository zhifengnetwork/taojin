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
        $where['end_time']=['gt',time()];
        $where['user_id']=$user_id;
        $where['status']=0;
        $give=Db::name('give')->where($where)->find();
        if($give){
            $jackpot['is_give']=1;
        }else{
            $jackpot['is_give']=0;
        }
        $goods=Db::name('system')
            ->field('id,name,money,title,logo')
            ->find();
        $goods['logo']=SITE_URL.'/public'.$goods['logo'];
        $system = Db::name('system')->where('id=1')->value('notice');
        $jackpot['notice']=$system;
        $bonus_time=strtotime(date("Y-m-d")." ".$jackpot['open_time']);
        if($bonus_time>time()){
            $jackpot['data_time']=date("Y-m-d");
        }else{
            $jackpot['data_time']=date("Y-m-d",strtotime('+1 day'));
        }
        $jackpot['goods_name']=$goods['name'];
        $jackpot['money']=$goods['money'];
        $jackpot['title']=$goods['title'];
        $jackpot['logo']=$goods['logo'];
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
        if($give_list){
            $number=0;
            foreach ($give_list as $key=>$value){
                $num_i=$this->receive_log($user_id,$value['num'],$value['id']);
                $number=$number+$num_i;
            }
            $data['num']=$number;
            $this->ajaxReturn(['status' => 1, 'msg' => '领取成功！','data'=>$data]);
        }else{
            $this->ajaxReturn(['status' => -2, 'msg' => '糖果已过期！']);
        }
    }
    /*
     * 领取增加糖果
     */
    public function receive_log($user_id,$num,$give_id){
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
        $today_time= strtotime(date("Y-m-d"),time());
        $where=[];
        $where['r.reward_day']=$today_time;
        $reward=Db::name('reward')->alias('r')
            ->join('users u','u.id=r.user_id','LEFT')
            ->field('r.rank_time,u.phone')
            ->where($where)
            ->select();
        foreach ($reward as $key=>$value){
            $reward[$key]['rank_time']=date('Y-m-d H:i:s',$value['rank_time']);
            $reward[$key]['phone']=shadow($reward[$key]['phone']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$reward]);
    }
    public function test(){
        // $user_id = 1;
        // echo $this->create_token($user_id);


        echo $this->get_user_id();
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
            ->paginate(10,false,$pageParam);
        $balance_list=$balance_list->toArray();
        $balance_list=$balance_list['data'];
        foreach ($balance_list as $key=>$value){
            $balance_list[$key]['createtime']=date('Y-m-d H:i:s',$value['createtime']);
            $balance_list[$key]['type_text']=$this->balance_type($value['type']);
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
        $integral_list=Db::name('integral')->where($where)
            ->paginate(10,false,$pageParam);
        $integral_list=$integral_list->toArray();
        $integral_list=$integral_list['data'];
        foreach ($integral_list as $key=>$value){
            $integral_list[$key]['createtime']=date('Y-m-d H:i:s',$value['createtime']);
            $integral_list[$key]['type_text']=$this->integral_type($value['type']);
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
        $users_currency_list=Db::name('users_currency')->where($where)
            ->paginate(10,false,$pageParam);
        $users_currency_list=$users_currency_list->toArray();
        $users_currency_list=$users_currency_list['data'];
        foreach ($users_currency_list as $key=>$value){
            $users_currency_list[$key]['add_time']=date('Y-m-d H:i:s',$value['add_time']);
            $users_currency_list[$key]['type_text']=$this->currency_type($value['type']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$users_currency_list]);
    }
    function balance_type($type){
        switch ($type){
            case 1:
                return '返利';
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
            default:
                return '参数不对';
                break;
        }
    }
    function integral_type($type){
        switch ($type){
            case 0:
                return '赠送';
                break;
            case 1:
                return '兑换';
                break;
            case 2:
                return '被赠与';
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
    function currency_type($type){
        switch ($type){
            case 0:
                return '挂卖';
                break;
            case 1:
                return '赠送';
                break;
            case 2:
                return '管理员操作';
                break;
            case 3:
                return '被赠与';
                break;
            default:
                return '参数不对';
                break;
        }
    }
}
