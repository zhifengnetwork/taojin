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
     * 赠送余额
     */
    public function give_balance(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $u_id=I('u_id');
        $balance=I('money');
        if(!$u_id){
            $this->ajaxReturn(['status' => -2, 'msg' => 'u_id不能为空！']);
        }
        if(!$balance){
            $this->ajaxReturn(['status' => -2, 'msg' => 'money不能为空！']);
        }
        $is_int=$balance/100;
        if(ceil($is_int)!=$is_int){
            $this->ajaxReturn(['status' => -2, 'msg' => '金额必须是100的倍数！']);
        }
        $user=Db::name('users')->where(['id'=>$user_id])->find();
        $give_user=Db::name('users')->where(['id'=>$u_id])->find();
        if($user['balance']<$balance){
            $this->ajaxReturn(['status' => -2, 'msg' => '您的余额不足，不能赠送！']);
        }
        if(!$give_user){
            $this->ajaxReturn(['status' => -2, 'msg' => '赠送用户不存在，请输入正确的用户id！']);
        }
        Db::startTrans();
        $res=Db::name('users')->where(['id'=>$u_id])->setInc('balance',$balance);
        if($res){
            $detail['user_id']=$u_id;
            $detail['type']=5;//被赠送
            $detail['money']=$balance;
            $detail['createtime']=time();
            $detail['intro']=$user['nick_name'].'赠送';
            $id=Db::name('moneydetail')->insertGetId($detail);
            if(!$id){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
            }
        }
        $re=Db::name('users')->where(['id'=>$user_id])->setDec('balance',$balance);
        if($re){
            $detail=[];
            $detail['user_id']=$u_id;
            $detail['type']=2;//被赠送
            $detail['money']=$balance;
            $detail['createtime']=time();
            $detail['intro']='赠送给'.$give_user['nick_name'];
            $ids=Db::name('moneydetail')->insertGetId($detail);
            if(!$ids){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
            }
        }
        if(!$res||!$re){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '赠送失败！']);
        }else{
            Db::commit();
            $this->ajaxReturn(['status' => 1, 'msg' => '赠送成功！']);
        }
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
}
