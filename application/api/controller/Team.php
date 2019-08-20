<?php
namespace app\api\controller;
use think\Db;

class Team extends ApiBase
{
    /*
     * 团队列表
     */
    public function team_list(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $is_direct=I('is_direct',0);//是否直推  0：直推  1：其他
        if(!$is_direct){//直推会员
            $team_list=Db::name('users')->field('id,nick_name,phone')->where('p_1',$user_id)->select();
            $count=Db::name('users')->where('p_1',$user_id)->count();
            $data['data']=$team_list;
            $data['count']=$count;
            $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $data]);
        }else{
            $team_list=Db::name('users')->field('id,nick_name,phone')->where('p_1',$user_id)->select();
            $count=0;
            if($team_list){
                $my_list=[];
                $my_list=$this->recursion_team($team_list,$my_list);
                $data['data']=$my_list;
                $data['count']=count($my_list);
                $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $data]);
            }else{
                $data['data']=$team_list;
                $data['count']=$count;
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $data]);
        }
    }
    /*
     * 递归查找下级
     */
    public function recursion_team($team_list,$my_list){
        if($team_list){
            foreach ($team_list as $key=>$value){//循环找下级
                $list=Db::name('users')->field('id,nick_name,phone')->where('p_1',$value['id'])->select();
                if($list){
                    $my_list=array_merge($my_list,$list);//合并数组
                    $my_list=$this->recursion_team($list,$my_list);//递归
                }
            }
            return $my_list;
        }else{
            return $my_list;
        }
    }

    /*
     * 团队订单
     */
    public function team_rank_list(){
        $user_id=I('user_id');
        if(!$user_id){
            $this->ajaxReturn(['status' => -2, 'msg' => 'user_id不能为空']);
        }
        $pageParam=[];
        $rank_list=Db::name('ranking')
            ->where('user_id',$user_id)
            ->field('id,money,add_time')
            ->paginate(10,false,$pageParam);
        $rank_list=$rank_list->toArray();
        $rank_list=$rank_list['data'];
        foreach ($rank_list as $key=>$value){
            $rank_list[$key]['add_time']=date('Ymd',$value['add_time']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $rank_list]);
    }

}
