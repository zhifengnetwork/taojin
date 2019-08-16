<?php
namespace app\api\controller;
use think\Db;

class Index extends ApiBase
{
    /*
     * 首页
     */
        public function index()
    {
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $jackpot=Db::name('jackpot')->where('id',1)->find();
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$jackpot]);
    }
    /*
     * 首页中奖
     */
    public function reward_list(){
        $today_time= strtotime(date("Y-m-d"),time());
        $where['r.reward_day']=$today_time;
        $reward=Db::name('reward')->alias('r')
            ->join('users u','u.id=r.user_id','LEFT')
            ->find('r.ranking_time,u.phone')
            ->where($where)
            ->select();
        foreach ($reward as $key=>$value){
            $reward[$key]['ranking_time']=date('Y-m-d H:i:s',$value['ranking_time']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$reward]);
    }
    public function test(){
        // $user_id = 1;
        // echo $this->create_token($user_id);


        echo $this->get_user_id();
    }


}
