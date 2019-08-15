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


    public function test(){
        // $user_id = 1;
        // echo $this->create_token($user_id);


        echo $this->get_user_id();
    }


}
