<?php
namespace app\api\controller;
use think\Db;
use app\common\logic\RankingLogic;
class Ranking extends ApiBase
{
    /**
     * 获取金铲子
     */
    public function get_gold_shovel(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $system = Db::name('system')->field('id,name,title,money,logo')->where('id=1')->find();
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！', 'data' => $system]);
    }
    /**
     * 购买铲子
     */
    public function buy_gold_shovel(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $num=I('num',1);
        $RankingLogic = new RankingLogic();
        $res = $RankingLogic->buy_gold_shovel($user_id,$num);
        $this->ajaxReturn($res);
//        if ($res['status'] == -1 ) {
//            $this->ajaxReturn(['status' => -2, 'msg' => $res['msg']]);
//        }else{
//            $this->ajaxReturn(['status' => 1, 'msg' => '购买成功']);
//        }

    }

    /*
     * 排位
     */
    public function my_ranking(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pageParam=[];
        $where=[];
        $where['rank_status']=0;
        $list=Db::name('ranking')->field('id')->where($where)->select();
        $data_list=[];
        foreach ($list as $key=>$value)//二维数组转换
        {
            $data_list[$key]=$value['id'];
        }
        $data_list=array_flip($data_list);//键值翻转
        $where=[];
        $where['user_id']=$user_id;
        $where['rank_status']=0;
        $where['is_delete']=0;
        $rank_list=Db::name('ranking')
            ->field('id,user_id,rank_time')
            ->where($where)
            ->order('add_time')
            ->paginate(10,false,$pageParam);
        $rank_list=$rank_list->toArray();
        $rank_list=$rank_list['data'];
        foreach ($rank_list as $k=>$v){
            $rank_list[$k]['num']=$data_list[$v['id']];
            $rank_list[$k]['rank']=$data_list[$v['id']]+1;
            $rank_list[$k]['rank_time']=date('Y-m-d H:i:s',$v['rank_time']);
        }
        $where=[];
        $where['rank_status']=1;
        $count=Db::name('ranking')->where($where)->count();//出局人数
        $data['rank_list']=$rank_list;
        $data['count']=$count;
//        $subQuery = Db::name('ranking')
//            ->fieldRaw('*, @rownum := @rownum+1 AS rownum')
//            ->where($where)
//            ->buildSql();
//        $where=[];
//        $where['a.user_id']=$user_id;
//        $rank_list=Db::table($subQuery.' a')
//            ->where($where)
//            ->order('a.add_time')
//            ->paginate(10,false,$pageParam);
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$data]);

    }
    /*
     * 挂卖
     */
    public function user_auction(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pageParam=[];
        $where=[];
        $where['user_id']=$user_id;
        $list=Db::name('auction')
            ->field('id,user_id,currency_num,all_money,add_time')
            ->where($where)
            ->paginate(10,false,$pageParam);
        $list=$list->toArray();
        $list=$list['data'];
        foreach ($list as $k=>$v){
            $list[$k]['add_time']=date('Y-m-d H:i:s',$v['add_time']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$list]);
    }
    /*
     * 道具详情
     */
    public function goods_details(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $goods=Db::name('system')
            ->field('id,name,money,title,logo')
            ->find();
        $goods['logo']=SITE_URL.$goods['logo'];
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$goods]);
    }
    /*
     * 我的订单
     */
    public function order(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $goods=Db::name('system')
            ->field('id,name,money,title,logo')
            ->find();
        $goods['logo']=SITE_URL.$goods['logo'];
        $data['goods']=$goods;
        $pageParam=[];
        $where=[];
        $where['user_id']=$user_id;
        $where['rank_status']=0;
        $where['is_delete']=0;
        $rank_list=Db::name('ranking')
            ->field('id,user_id,rank_time')
            ->where($where)
            ->order('add_time')
            ->paginate(10,false,$pageParam);
        $rank_list=$rank_list->toArray();
        $rank_list=$rank_list['data'];
        foreach ($rank_list as $k=>$v){
            $rank_list[$k]['rank_time']=date('Y-m-d H:i:s',$v['rank_time']);
        }
        $data['ranking']=$rank_list;
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$data]);
    }
    /*
     * 删除订单
     */
    public function order_del(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $id=I('id');
        if(!$id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'id不能为空','data'=>'']);
        }
        $where['id']=$id;
        $where['user_id']=$user_id;
        $res=Db::name('ranking')->where($where)->update(['is_delete'=>1]);
        if($res){
            $this->ajaxReturn(['status' => 1, 'msg'=>'删除成功']);
        }else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'删除失败']);
        }
    }
}
